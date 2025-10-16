<?php

namespace Hamaka\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\UserForms\Model\UserDefinedForm;

class UserFormsCleanupOldEntriesTask extends BuildTask
{
    protected $title = "UserForms Clean-up SubmittedForm task";

    protected $description = "Removes old userdata for privacy reasons based on per-form retention policies";

    private static $segment = 'userforms-cleanup';

    // Fallback for forms without a retention policy set
    private static $days_retention = 31;

    public function run($request)
    {
        DB::alteration_message('Starting UserForms cleanup task...');
        DB::alteration_message('Total entries in database (before cleanup): ' . SubmittedForm::get()->count());

        $totalCleared = 0;

        // Get all UserDefinedForms with retention policies
        $forms = UserDefinedForm::get()
            ->filter('SubmissionRetentionDays:GreaterThan', 0)
            ->exclude('SubmissionRetentionDays', null);

        if ($forms->count() === 0) {
            DB::alteration_message('No forms with retention policies found.');
        }

        foreach ($forms as $form) {
            $thresholdDate = $form->getSubmissionThresholdDate();

            if (!$thresholdDate) {
                DB::alteration_message(sprintf(
                    'Form "%s" (ID: %d) has retention policy set to "Never delete" - skipping',
                    $form->Title,
                    $form->ID
                ));
                continue;
            }

            DB::alteration_message(sprintf(
                'Processing form "%s" (ID: %d) - removing entries before %s',
                $form->Title,
                $form->ID,
                $thresholdDate
            ));

            $cleared = $this->cleanUpUserFormSubmissions($form->ID, $thresholdDate);
            $totalCleared += $cleared;

            if ($cleared > 0) {
                DB::alteration_message(sprintf(
                    '  → Deleted %d entries',
                    $cleared
                ));
            } else {
                DB::alteration_message('  → No entries to delete');
            }
        }

        DB::alteration_message('');
        DB::alteration_message('=======================================');
        DB::alteration_message('Total entries deleted: ' . $totalCleared);
        DB::alteration_message('Total entries remaining: ' . SubmittedForm::get()->count());
        DB::alteration_message('Done.');
    }

    /**
     * Remove submissions for a specific form before a given date
     *
     * @param int    $formID The UserDefinedForm ID
     * @param string $beforeDate Date in format Y-m-d H:i:s
     *
     * @return int Number of entries cleared
     */
    private function cleanUpUserFormSubmissions(int $formID, string $beforeDate): int
    {
        $submissions = SubmittedForm::get()
            ->filter([
                'ParentID' => $formID,
                'Created:LessThanOrEqual' => $beforeDate,
            ]);

        $count = $submissions->count();
        $submissions->removeAll();

        return $count;
    }

    /**
     * Legacy method for backward compatibility
     * Uses global fallback days_retention setting
     *
     * @param string $beforeDate Date in format Y-m-d H:i:s
     *
     * @return int
     */
    public static function cleanUpUserForms(string $beforeDate): int
    {
        $submissions = SubmittedForm::get()
            ->filter('Created:LessThanOrEqual', $beforeDate);

        $count = $submissions->count();
        $submissions->removeAll();

        return $count;
    }
}
