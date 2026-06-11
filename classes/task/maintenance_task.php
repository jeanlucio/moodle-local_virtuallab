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
use local_labvirtual\local\notification_service;

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

        $months      = (int) get_config('local_labvirtual', 'lifecycle_months');
        $action      = (int) get_config('local_labvirtual', 'lifecycle_action');
        $warningdays = (int) get_config('local_labvirtual', 'warning_days_before');

        if ($months === 0 || $action === 0) {
            mtrace('Lab Virtual maintenance: disabled (lifecycle_months or lifecycle_action is 0).');
            return;
        }

        $cutoff = mktime(0, 0, 0, (int)date('n') - $months, (int)date('j'), (int)date('Y'));

        // Phase 0 — pre-action warnings.
        if ($warningdays > 0) {
            $this->send_warnings($cutoff, $months, $warningdays, $action);
        }

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

        // Capture course names and editors before deletion so the summary can list deleted
        // labs and notify their editors (enrolments disappear when a course is deleted).
        $coursenames     = $this->get_course_names($labs);
        $notification    = new notification_service();
        $editorsbycourse = $notification->get_course_editors(array_map(fn($lab) => $lab->courseid, $labs));

        $service = new maintenance_service();
        $results = [];
        $success = 0;
        $errors  = 0;

        // Delete and reset are per-course Moodle API calls;
        // batch equivalents do not exist, so the loop is unavoidable.
        foreach ($labs as $lab) {
            $name = $coursenames[$lab->courseid] ?? ('#' . $lab->courseid);
            $ok   = true;
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
                $ok = false;
            }
            $results[] = [
                'lab'     => $lab,
                'name'    => $name,
                'action'  => $label,
                'ok'      => $ok,
                'editors' => $editorsbycourse[$lab->courseid] ?? [],
            ];
        }

        // Phase 2 — post-action summary to teachers, course editors and (optionally) admin.
        if (!empty($results)) {
            $notification->send_summary($results);
        }

        mtrace("Lab Virtual maintenance: done. {$success} succeeded, {$errors} failed.");
    }

    /**
     * Phase 0: emails the responsible teacher about labs approaching the cutoff
     * and marks them so the warning is not sent again in the same cycle.
     *
     * @param int $cutoff      Timestamp threshold for the action (labs older than this are due).
     * @param int $months      Configured lifecycle length in months.
     * @param int $warningdays Number of days before the action to warn.
     * @param int $action      Lifecycle action setting (1 = reset, 2 = delete).
     */
    private function send_warnings(int $cutoff, int $months, int $warningdays, int $action): void {
        global $DB;

        // Labs whose reference date lands in [cutoff, cutoff + warningdays): due within the window, not yet overdue.
        $warnend = mktime(0, 0, 0, (int)date('n') - $months, (int)date('j') + $warningdays, (int)date('Y'));

        $warninglabs = $DB->get_records_sql(
            "SELECT lc.*
               FROM {local_labvirtual_courses} lc
              WHERE lc.lastwarn = 0
                AND ((lc.lastreset > 0 AND lc.lastreset >= :cutoff1 AND lc.lastreset < :warnend1)
                  OR (lc.lastreset = 0 AND lc.timecreated >= :cutoff2 AND lc.timecreated < :warnend2))",
            ['cutoff1' => $cutoff, 'warnend1' => $warnend, 'cutoff2' => $cutoff, 'warnend2' => $warnend]
        );

        if (empty($warninglabs)) {
            return;
        }

        mtrace('Lab Virtual maintenance: ' . count($warninglabs) . ' lab(s) warned.');

        // All warned labs are actioned within warningdays; show that deadline to the teacher.
        $deadline = time() + $warningdays * DAYSECS;
        (new notification_service())->send_warnings($warninglabs, $action, new \DateTime("@{$deadline}"));

        [$insql, $params] = $DB->get_in_or_equal(array_keys($warninglabs), SQL_PARAMS_NAMED);
        $DB->set_field_select('local_labvirtual_courses', 'lastwarn', time(), "id $insql", $params);
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
