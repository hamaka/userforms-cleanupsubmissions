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

        public function run($request)
        {
            DB::alteration_message('Starting UserForms cleanup task...');
            DB::alteration_message('Total entries in database (before cleanup): ' . SubmittedForm::get()->count());

            $totalCleared = 0;

            // Handle normal UserDefinedForms
            $forms = UserDefinedForm::get();

            if ($forms->count() === 0) {
                DB::alteration_message('No regular forms found.');
            }
            else {
                DB::alteration_message('Found ' . $forms->count() . ' regular forms');
            }

            foreach ($forms as $form) {
                $thresholdDate = $form->getSubmissionThresholdDate();

                // null means never delete (-1)
                if ($thresholdDate === null) {
                    DB::alteration_message(sprintf(
                        'Form "%s" (ID: %d) has retention policy set to "Never delete" - skipping',
                        $form->Title,
                        $form->ID
                    ));
                    continue;
                }

                $effectiveDays = $form->getEffectiveRetentionDays();

                DB::alteration_message(sprintf(
                    'Processing form "%s" (ID: %d) - removing entries before %s (retention: %d days%s)',
                    $form->Title,
                    $form->ID,
                    $thresholdDate,
                    $effectiveDays,
                    ($form->SubmissionRetentionDays === 0 || ! $form->SubmissionRetentionDays) ? ' - default' : ''
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

            DB::alteration_message('');
            DB::alteration_message('=======================================');
            DB::alteration_message('Total entries deleted: ' . $totalCleared);
            DB::alteration_message('Total entries remaining: ' . SubmittedForm::get()->count());
            DB::alteration_message('Done.');
        }

        /**
         * Remove submissions for a specific form before a given date
         */
        private function cleanUpUserFormSubmissions(int $formID, string $beforeDate): int
        {
            // Get all submissions for this form
            $allSubmissions = SubmittedForm::get()
                                           ->filter([
                                               'ParentID' => $formID,
                                           ]);

            if ($allSubmissions->count() === 0) {
                return 0;
            }

            // Then filter those older than the threshold date
            $submissions = $allSubmissions->filter([
                'Created:LessThanOrEqual' => $beforeDate,
            ]);

            $count = $submissions->count();

            if ($count > 0) {
                $submissions->removeAll();
            }

            return $count;
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
                $defaultRetentionDays = (int)Config::inst()->get(self::class, 'days_retention') ?: 31;

                // Get ALL elemental forms
                $allForms = DB::query("
            SELECT DISTINCT ef.ID, ef.SubmissionRetentionDays
            FROM ElementForm ef
        ");

                if ( ! $allForms->numRecords()) {
                    DB::alteration_message('No elemental forms found.');

                    return 0;
                }

                DB::alteration_message('Found ' . $allForms->numRecords() . ' elemental forms');

                foreach ($allForms as $record) {
                    $elementID     = $record['ID'];
                    $retentionDays = $record['SubmissionRetentionDays'];

                    // Use default if not set or invalid (legacy support)
                    if ($retentionDays <= 0 || is_null($retentionDays)) {
                        $retentionDays = $defaultRetentionDays;
                        DB::alteration_message(sprintf(
                            'Elemental form (ID: %d) - using default retention of %d days',
                            $elementID,
                            $retentionDays
                        ));
                    }

                    // Skip if retention is -1 (never)
                    if ($retentionDays === -1) {
                        DB::alteration_message(sprintf(
                            'Skipping elemental form (ID: %d) - retention set to never',
                            $elementID
                        ));
                        continue;
                    }

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
    }
