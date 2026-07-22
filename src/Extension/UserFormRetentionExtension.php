<?php

    namespace Hamaka\UserForms\Model;

    use SilverStripe\Core\Config\Config;
    use SilverStripe\Core\Extension;
    use SilverStripe\Forms\DropdownField;
    use SilverStripe\Forms\FieldList;

    class UserFormRetentionExtension extends Extension
    {
        private static $db = [
            'SubmissionRetentionDays' => 'Int',
        ];

        /**
         * Retention policy options (days)
         * Override in your _config.yml to customize
         */
        private static $retention_options = [
            1   => 'Hamaka\\UserForms\\Model.RETENTION_1_DAY',
            2   => 'Hamaka\\UserForms\\Model.RETENTION_2_DAYS',
            7   => 'Hamaka\\UserForms\\Model.RETENTION_1_WEEK',
            14  => 'Hamaka\\UserForms\\Model.RETENTION_2_WEEKS',
            31  => 'Hamaka\\UserForms\\Model.RETENTION_1_MONTH',
            62  => 'Hamaka\\UserForms\\Model.RETENTION_2_MONTHS',
            182 => 'Hamaka\\UserForms\\Model.RETENTION_6_MONTHS',
            -1   => 'Hamaka\\UserForms\\Model.RETENTION_NEVER',
        ];

        public function updateCMSFields(FieldList $fields)
        {
            $retentionOptions = $this->owner->config()->get('retention_options');

            // Translate the options
            $translatedOptions = [];
            foreach ($retentionOptions as $days => $translationKey) {
                $translatedOptions[$days] = _t($translationKey, $translationKey);
            }

            $fields->addFieldToTab(
                'Root.FormOptions',
                DropdownField::create(
                    'SubmissionRetentionDays',
                    _t('Hamaka\\UserForms\\Model.SUBMISSION_RETENTION_POLICY', 'Submission Retention Policy'),
                    $translatedOptions,
                    $this->owner->SubmissionRetentionDays
                )->setEmptyString(_t('Hamaka\\UserForms\\Model.SELECT_RETENTION_POLICY', '-- Select a retention policy --')),
                'DisableSaveSubmissions'
            );
        }

        /**
         * Get the threshold date for this form's submissions
         * Returns null if retention is set to "never delete" (-1)
         * Returns date string for all other cases
         */
        public function getSubmissionThresholdDate()
        {
            $retentionDays = $this->owner->SubmissionRetentionDays;

            // -1 means never delete
            if ($retentionDays === -1 || $retentionDays === '-1') {
                return null;
            }

            // 0 or empty means use default from config
            if ($retentionDays === 0 || $retentionDays === '0' || ! $retentionDays) {
                $retentionDays = (int)Config::inst()->get(\Hamaka\Tasks\UserFormsCleanupOldEntriesTask::class, 'days_retention');
            }

            $iThresholdDate = strtotime('-' . $retentionDays . ' days');

            return date('Y-m-d 00:00:00', $iThresholdDate);
        }

        /**
         * Get the effective retention days (resolves default)
         * Useful for displaying in logs/UI
         */
        public function getEffectiveRetentionDays()
        {
            $retentionDays = $this->owner->SubmissionRetentionDays;

            // -1 means never delete
            if ($retentionDays === -1 || $retentionDays === '-1') {
                return -1;
            }

            // 0 or empty means use default from config
            if ($retentionDays === 0 || $retentionDays === '0' || ! $retentionDays) {
                return (int)Config::inst()->get(\Hamaka\Tasks\UserFormsCleanupOldEntriesTask::class, 'days_retention');
            }

            return $retentionDays;
        }
    }
