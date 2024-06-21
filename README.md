# Silverstripe UserForms: clean up form submissions

Simple task to automatically cleanup userdata

## Installation
To install run `composer require hamaka/userforms-cleanupsubmissions`.

## Requirements

* Silverstripe ^5.0
* UserForms ^6

**Note:** For Silverstripe 3, please use the [3.x release line](https://github.com/hamaka/userforms-cleanupsubmissions/tree/3), for Silverstripe 4, please use the [4.x release line](https://github.com/hamaka/userforms-cleanupsubmissions/tree/4).

## Retention period
By default, the module will clean up entries older then 31 days.
You can change the number of days you want to keep data stored via yml:

```yaml

Hamaka\Tasks\UserFormsCleanupOldEntriesTask:
  days_retention: 90

```
