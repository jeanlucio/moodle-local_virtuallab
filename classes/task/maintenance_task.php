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
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual\task;

use local_labvirtual\local\maintenance_service;

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
        return get_string('task_maintenance', 'local_labvirtual');
    }

    /**
     * Executes the lifecycle maintenance task.
     *
     * Reads lifecycle_months and lifecycle_action from plugin config.
     * Processes each overdue lab individually so a single failure does not
     * block the rest of the batch.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $months = (int) get_config('local_labvirtual', 'lifecycle_months');
        $action = (int) get_config('local_labvirtual', 'lifecycle_action');

        if ($months === 0 || $action === 0) {
            mtrace('Lab Virtual maintenance: disabled (lifecycle_months or lifecycle_action is 0).');
            return;
        }

        $cutoff = mktime(0, 0, 0, (int)date('n') - $months, (int)date('j'), (int)date('Y'));

        $labs = $DB->get_records_sql(
            "SELECT lc.*
               FROM {local_labvirtual_courses} lc
              WHERE (lc.lastreset > 0 AND lc.lastreset < :cutoff1)
                 OR (lc.lastreset = 0 AND lc.timecreated < :cutoff2)",
            ['cutoff1' => $cutoff, 'cutoff2' => $cutoff]
        );

        if (empty($labs)) {
            mtrace('Lab Virtual maintenance: no labs due for action.');
            return;
        }

        $label = $action === self::ACTION_RESET ? 'reset' : 'delete';
        mtrace('Lab Virtual maintenance: ' . count($labs) . " lab(s) due for {$label}.");

        $service = new maintenance_service();
        $success = 0;
        $errors  = 0;

        // Delete and reset are per-course Moodle API calls;
        // batch equivalents do not exist, so the loop is unavoidable.
        foreach ($labs as $lab) {
            try {
                if ($action === self::ACTION_RESET) {
                    $service->reset_lab($lab->id, $lab->batchid);
                    mtrace("  Reset lab {$lab->id} (course {$lab->courseid}) — OK.");
                } else if ($action === self::ACTION_DELETE) {
                    $service->delete_lab($lab->id, $lab->batchid);
                    mtrace("  Deleted lab {$lab->id} (course {$lab->courseid}) — OK.");
                }
                $success++;
            } catch (\Throwable $e) {
                mtrace("  Error on lab {$lab->id}: " . $e->getMessage());
                $errors++;
            }
        }

        mtrace("Lab Virtual maintenance: done. {$success} succeeded, {$errors} failed.");
    }
}
