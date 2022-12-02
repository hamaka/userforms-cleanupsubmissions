<?php

namespace Hamaka\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;

class UserFormsCleanupOldEntriesTask extends BuildTask
{

    protected $title = "UserForms Clean-up SubmittedForm task";

    protected $description = "Removes old userdata for privacy reasons";

    private static $segment = 'userforms-cleanup';

    private static $days_retention = 31;

    public function run($request)
    {
        $iThresholdDate = strtotime('-' . $this->config()->get('days_retention') . ' days');
        $sThresholdDate = date('Y-m-d 00:00:00', $iThresholdDate);
        DB::alteration_message('Removing all entries before ' . $sThresholdDate);

        DB::alteration_message('Total entries in database (before cleanup): ' . SubmittedForm::get()->count());

        $iClearedEntries = $this->cleanUpUserForms($sThresholdDate);
        DB::alteration_message('Total entries to be deleted: ' . $iClearedEntries);

        DB::alteration_message("Done, total entries after cleanup: " . SubmittedForm::get()->count());
    }

    /**
     * @param $sBeforeDate String Date written as Y-m-d h:m:s
     *
     * @return int
     */
    public static function cleanUpUserForms(string $sBeforeDate): int
    {
        $dlSubmissions     = SubmittedForm::get()->filter('Created:LessThanOrEqual', $sBeforeDate);
        $iTotalToBeCleared = $dlSubmissions->count();
        $dlSubmissions->removeAll();

        return $iTotalToBeCleared;
    }
}
