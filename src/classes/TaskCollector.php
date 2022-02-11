<?php

namespace NhnEdu\TeamTaskCollector;

use NhnEdu\PhpDooray\DoorayCommonApi;
use NhnEdu\PhpDooray\DoorayProjectApi;
use NhnEdu\PhpDooray\DoorayMessengerHelper;

define('SEARCH_TODAY_DATE', date('Y-m-d').'T23:59:59');

class TaskCollector {

	private $_configFilePath;

	private $_commonApi;
	private $_projectApi;

	private $_cache;

	public function __construct($configFilePath = 'config.json') {

		$this->_cache = new LocalCache();
		$this->_configFilePath = $configFilePath;

		$this->loadConfig();
		$this->initDoorayApi();
		$this->loadMembers();
	}

	private function loadConfig() {
		$config = ConfigParser::getConfig($this->_configFilePath);

		if (!$config) {
			die("[Error] Configuration is not loaded.");
		}

		$EMPLOYEE_EMAILS = $config->EMPLOYEE_EMAILS;
		$PROJECT_IDS = $config->PROJECT_IDS;

		$this->_cache->putData('PERSONAL_AUTH_KEY', $config->AUTH_KEY);
		$this->_cache->putData('EMPLOYEE_EMAILS', $EMPLOYEE_EMAILS);
		$this->_cache->putData('PROJECT_IDS', $PROJECT_IDS);
		$this->_cache->putData('TENANT_ID', $config->TENANT_ID);
		$this->_cache->putData('SEARCH_BEGIN_DATE', $config->SEARCH_BEGIN_DATE);
		$this->_cache->putData('SEARCH_DUE_DATE', str_replace('{today}',SEARCH_TODAY_DATE,$config->SEARCH_DUE_DATE));

		$this->_cache->putData('NOTIFY_TARGET_PROJECT_ID', $config->NOTIFY_TARGET_PROJECT_ID);
		$this->_cache->putData('NOTIFY_TASK_SUBJECT', $config->NOTIFY_TASK_SUBJECT);

		$this->_cache->putData('NOTIFY_MESSENGER_HOOK_URL', $config->NOTIFY_MESSENGER_HOOK_URL);
		$this->_cache->putData('NOTIFY_MESSENGER_TEXT', $config->NOTIFY_MESSENGER_TEXT);

		$this->_cache->putData('CLEANUP_MODE', $config->CLEANUP_MODE);
		$this->_cache->putData('CLEANUP_DELAY_DAYS_AFTER_REGISTRATION', $config->CLEANUP_DELAY_DAYS_AFTER_REGISTRATION);
		$this->_cache->putData('CLEANUP_LOG_MESSAGE', $config->CLEANUP_LOG_MESSAGE);
	}

	private function initDoorayApi() {
		$this->_commonApi = new DoorayCommonApi($this->_cache->getData('PERSONAL_AUTH_KEY'));
		$this->_projectApi = new DoorayProjectApi($this->_cache->getData('PERSONAL_AUTH_KEY'));
	}

	private function loadMembers() {

		$members = $this->_commonApi->getMembers(0, 100, ['externalEmailAddresses' => $this->_cache->getData('EMPLOYEE_EMAILS')]);
		$memberIds = array_map(function($member) { return $member->id; }, $members);

		$this->_cache->putData('members', $members);
		$this->_cache->putData('memberIds', $memberIds);
	}

	public function collectTasks() {
		
		$TENANT_ID = $this->_cache->getData('TENANT_ID');
		$PROJECT_IDS = $this->_cache->getData('PROJECT_IDS');
		$memberIds = $this->_cache->getData('memberIds');

		$startTime = $this->__getTime();

		$resultList = [];
		foreach ($memberIds as $memberId) {
			$resultList[$memberId] = [];
		}

		$searchPeriod = $this->_cache->getData('SEARCH_BEGIN_DATE').'~'.$this->_cache->getData('SEARCH_DUE_DATE');

		foreach ($PROJECT_IDS as $projectId) {
			
			$workflows = $this->_projectApi->getProjectWorkflows($projectId);
			$progressingWorkflows = DoorayDataUtility::getProgressingWorkflow($workflows);
			$progressingWorkflowIds = DoorayDataUtility::getWorkflowIds($progressingWorkflows);
			
			foreach ($memberIds as $memberId) {
				$organizationMemberId = $this->_projectApi->getOrganizationMemberIdByProjectMember($projectId, $memberId);
				
				$tasks = $this->_projectApi->getAllTasks($projectId, [
														'toMemberIds'=>$organizationMemberId,
														'postWorkflowIds'=>$progressingWorkflowIds,
														'createdAt'=>$searchPeriod,
														'order'=>'createdAt'
													]);

				foreach ($tasks as $task) {
					$resultList[$memberId][] = [
										$task->project->code,
										$task->taskNumber, 
										$task->workflow->name, 
										$task->subject,
										DoorayDataUtility::getDoorayTaskUrl($TENANT_ID, $task->id),
										round((time() - strtotime($task->createdAt)) / 3600 / 24, 0),
										$task->project->id,
										$task->id
									];
				}
			}
		}

		$elapsedSecs = $this->__getTime() - $startTime;

		$this->_cache->putData('COLLECTED_TASK_FOR_MARKDOWN', $resultList);
		$this->_cache->putData('ELAPSED_SECONDS', $elapsedSecs);
	}

	public function getGeneratedMarkdownContent() {

		$members = $this->_cache->getData('members');
		$COLLECTED_TASK_FOR_MARKDOWN = $this->_cache->getData('COLLECTED_TASK_FOR_MARKDOWN');
		$ELAPSED_SECONDS = round($this->_cache->getData('ELAPSED_SECONDS'),2);

		$mdRows = [];
		
		$mdRows[] = '# 업무 진행 현황';

		$mdRows[] = '* 집계 기간: '.$this->_cache->getData('SEARCH_BEGIN_DATE').' ~ '.$this->_cache->getData('SEARCH_DUE_DATE');
		$mdRows[] = '* 집계 소요 시간: '.$ELAPSED_SECONDS.'초';

		foreach ($members as $member) {

			$mdRows[] = '## '.$member->name;
			$prevProjectName = "";

			if (sizeof($COLLECTED_TASK_FOR_MARKDOWN[$member->id]) > 0) {
				foreach ($COLLECTED_TASK_FOR_MARKDOWN[$member->id] as $task) {
					list($projectName, $taskNumber, $workflowName, $subject, $url, $delayDays, $projectId, $taskId) = $task;

					if ($this->_cache->getData('CLEANUP_MODE')
						&& $delayDays >= $this->_cache->getData('CLEANUP_DELAY_DAYS_AFTER_REGISTRATION')) {
						$this->_projectApi->setPostDone($projectId, $taskId);

						if (!empty($this->_cache->getData('CLEANUP_LOG_MESSAGE'))) {
							$this->_projectApi->postLog($projectId, $taskId, $this->_cache->getData('CLEANUP_LOG_MESSAGE'));
						}

						echo '[Clean-up Task] '.$url.PHP_EOL;
						continue;
					}

					if ($prevProjectName != $projectName) {
						$mdRows[] = '### '.$projectName;
						$mdRows[] = '';
						$mdRows[] = '| - | 현재 상태 | 업무 번호 | 업무 | 지연일수 |';
						$mdRows[] = '| --- | --- | --- | --- | --- |';
					}

					$mdRows[] = '| '.implode(' | ', [
														'<ul><li class="task-list-item" data-te-task=""></li></ul>',
														$workflowName,
														$taskNumber,
														'['.$subject.']('.$url.')',
														($delayDays > 0 ? $delayDays.'일' : '-')
													]);

					$prevProjectName = $projectName;
				}
			} else {
				$mdRows[] = '- 등록/진행 업무 없음';
			}

			$mdRows[] = '';
		}

		return implode(PHP_EOL, $mdRows);
	}

	public function uploadSummaryTaskAndNotifyMessenger() {
		
		$TENANT_ID = $this->_cache->getData('TENANT_ID');
		$NOTIFY_TARGET_PROJECT_ID = $this->_cache->getData('NOTIFY_TARGET_PROJECT_ID');

		if (empty($NOTIFY_TARGET_PROJECT_ID)) {
			return ;
		}

		$members = $this->_cache->getData('members');
		$subject = str_replace('{today}', date('Y/m/d'), $this->_cache->getData('NOTIFY_TASK_SUBJECT'));

		$bodyContent = $this->getGeneratedMarkdownContent();

		$toOrganizationMemberIds = [];

		foreach ($members as $member) {
			$toOrganizationMemberIds[] = ["type"=>"member", "member" => ["organizationMemberId" => $member->id] ];
		}

		$result = $this->_projectApi->postTask($NOTIFY_TARGET_PROJECT_ID,
											$subject, 
											'text/x-markdown',
											$bodyContent,
											$toOrganizationMemberIds);

		$uploadedTaskId = $result->id;
		$taskUrl = DoorayDataUtility::getDoorayTaskUrl($TENANT_ID, $uploadedTaskId);

		if (!empty($taskUrl)) {
			$this->sendNotifyMessage($taskUrl);
		}
	}

	private function sendNotifyMessage($taskUrl) {		
		$hookUrl = $this->_cache->getData('NOTIFY_MESSENGER_HOOK_URL');
		$message = $this->_cache->getData('NOTIFY_MESSENGER_TEXT');

		$message = str_replace('{url}', $taskUrl, $message);

		DoorayMessengerHelper::sendMessage($hookUrl, $message);
	}

	private function __getTime() {
		$t = explode(' ',microtime());
		return (float)$t[0] + (float)$t[1];
	}
}
