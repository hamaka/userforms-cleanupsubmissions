<?php

namespace Hamaka\UserForms\Model;

use SilverStripe\Forms\DropdownField;
use SilverStripe\UserForms\Model\UserDefinedForm;
use SilverStripe\ORM\DataExtension;

class UserFormRetentionExtension extends DataExtension
{
    private static $db = [
        'SubmissionRetentionDays' => 'Int',
    ];

    /**
     * Retention policy options (days)
     * Override in your _config.yml to customize
     */
    private static $retention_options = [
        1 => 'Hamaka\\UserForms\\Model.RETENTION_1_DAY',
        2 => 'Hamaka\\UserForms\\Model.RETENTION_2_DAYS',
        7 => 'Hamaka\\UserForms\\Model.RETENTION_1_WEEK',
        14 => 'Hamaka\\UserForms\\Model.RETENTION_2_WEEKS',
        31 => 'Hamaka\\UserForms\\Model.RETENTION_1_MONTH',
        62 => 'Hamaka\\UserForms\\Model.RETENTION_2_MONTHS',
        182 => 'Hamaka\\UserForms\\Model.RETENTION_6_MONTHS',
        0 => 'Hamaka\\UserForms\\Model.RETENTION_NEVER',
    ];

    public function updateCMSFields(\SilverStripe\Forms\FieldList $fields)
    {
        $retentionOptions = $this->owner->config()->get('retention_options');

        // Translate the options
        $translatedOptions = [];

        foreach ($retentionOptions as $days => $translationKey) {
            switch ($days) {
                case 1:
                    $translatedOptions[$days] = _t($translationKey, '1 day');
                    break;
                case 2:
                    $translatedOptions[$days] = _t($translationKey, '2 days');
                    break;
                case 7:
                    $translatedOptions[$days] = _t($translationKey, '1 week');
                    break;
                case 14:
                    $translatedOptions[$days] = _t($translationKey, '2 weeks');
                    break;
                case 31:
                    $translatedOptions[$days] = _t($translationKey, '1 month');
                    break;
                case 62:
                    $translatedOptions[$days] = _t($translationKey, '2 months');
                    break;
                case 182:
                    $translatedOptions[$days] = _t($translationKey, '6 months');
                    break;
                case 0:
                    $translatedOptions[$days] = _t($translationKey, 'Never delete');
                    break;
                default:
                    $translatedOptions[$days] = $translationKey;
            }
        }

        $fields->addFieldToTab(
            'Root.FormOptions',
            DropdownField::create(
                'SubmissionRetentionDays',
                _t('Hamaka\\UserForms\\Model.SUBMISSION_RETENTION_POLICY', 'Submission Retention Policy'),
                $translatedOptions,
                $this->owner->SubmissionRetentionDays
            )->setEmptyString(_t('Hamaka\\UserForms\\Model.SELECT_RETENTION_POLICY', '-- Select a retention policy --')),
            'OnCompleteMessage'
        );
    }

    /**
     * Get the threshold date for this form's submissions
     */
    public function getSubmissionThresholdDate()
    {
        $retentionDays = $this->owner->SubmissionRetentionDays;

        if (!$retentionDays || $retentionDays === 0) {
            return null; // Never delete
        }

        $iThresholdDate = strtotime('-' . $retentionDays . ' days');
        return date('Y-m-d 00:00:00', $iThresholdDate);
    }
}
