# team-task-collector
This project is collects the work status of team members. And make a list of the collected results, and register it as a new task.


## 💾 Install

This Sample Project can be used by using the package manager or downloading the source directly. However, we highly recommend using the package manager.
If you install it yourself, you will need to fix the "https://github.com/eric-hnnedu/php-dooray" dependency issue yourself.

## Via Package Manager
This SDK are registered in two package managers, composer. You can conveniently install it using the commands provided by the package manager. When using composer, be sure to use it in the environment PHP 5.6+ is installed.

### composer

```sh
$ composer create-project nhn-edu/team-task-collector example-app
```

## 🔨 Usage

A personal authentication token is required to use the SDK.
Follow the steps below to obtain a personal authentication token.


### Prepare

1. Log in to Dooray PC Web.
2. Click the cogwheel icon (내설정; My Settings) at the top-right of the screen.
3. Click the [설정; Settings] button.
4. Click the API menu and click the "개인 인증 토큰; Personal Authentication Token" menu.
5. Click the "인증 토큰 생성하기; Generate Authentication Token" button.
6. Fill in the "용도; Use" field with a suitable description and click the "생성하기; Create" button.
7. Click the "복사하기; Copy" button to copy the "인증 토큰; Authentication Token" to the clipboard.

### Fix configuration

Edit the `config.json` file:

```json
{
	"__COMMENT__$$1" : [
		"-- Enter your personal authentication token and tenant ID values below. --"
	],
	"AUTH_KEY" : "-- YOUR AUTH KEY HERE --",
	"TENANT_ID" : "-- YOUR TENANT DOMAIN HOST NAME --",


	"__COMMENT__$$2" : [
		"-- Enter the email address of the employee you want to monitor. --"
	],
	"EMPLOYEE_EMAILS" : [
		"employee1@your-company.com",
		"employee2@your-company.com",
		"employee3@your-company.com"
	],


	"__COMMENT__$$3" : [
		"-- Enter the ID value of the project where the job is registered. --",
		"Check the address bar of your web browser on your PC:",
		"    https://{tenant-ID}.dooray.com/project/{project-ID}"
	],
	"PROJECT_IDS" : [
		"1234567890123456789",
		"2345678901234567890"
	],


	"__COMMENT__$$4" : [
		"-- Enter the start/end date and time of the search period in ISO8601 format. --",
		"    eg. 2022-01-01T00:00:00+09:00"
	],
	"SEARCH_BEGIN_DATE" : "2022-01-01T00:00:00+00:00",
	"SEARCH_DUE_DATE" : "{today}+00:00",


	"__COMMENT__$$5" : [
		"-- [Additional Feature, Notification] --",
		"Enter the Dooray Messenger Hook URL below to receive notifications. (optional) --"
	],
	"NOTIFY_TARGET_PROJECT_ID" : "3456789012345678901",
	"NOTIFY_TASK_SUBJECT" : "[Summary] IAMSCHOOL Dev. Team {today}",

	"NOTIFY_MESSENGER_HOOK_URL" : "https://hook.dooray.com/services/2049115262726134450/3205835367300737562/9fVoDjpsSw6wbm7Z1qBjHg",
	"NOTIFY_MESSENGER_TEXT" : "Team work status has been registered.\nCheck each one to see if there are any tasks that I have forgotten.\n\n{url}\n\nHave a good day!",

	"__COMMENT__$$6" : [
		"-- [Additional Feature, Clean-up Task] --",
		"If you turn on the option below, tasks that have been neglected for a long time are completed. --"
	],
	"CLEANUP_MODE" : true,
	"CLEANUP_DELAY_DAYS_AFTER_REGISTRATION" : 365,
	"CLEANUP_LOG_MESSAGE" : "This task has been neglected for more than `365 days`, so I changed the task status to Completed."
}
```

### Run

#### PHP Code

```php
<?php

require ('../vendor/autoload.php');

use NhnEdu\TeamTaskCollector\TaskCollector;

$collector = new TaskCollector('config.json');

$collector->collectTasks();

$collector->uploadSummaryTaskAndNotifyMessenger();
```