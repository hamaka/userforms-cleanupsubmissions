# Silverstripe UserForms: clean up form submissions

Simple task to automatically cleanup userdata

## Installation
To install run `composer require hamaka/userforms-cleanupsubmissions`.

## Requirements

* Silverstripe ^5.0
* UserForms ^6

**Note:** For Silverstripe 3, please use the [3.x release line](https://github.com/hamaka/userforms-cleanupsubmissions/tree/3), for Silverstripe 4, please use the [4.x release line](https://github.com/hamaka/userforms-cleanupsubmissions/tree/4).

## Features

- **Per-form retention policies**: Set individual retention periods for each UserDefinedForm
- **Elemental support**: Configure retention policies for both UserDefinedForm and Elemental UserForm elements
- **Flexible retention options**: Choose from predefined periods (1 day to 6 months) or set to "Never delete"
- **i18n support**: Built-in translations for English and Dutch
- **Configurable options**: Override retention options per project

## Configuration

### Global retention period (fallback)

By default, the module will clean up entries older than 31 days. You can change this fallback via yml:

```yaml
Hamaka\Tasks\UserFormsCleanupOldEntriesTask:
  days_retention: 90
```

This is used as a fallback for submissions that have no associated form retention policy.

### Per-form retention policies (recommended)

Since version 5.1, you can set individual retention policies per form. This is the recommended approach.

#### For UserDefinedForm

Edit any UserDefinedForm in the CMS and navigate to the **Settings** tab. You'll find a **Submission Retention Policy** dropdown where you can select:

- 1 day
- 2 days
- 1 week
- 2 weeks
- 1 month
- 2 months
- 6 months
- Never delete (default)

#### For Elemental UserForm elements

The same retention policy field is available when editing Elemental UserForm elements.

### Customizing retention options

You can customize the available retention options in your project's `_config.yml`:

```yaml
Hamaka\UserForms\Model\UserFormRetentionExtension:
  # Override this in your project's _config.yml to customize
  retention_options:
    1: '1 day'
    2: '2 days'
    7: '1 week'
    14: '2 weeks'
    31: '1 month'
    62: '2 months'
    182: '6 months'
    0: 'Never delete'

```

## Running the cleanup task

The cleanup task can be run manually via the command line:

```
sake dev/tasks/userforms-cleanup
```

Or you can set it up to run automatically via a cron job:

```bash
*/1 * * * * cd /path/to/project && sake dev/tasks/userforms-cleanup
```

## Translations

The module includes built-in translations for:

- English (en)
- Dutch (nl)

To add additional translations, create language files in your project and override the translation keys.
