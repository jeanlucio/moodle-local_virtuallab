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
 * Notification service — lifecycle warning and summary emails.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual\local;

use local_labvirtual\task\maintenance_task;

/**
 * Sends lifecycle-related emails: pre-action warnings to teachers and
 * post-action summaries to teachers and the site administrator.
 *
 * All recipient lookups are performed in bulk to avoid per-lab database calls.
 */
class notification_service {
    /**
     * Sends warning emails for labs approaching the lifecycle cutoff.
     *
     * Labs are grouped by batch so each responsible teacher receives a single
     * email listing all of their affected labs. The caller is responsible for
     * updating the lastwarn timestamp on the warned labs.
     *
     * @param \stdClass[] $labs       Lab rows (from local_labvirtual_courses) due for a warning.
     * @param int         $action     Lifecycle action setting (1 = reset, 2 = delete).
     * @param \DateTime   $cutoffdate Date when the action will be performed.
     */
    public function send_warnings(array $labs, int $action, \DateTime $cutoffdate): void {
        if (empty($labs)) {
            return;
        }

        $bybatch = [];
        foreach ($labs as $lab) {
            $bybatch[$lab->batchid][] = $lab;
        }

        $teachers    = $this->get_teachers_by_batch(array_keys($bybatch));
        $coursenames = $this->get_course_names($labs);
        $actionlabel = $this->action_label($action);
        $datestr     = userdate($cutoffdate->getTimestamp(), get_string('strftimedate', 'langconfig'));
        $days        = max(0, (int) ceil(($cutoffdate->getTimestamp() - time()) / DAYSECS));
        $from        = \core_user::get_noreply_user();

        foreach ($bybatch as $batchid => $batchlabs) {
            if (empty($teachers[$batchid])) {
                continue;
            }

            $teacher = $teachers[$batchid];
            $subject = get_string('email_warning_subject', 'local_labvirtual', (object) [
                'action' => $actionlabel,
                'days'   => $days,
            ]);

            $intro = get_string('email_warning_body', 'local_labvirtual', (object) [
                'action' => $actionlabel,
                'date'   => $datestr,
            ]);

            $items = [];
            foreach ($batchlabs as $lab) {
                $items[] = $coursenames[$lab->courseid] ?? ('#' . $lab->courseid);
            }

            $html = \html_writer::tag('p', s($intro));
            $html .= $this->render_list($items);

            $text = html_to_text($html);

            email_to_user($teacher, $from, $subject, $text, $html);
        }
    }

    /**
     * Sends post-action summary emails: one per teacher (their own labs) and a
     * consolidated email to the site administrator.
     *
     * @param array[] $results Each entry: ['lab' => \stdClass, 'name' => string,
     *                         'action' => 'reset'|'delete', 'ok' => bool].
     */
    public function send_summary(array $results): void {
        if (empty($results)) {
            return;
        }

        $bybatch = [];
        foreach ($results as $result) {
            $bybatch[$result['lab']->batchid][] = $result;
        }

        $teachers = $this->get_teachers_by_batch(array_keys($bybatch));
        $from     = \core_user::get_noreply_user();
        $subject  = get_string('email_summary_subject', 'local_labvirtual');
        $intro    = get_string('email_summary_body', 'local_labvirtual');

        foreach ($bybatch as $batchid => $batchresults) {
            if (empty($teachers[$batchid])) {
                continue;
            }

            $html = \html_writer::tag('p', s($intro));
            $html .= $this->render_list($this->summary_lines($batchresults));
            $text = html_to_text($html);

            email_to_user($teachers[$batchid], $from, $subject, $text, $html);
        }

        $admin = get_admin();
        if ($admin) {
            $html = \html_writer::tag('p', s($intro));
            $html .= $this->render_list($this->summary_lines($results));
            $text = html_to_text($html);

            email_to_user($admin, $from, $subject, $text, $html);
        }
    }

    /**
     * Returns the responsible teacher (full user record) for each batch ID in one query.
     *
     * @param int[] $batchids Batch IDs to resolve.
     * @return \stdClass[] Indexed by batch ID; each value is a user record (id = teacher).
     */
    private function get_teachers_by_batch(array $batchids): array {
        global $DB;

        if (empty($batchids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($batchids, SQL_PARAMS_NAMED);

        $sql = "SELECT b.id AS batchid, u.*, b.name AS batchname
                  FROM {local_labvirtual_batches} b
                  JOIN {user} u ON u.id = b.teacherid
                 WHERE b.id $insql
                   AND u.deleted = 0
                   AND u.suspended = 0";

        return $DB->get_records_sql($sql, $params);
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

        if (empty($courseids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_unique($courseids), SQL_PARAMS_NAMED);

        return $DB->get_records_select_menu('course', "id $insql", $params, '', 'id, fullname');
    }

    /**
     * Maps the lifecycle action integer to its localised verb.
     *
     * @param int $action Lifecycle action setting (1 = reset, 2 = delete).
     * @return string
     */
    private function action_label(int $action): string {
        $key = $action === maintenance_task::ACTION_DELETE ? 'email_action_delete' : 'email_action_reset';

        return get_string($key, 'local_labvirtual');
    }

    /**
     * Builds the per-lab summary lines including the action verb and outcome.
     *
     * @param array[] $results Summary result entries.
     * @return string[] One descriptive line per lab.
     */
    private function summary_lines(array $results): array {
        $lines = [];
        foreach ($results as $result) {
            $verb   = $result['action'] === 'delete'
                ? get_string('email_action_delete', 'local_labvirtual')
                : get_string('email_action_reset', 'local_labvirtual');
            $status = $result['ok']
                ? get_string('email_summary_ok', 'local_labvirtual')
                : get_string('email_summary_failed', 'local_labvirtual');
            $lines[] = "{$result['name']} — {$verb}: {$status}";
        }

        return $lines;
    }

    /**
     * Renders a list of plain-text items as an escaped HTML unordered list.
     *
     * @param string[] $items Items to render.
     * @return string HTML markup.
     */
    private function render_list(array $items): string {
        $listitems = '';
        foreach ($items as $item) {
            $listitems .= \html_writer::tag('li', s($item));
        }

        return \html_writer::tag('ul', $listitems);
    }
}
