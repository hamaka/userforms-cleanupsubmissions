# Silverstripe UserForms: clean up form submissions

Simple task to automatically cleanup userdata

## Requirements

* Silverstripe ^4.0
* UserForms ^5

## Installation
```
composer require hamaka/userforms-cleanupsubmissions
```

## Retention period
By default, the module will clean up entries older then 31 days.
You can change the number of days you want to keep data stored via yml:

```yaml

Hamaka\Tasks\UserFormsCleanupOldEntriesTask:
  days_retention: 90

```
