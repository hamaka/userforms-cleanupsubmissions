<?php

    namespace Hamaka\Tasks;

    use SilverStripe\Core\Config\Config;
    use SilverStripe\Dev\BuildTask;
    use SilverStripe\ORM\DB;
    use SilverStripe\PolyExecution\PolyOutput;
    use SilverStripe\UserForms\Model\Submission\SubmittedForm;
    use SilverStripe\UserForms\Model\UserDefinedForm;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputInterface;

    class UserFormsCleanupOldEntriesTask extends BuildTask
    {
        protected static string $commandName = 'userforms-cleanup';

        protected string $title = 'UserForms Clean-up SubmittedForm task';

        protected static string $description = "Removes old userdata for privacy reasons based on per-form retention policies";

        private static $days_retention = 31;

        private bool $hadErrors = false;

        protected function execute(InputInterface $input, PolyOutput $output): int
        {
            $output->writeln('Starting UserForms cleanup task...');
            $output->writeln('Total entries in database (before cleanup): ' . SubmittedForm::get()->count());

            $totalCleared = 0;

            // Handle normal UserDefinedForms
            $forms = UserDefinedForm::get();

            if ($forms->count() === 0) {
                $output->writeln('No regular forms found.');
            }
            else {
                $output->writeln('Found ' . $forms->count() . ' regular forms');
            }

            foreach ($forms as $form) {
                $thresholdDate = $form->getSubmissionThresholdDate();

                // null means never delete (-1)
                if ($thresholdDate === null) {
                    $output->writeln(sprintf(
                        'Form "%s" (ID: %d) has retention policy set to "Never delete" - skipping',
                        $form->Title,
                        $form->ID
                    ));
                    continue;
                }

                $effectiveDays = $form->getEffectiveRetentionDays();

                $output->writeln(sprintf(
                    'Processing form "%s" (ID: %d) - removing entries before %s (retention: %d days%s)',
                    $form->Title,
                    $form->ID,
                    $thresholdDate,
                    $effectiveDays,
                    ($form->SubmissionRetentionDays === 0 || ! $form->SubmissionRetentionDays) ? ' - default' : ''
                ));

                $cleared      = $this->cleanUpUserFormSubmissions($form->ID, $thresholdDate);
                $totalCleared += $cleared;

                $output->writeln($cleared > 0
                    ? sprintf('  → Deleted %d entries', $cleared)
                    : '  → No entries to delete'
                );
            }

            // Handle Elemental Forms
            if (class_exists('DNADesign\Elemental\Models\BaseElement')) {
                $elementalCleared = $this->cleanUpElementalForms($output);
                $totalCleared     += $elementalCleared;
            }

            $output->writeln('');
            $output->writeln('=======================================');
            $output->writeln('Total entries deleted: ' . $totalCleared);
            $output->writeln('Total entries remaining: ' . SubmittedForm::get()->count());
            $output->writeln('Done.');

            return $this->hadErrors ? Command::FAILURE : Command::SUCCESS;
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
        private function cleanUpElementalForms(PolyOutput $output): int
        {
            $totalCleared = 0;
            $tables       = DB::table_list();

            if ( ! isset($tables['elementform'])) {
                $output->writeln('ElementForm table not found.');

                return 0;
            }

            $columns = DB::field_list('ElementForm');
            if ( ! isset($columns['SubmissionRetentionDays'])) {
                $output->writeln('SubmissionRetentionDays column not found on ElementForm.');

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
                    $output->writeln('No elemental forms found.');

                    return 0;
                }

                $output->writeln('Found ' . $allForms->numRecords() . ' elemental forms');

                foreach ($allForms as $record) {
                    $elementID     = $record['ID'];
                    $retentionDays = $record['SubmissionRetentionDays'];

                    // Skip if retention is -1 (never)
                    if ($retentionDays === -1) {
                        $output->writeln(sprintf(
                            'Skipping elemental form (ID: %d) - retention set to never',
                            $elementID
                        ));
                        continue;
                    }

                    // Use default if not set or invalid (legacy support)
                    if ($retentionDays <= 0 || is_null($retentionDays)) {
                        $retentionDays = $defaultRetentionDays;
                        $output->writeln(sprintf(
                            'Elemental form (ID: %d) - using default retention of %d days',
                            $elementID,
                            $retentionDays
                        ));
                    }

                    $thresholdDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

                    $output->writeln(sprintf(
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
                        $output->writeln(sprintf('  → Deleted %d entries', $cleared));
                    }
                    else {
                        $output->writeln('  → No entries to delete');
                    }
                }
            }
            catch (\Exception $e) {
                $output->writeln('Error processing elemental forms: ' . $e->getMessage());
                $this->hadErrors = true;
            }

            return $totalCleared;
        }
    }
