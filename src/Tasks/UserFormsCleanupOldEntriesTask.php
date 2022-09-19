<?php
    namespace Hamaka\Tasks;
    use SilverStripe\Core\Config\Configurable;

    class UserFormsCleanupOldEntriesTask extends BuildTask
    {
        use Configurable;

        protected $title = "UserForms Clean-up old SubmittedForms task";

        protected $description = "Removes old user data for privacy reasons";

        private static $days_retention = 30;

        public function run($request)
        {

            $aFeedback   = [];
            $iThresholdDate = strtotime('-' . $this->config()->get('days_retention') . ' days');
            $sThresholdDate = date('Y-m-d 00:00:00', $iThresholdDate);
            $aFeedback[]    = 'Removing all entries before ' . $sThresholdDate;

            $aFeedback[] = 'Total entries in database (before cleanup): ' . SubmittedForm::get()->count();

            $iClearedEntries = $this->cleanUpUserForms($sThresholdDate);
            $aFeedback[]     = 'Total entries to be deleted: ' . $iClearedEntries;

            $aFeedback[] = "Done, total entries after cleanup: " . SubmittedForm::get()->count();

            if (Director::is_cli()) {
                $sFeedback = implode(PHP_EOL, $aFeedback);
            }
            else {
                $sSep = PHP_EOL . '<br>&bull; ';
                $sFeedback = $sSep . implode($sSep, $aFeedback);
            }

            echo $sFeedback;
        }

        private static function cleanUpUserForms($sBeforeDate)
        {
            $dlSubmissions     = SubmittedForm::get()->filter('Created:LessThanOrEqual', $sBeforeDate);
            $iTotalToBeCleared = $dlSubmissions->count();
            $dlSubmissions->removeAll();

            return $iTotalToBeCleared;
        }
    }
