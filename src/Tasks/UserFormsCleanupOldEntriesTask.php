<?php

    namespace Hamaka;

    use BuildTask;
    use Director;
    use SubmittedForm;

    class UserFormsCleanupOldEntriesTask extends BuildTask
    {

        protected $title = "UserForms Clean-up SubmittedForm task";

        protected $description = "Removes old userdata for privacy reasons";

        private static $segment = 'userforms-cleanup';

        private static $days_retention = 31;

        public function run($request)
        {
            $aFeedback      = array();
            $iThresholdDate = strtotime('-' . $this->config()->get('days_retention') . ' days');
            $sThresholdDate = date('Y-m-d 00:00:00', $iThresholdDate);
            $aFeedback []   = 'Removing all entries before ' . $sThresholdDate;

            $aFeedback [] = 'Total entries in database (before cleanup): ' . SubmittedForm::get()->count();

            $iClearedEntries = $this->cleanUpUserForms($sThresholdDate);
            $aFeedback []    = 'Total entries to be deleted: ' . $iClearedEntries;

            $aFeedback [] = "Done, total entries after cleanup: " . SubmittedForm::get()->count();

            if (Director::is_cli()) {
                $sFeedback = implode(PHP_EOL, $aFeedback);
            }
            else {
                $sSep      = PHP_EOL . '<br>&bull; ';
                $sFeedback = $sSep . implode($sSep, $aFeedback);
            }

            echo $sFeedback;
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
