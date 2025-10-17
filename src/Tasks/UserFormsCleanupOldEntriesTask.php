<?php

    namespace Hamaka\Tasks;

    use SilverStripe\Core\Config\Config;
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

            // Handle normal UserDefinedForms with explicit retention
            $forms = UserDefinedForm::get()
                                    ->filter('SubmissionRetentionDays:GreaterThan', 0)
                                    ->exclude('SubmissionRetentionDays', null);

            if ($forms->count() === 0) {
                DB::alteration_message('No regular forms with retention policies found.');
            }
            else {
                DB::alteration_message('Found ' . $forms->count() . ' regular forms with retention policies');
            }

            foreach ($forms as $form) {
                $thresholdDate = $form->getSubmissionThresholdDate();

                if ( ! $thresholdDate) {
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

                $cleared      = $this->cleanUpUserFormSubmissions($form->ID, $thresholdDate);
                $totalCleared += $cleared;

                DB::alteration_message($cleared > 0
                    ? sprintf('  → Deleted %d entries', $cleared)
                    : '  → No entries to delete'
                );
            }

            // Handle Elemental Forms
            if (class_exists('DNADesign\Elemental\Models\BaseElement')) {
                $elementalCleared = $this->cleanUpElementalForms();
                $totalCleared     += $elementalCleared;
            }

            // LEGACY fallback cleanup for all old submissions without any retention policy
            $legacyCleared = self::cleanUpUserForms();
            if ($legacyCleared > 0) {
                DB::alteration_message('');
                DB::alteration_message(sprintf('Legacy cleanup removed %d old submissions (fallback policy)', $legacyCleared));
            }

            $totalCleared += $legacyCleared;

            DB::alteration_message('');
            DB::alteration_message('=======================================');
            DB::alteration_message('Total entries deleted: ' . $totalCleared);
            DB::alteration_message('Total entries remaining: ' . SubmittedForm::get()->count());
            DB::alteration_message('Done.');
        }

        /**
         * Clean up submissions for Elemental UserForm blocks
         */
        private function cleanUpElementalForms(): int
        {
            $totalCleared = 0;
            $tables       = DB::table_list();

            if ( ! isset($tables['elementform'])) {
                DB::alteration_message('ElementForm table not found.');

                return 0;
            }

            $columns = DB::field_list('elementform');
            if ( ! isset($columns['SubmissionRetentionDays'])) {
                DB::alteration_message('SubmissionRetentionDays column not found on ElementForm.');

                return 0;
            }

            try {
                $query = DB::query("
                SELECT DISTINCT ef.ID, ef.SubmissionRetentionDays
                FROM ElementForm ef
                WHERE ef.SubmissionRetentionDays > 0
                AND ef.SubmissionRetentionDays IS NOT NULL
            ");

                if ( ! $query->numRecords()) {
                    DB::alteration_message('No elemental forms with retention policies found.');

                    return 0;
                }

                DB::alteration_message('Found ' . $query->numRecords() . ' elemental forms with retention policies');

                foreach ($query as $record) {
                    $elementID     = $record['ID'];
                    $retentionDays = $record['SubmissionRetentionDays'];
                    $thresholdDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

                    DB::alteration_message(sprintf(
                        'Processing elemental form (ID: %d) - removing entries before %s',
                        $elementID,
                        $thresholdDate
                    ));

                    $submissions = SubmittedForm::get()
                                                ->filter([
                                                    'ParentID'                => $elementID,
                                                    'Created:LessThanOrEqual' => $thresholdDate,
                                                ]);

                    $cleared = $submissions->count();

                    if ($cleared > 0) {
                        $submissions->removeAll();
                        $totalCleared += $cleared;
                        DB::alteration_message(sprintf('  → Deleted %d entries', $cleared));
                    }
                    else {
                        DB::alteration_message('  → No entries to delete');
                    }
                }
            }
            catch (\Exception $e) {
                DB::alteration_message('Error processing elemental forms: ' . $e->getMessage());
            }

            return $totalCleared;
        }

        /**
         * Remove submissions for a specific form before a given date
         */
        private function cleanUpUserFormSubmissions(int $formID, string $beforeDate): int
        {
            $submissions = SubmittedForm::get()
                                        ->filter([
                                            'ParentID'                => $formID,
                                            'Created:LessThanOrEqual' => $beforeDate,
                                        ]);

            $count = $submissions->count();

            if ($count > 0) {
                $submissions->removeAll();
            }

            return $count;
        }

        /**
         * Legacy fallback cleanup for *all* submissions older than the global retention period
         */
        public static function cleanUpUserForms(?string $beforeDate = null): int
        {
            // ✅ Gebruik Config om de YAML-waarde op te halen
            $days = (int)Config::inst()->get(__CLASS__, 'days_retention') ?: static::$days_retention;

            $thresholdDate = $beforeDate ?: date('Y-m-d H:i:s', strtotime("-{$days} days"));

            DB::alteration_message(sprintf(
                'Running legacy cleanup for all submissions older than %d days (%s)',
                $days,
                $thresholdDate
            ));

            $submissions = SubmittedForm::get()
                                        ->filter('Created:LessThanOrEqual', $thresholdDate);

            $count = $submissions->count();

            if ($count > 0) {
                $submissions->removeAll();
            }

            return $count;
        }
    }
