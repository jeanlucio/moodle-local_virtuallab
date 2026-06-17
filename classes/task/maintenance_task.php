<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Scheduled task: automatic lifecycle management of lab courses.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\task;

use local_virtuallab\local\batch_settings;
use local_virtuallab\local\lifecycle;
use local_virtuallab\local\maintenance_service;
use local_virtuallab\local\notification_service;

/**
 * Resets or deletes labs whose last-reset (or creation) date exceeds
 * the configured lifecycle_months threshold.
 *
 * Runs nightly at 02:00 by default. Does nothing when lifecycle_months = 0
 * or lifecycle_action = 0 (both disable the task without unregistering it).
 */
class maintenance_task extends \core\task\scheduled_task {
    /** @var int Config value for the reset action. */
    const ACTION_RESET = 1;

    /** @var int Config value for the delete action. */
    const ACTION_DELETE = 2;

    /**
     * Returns the task display name shown in the admin scheduled-tasks UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_maintenance', 'local_virtuallab');
    }

    /**
     * Executes the lifecycle maintenance task.
     *
     * Each batch is processed with its own effective settings (per-batch override or
     * global default). Labs are processed individually so a single failure does not
     * block the rest.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $batches = $DB->get_records('local_virtuallab_batches');
        $alllabs = $batches ? $DB->get_records('local_virtuallab_courses') : [];

        if (empty($alllabs)) {
            mtrace('Lab Virtual maintenance: no labs to process.');
            return;
        }

        $labsbybatch = [];
        foreach ($alllabs as $lab) {
            $labsbybatch[$lab->batchid][] = $lab;
        }

        // Capture names and editors for every lab up front, before any deletion removes them.
        $notification    = new notification_service();
        $coursenames     = $this->get_course_names($alllabs);
        $editorsbycourse = $notification->get_course_editors(array_map(fn($lab) => $lab->courseid, $alllabs));

        $service   = new maintenance_service();
        $results   = [];
        $warnedids = [];
        $success   = 0;
        $errors    = 0;

        foreach ($batches as $batch) {
            $settings    = batch_settings::effective($batch);
            $months      = $settings->lifecyclemonths;
            $action      = $settings->lifecycleaction;
            $warningdays = $settings->warningdays;

            if ($months === 0 || $action === 0) {
                continue;
            }

            $cutoff  = mktime(0, 0, 0, (int)date('n') - $months, (int)date('j'), (int)date('Y'));
            $warnend = mktime(0, 0, 0, (int)date('n') - $months, (int)date('j') + $warningdays, (int)date('Y'));
            $label   = $action === self::ACTION_RESET ? 'reset' : 'delete';

            $warnlabs = [];

            foreach ($labsbybatch[$batch->id] ?? [] as $lab) {
                $ref = lifecycle::reference($batch, $lab);

                if ($ref < $cutoff) {
                    $name = $coursenames[$lab->courseid] ?? ('#' . $lab->courseid);
                    $ok   = true;
                    try {
                        if ($action === self::ACTION_RESET) {
                            $service->reset_lab($lab->id, $lab->batchid);
                        } else {
                            $service->delete_lab($lab->id, $lab->batchid);
                        }
                        $success++;
                    } catch (\Throwable $e) {
                        mtrace("  Error on lab {$lab->id}: " . $e->getMessage());
                        $errors++;
                        $ok = false;
                    }
                    $results[] = [
                        'lab'     => $lab,
                        'name'    => $name,
                        'action'  => $label,
                        'ok'      => $ok,
                        'editors' => $editorsbycourse[$lab->courseid] ?? [],
                    ];
                } else if ($warningdays > 0 && (int) $lab->lastwarn === 0 && $ref >= $cutoff && $ref < $warnend) {
                    $warnlabs[] = $lab;
                }
            }

            if ($warnlabs) {
                $deadline = time() + $warningdays * DAYSECS;
                $notification->send_warnings($warnlabs, $action, new \DateTime("@{$deadline}"));
                foreach ($warnlabs as $warnlab) {
                    $warnedids[] = $warnlab->id;
                }
            }
        }

        if ($warnedids) {
            [$insql, $params] = $DB->get_in_or_equal($warnedids, SQL_PARAMS_NAMED);
            $DB->set_field_select('local_virtuallab_courses', 'lastwarn', time(), "id $insql", $params);
        }

        if ($results) {
            $notification->send_summary($results);
        }

        mtrace("Lab Virtual maintenance: done. {$success} succeeded, {$errors} failed.");
    }

    /**
     * Returns a map of course ID to course full name for the given labs in one query.
     *
     * @param \stdClass[] $labs Lab rows with a courseid property.
     * @return string[] Indexed by course ID.
     */
    private function get_course_names(array $labs): array {
        global $DB;

        $courseids = [];
        foreach ($labs as $lab) {
            $courseids[] = $lab->courseid;
        }

        [$insql, $params] = $DB->get_in_or_equal(array_unique($courseids), SQL_PARAMS_NAMED);

        return $DB->get_records_select_menu('course', "id $insql", $params, '', 'id, fullname');
    }
}
