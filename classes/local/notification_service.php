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
 * Sends lifecycle-related emails: pre-action warnings and post-action summaries.
 *
 * Recipients are the responsible teachers of each batch, the editors of each affected
 * course, and — only when notify_admin_copy is enabled — a consolidated copy to the
 * administrator. All recipient lookups are performed in bulk to avoid per-lab queries.
 */
class notification_service {
    /**
     * Sends warning emails for labs approaching the lifecycle cutoff.
     *
     * Each responsible teacher gets one email per batch, each course editor gets one
     * email listing their own labs, and the admin optionally gets a consolidated copy.
     * The caller is responsible for updating the lastwarn timestamp on the warned labs.
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
            $html .= $this->link_paragraph(
                new \moodle_url('/local/labvirtual/view.php', ['batchid' => $batchid]),
                'email_panel_link'
            );

            $text = html_to_text($html);

            foreach ($teachers[$batchid] as $teacher) {
                email_to_user($teacher, $from, $subject, $text, $html);
            }
        }

        $this->send_warning_editors($labs, $coursenames, $actionlabel, $datestr, $days, $from);

        if ($this->notify_admin()) {
            $this->send_admin_copy(
                get_string('email_warning_subject', 'local_labvirtual', (object) [
                    'action' => $actionlabel,
                    'days'   => $days,
                ]),
                get_string('email_warning_body', 'local_labvirtual', (object) [
                    'action' => $actionlabel,
                    'date'   => $datestr,
                ]),
                array_map(fn($lab) => $coursenames[$lab->courseid] ?? ('#' . $lab->courseid), $labs),
                $from
            );
        }
    }

    /**
     * Sends warning emails to the editors of each affected course, one email per
     * editor listing all of their labs in the warning window.
     *
     * @param \stdClass[] $labs        Warned lab rows.
     * @param string[]    $coursenames Course full names indexed by course ID.
     * @param string      $actionlabel Localised action verb.
     * @param string      $datestr     Formatted deadline date.
     * @param int         $days        Days until the deadline.
     * @param \stdClass   $from        No-reply sender.
     */
    private function send_warning_editors(
        array $labs,
        array $coursenames,
        string $actionlabel,
        string $datestr,
        int $days,
        \stdClass $from
    ): void {
        $courseids = array_map(fn($lab) => $lab->courseid, $labs);
        $byeditor  = $this->group_courses_by_editor($this->get_course_editors($courseids));

        if (empty($byeditor)) {
            return;
        }

        $subject = get_string('email_warning_subject', 'local_labvirtual', (object) [
            'action' => $actionlabel,
            'days'   => $days,
        ]);
        $intro = get_string('email_warning_editor_body', 'local_labvirtual', (object) [
            'action' => $actionlabel,
            'date'   => $datestr,
        ]);

        foreach ($byeditor as $entry) {
            $courses = [];
            foreach ($entry['courseids'] as $cid) {
                $courses[$cid] = $coursenames[$cid] ?? ('#' . $cid);
            }
            $html = \html_writer::tag('p', s($intro)) . $this->render_course_links($courses);
            $text = html_to_text($html);
            email_to_user($entry['user'], $from, $subject, $text, $html);
        }
    }

    /**
     * Sends post-action summary emails: one per responsible teacher, one per affected
     * course editor, and an optional consolidated copy to the site administrator.
     *
     * @param array[] $results Each entry: ['lab' => \stdClass, 'name' => string,
     *                         'action' => 'reset'|'delete', 'ok' => bool,
     *                         'editors' => \stdClass[]].
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
            $html .= $this->link_paragraph(
                new \moodle_url('/local/labvirtual/view.php', ['batchid' => $batchid]),
                'email_panel_link'
            );
            $text = html_to_text($html);

            foreach ($teachers[$batchid] as $teacher) {
                email_to_user($teacher, $from, $subject, $text, $html);
            }
        }

        $this->send_summary_editors($results, $from, $subject);

        if ($this->notify_admin()) {
            $this->send_admin_copy($subject, $intro, $this->summary_lines($results), $from);
        }
    }

    /**
     * Sends summary emails to the editors of each affected course, one email per
     * editor listing all of their processed labs.
     *
     * @param array[]   $results Summary result entries (each may carry an 'editors' list).
     * @param \stdClass $from    No-reply sender.
     * @param string    $subject Email subject.
     */
    private function send_summary_editors(array $results, \stdClass $from, string $subject): void {
        $byeditor = [];
        foreach ($results as $result) {
            foreach (($result['editors'] ?? []) as $editor) {
                if (!isset($byeditor[$editor->id])) {
                    $byeditor[$editor->id] = ['user' => $editor, 'results' => []];
                }
                $byeditor[$editor->id]['results'][] = $result;
            }
        }

        if (empty($byeditor)) {
            return;
        }

        $intro = get_string('email_summary_editor_body', 'local_labvirtual');

        foreach ($byeditor as $entry) {
            $html = \html_writer::tag('p', s($intro)) . $this->render_list($this->summary_lines($entry['results']));
            $text = html_to_text($html);
            email_to_user($entry['user'], $from, $subject, $text, $html);
        }
    }

    /**
     * Returns the responsible teachers (full user records) for each batch ID in one query.
     *
     * @param int[] $batchids Batch IDs to resolve.
     * @return array<int, \stdClass[]> Indexed by batch ID; each value is a list of user records.
     */
    private function get_teachers_by_batch(array $batchids): array {
        global $DB;

        if (empty($batchids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($batchids, SQL_PARAMS_NAMED);

        $sql = "SELECT bt.id AS rowid, bt.batchid, u.*
                  FROM {local_labvirtual_batch_teachers} bt
                  JOIN {user} u ON u.id = bt.userid
                 WHERE bt.batchid $insql
                   AND u.deleted = 0
                   AND u.suspended = 0";

        $bybatch = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $bybatch[$row->batchid][] = $row;
        }

        return $bybatch;
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
     * Returns the editors (editingteacher role) enrolled in each course, in one query.
     *
     * Public so the maintenance task can capture editors before a course is deleted.
     *
     * @param int[] $courseids Course IDs to resolve.
     * @return array<int, \stdClass[]> Indexed by course ID; each value is a list of user records.
     */
    public function get_course_editors(array $courseids): array {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['roleid']   = $roleid;
        $params['ctxlevel'] = CONTEXT_COURSE;

        $sql = "SELECT ra.id AS rowid, ctx.instanceid AS courseid, u.*
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel
                  JOIN {user} u ON u.id = ra.userid
                 WHERE ctx.instanceid $insql
                   AND ra.roleid = :roleid
                   AND u.deleted = 0
                   AND u.suspended = 0";

        $bycourse = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $bycourse[$row->courseid][] = $row;
        }

        return $bycourse;
    }

    /**
     * Inverts a course-to-editors map into one entry per editor with their course IDs.
     *
     * @param array<int, \stdClass[]> $editorsbycourse Editors indexed by course ID.
     * @return array<int, array{user: \stdClass, courseids: int[]}> Indexed by user ID.
     */
    private function group_courses_by_editor(array $editorsbycourse): array {
        $byeditor = [];
        foreach ($editorsbycourse as $courseid => $editors) {
            foreach ($editors as $editor) {
                if (!isset($byeditor[$editor->id])) {
                    $byeditor[$editor->id] = ['user' => $editor, 'courseids' => []];
                }
                $byeditor[$editor->id]['courseids'][] = $courseid;
            }
        }

        return $byeditor;
    }

    /**
     * Whether the administrator should receive a consolidated copy of lifecycle emails.
     *
     * @return bool
     */
    private function notify_admin(): bool {
        return (bool) get_config('local_labvirtual', 'notify_admin_copy');
    }

    /**
     * Sends a single consolidated email to the site administrator with a management link.
     *
     * @param string   $subject Email subject.
     * @param string   $intro   Intro paragraph text.
     * @param string[] $items   List items to render.
     * @param \stdClass $from    No-reply sender.
     */
    private function send_admin_copy(string $subject, string $intro, array $items, \stdClass $from): void {
        $admin = get_admin();
        if (!$admin) {
            return;
        }

        $html = \html_writer::tag('p', s($intro))
            . $this->render_list($items)
            . $this->link_paragraph(new \moodle_url('/local/labvirtual/manage.php'), 'email_manage_link');
        $text = html_to_text($html);

        email_to_user($admin, $from, $subject, $text, $html);
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
     * Builds a paragraph containing a single localised link.
     *
     * @param \moodle_url $url       Link target.
     * @param string      $stringkey Language string key for the link text.
     * @return string HTML markup.
     */
    private function link_paragraph(\moodle_url $url, string $stringkey): string {
        return \html_writer::tag('p', \html_writer::link($url, get_string($stringkey, 'local_labvirtual')));
    }

    /**
     * Renders a list of courses as links to their course pages.
     *
     * @param string[] $coursenames Course full names indexed by course ID.
     * @return string HTML markup.
     */
    private function render_course_links(array $coursenames): string {
        $listitems = '';
        foreach ($coursenames as $courseid => $name) {
            $url = new \moodle_url('/course/view.php', ['id' => $courseid]);
            $listitems .= \html_writer::tag('li', \html_writer::link($url, $name));
        }

        return \html_writer::tag('ul', $listitems);
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
