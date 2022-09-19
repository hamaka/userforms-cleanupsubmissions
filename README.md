# Silversripe Userforms: clean up form submissions

Simple module to automatically cleanup userdata

## Requirements

* Silverstripe ^4.0
* UserForms ^5

## Installation
```
composer require hamaka/userforms-cleanupsubmissions
```

## Retention period
By default, the module will clean up entries older then 30 days.
You can change the number of days you want to keep data stored via yml:

```yaml

Hamaka\Tasks\UserFormsCleanupOldEntriesTask:
  days_retention: 30

```
