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
 * Data export (CSV/Excel/...) for the consolidated lab usage report.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

/**
 * Builds export rows from report_repository data and streams them via core dataformat.
 */
class report_exporter {
    /**
     * Exports the full (unpaginated) batch overview using Moodle core dataformat.
     *
     * @param \stdClass $batch   Batch record.
     * @param int       $batchid Batch ID.
     * @param string    $format  Export format (csv, excel, ...).
     * @return void
     */
    public function export_batch(\stdClass $batch, int $batchid, string $format): void {
        $repository = new report_repository();
        $enrolments = $repository->get_all_batch_enrolments($batchid);

        $courseids = array_unique(array_map(fn($r) => (int) $r->courseid, $enrolments));
        $userids   = array_unique(array_map(fn($r) => (int) $r->userid, $enrolments));

        $logsummary    = $repository->get_log_summary($courseids, $userids);
        $rolemap       = $repository->get_role_map($courseids, $userids);
        $teacherroleid = $repository->get_teacher_role_id();
        $dtformat      = get_string('strftimedatetime', 'langconfig');

        $columns = [
            get_string('report_col_lab', 'local_virtuallab'),
            get_string('report_col_student', 'local_virtuallab'),
            get_string('report_col_role', 'local_virtuallab'),
            get_string('report_col_enrolled_at', 'local_virtuallab'),
            get_string('report_col_last_access', 'local_virtuallab'),
            get_string('report_col_events', 'local_virtuallab'),
        ];

        $rows = [];
        foreach ($enrolments as $row) {
            $uid = (int) $row->userid;
            $cid = (int) $row->courseid;

            $roleid   = $rolemap[$uid][$cid] ?? 0;
            $iseditor = ($roleid === $teacherroleid);
            $logentry = $logsummary[$uid][$cid] ?? null;

            $rows[] = [
                format_string($row->coursename),
                fullname($row),
                get_string($iseditor ? 'report_role_editor' : 'report_role_visitor', 'local_virtuallab'),
                userdate($row->enrolledat, $dtformat),
                $logentry ? userdate($logentry['lastactivity'], $dtformat) : get_string('report_never', 'local_virtuallab'),
                $logentry ? $logentry['eventcount'] : 0,
            ];
        }

        $filename = 'virtuallab_report_' . clean_filename(format_string($batch->name)) . '_' . date('Ymd');

        \core\dataformat::download_data($filename, $format, $columns, $rows);
        die();
    }

    /**
     * Exports the per-student event breakdown for a single lab using Moodle core dataformat.
     *
     * One row is emitted per (student, event type) pair; students with no recorded
     * activity get a single row with the event columns left blank.
     *
     * @param \stdClass $batch   Batch record.
     * @param \stdClass $lab     Lab record (with coursename and courseid).
     * @param int       $batchid Batch ID.
     * @param string    $format  Export format (csv, excel, ...).
     * @return void
     */
    public function export_lab_detail(\stdClass $batch, \stdClass $lab, int $batchid, string $format): void {
        $repository = new report_repository();
        $courseid   = (int) $lab->courseid;

        $enrolments = $repository->get_lab_enrolments((int) $lab->id, $batchid);
        $userids    = array_map(fn($r) => (int) $r->userid, $enrolments);

        $rolemap       = $repository->get_role_map([$courseid], $userids);
        $breakdown     = $repository->get_lab_event_breakdown($courseid, $userids);
        $teacherroleid = $repository->get_teacher_role_id();
        $dtformat      = get_string('strftimedatetime', 'langconfig');

        $columns = [
            get_string('report_col_student', 'local_virtuallab'),
            get_string('report_col_role', 'local_virtuallab'),
            get_string('report_col_enrolled_at', 'local_virtuallab'),
            get_string('report_col_component', 'local_virtuallab'),
            get_string('report_col_action', 'local_virtuallab'),
            get_string('report_col_count', 'local_virtuallab'),
            get_string('report_col_last_time', 'local_virtuallab'),
        ];

        $rows = [];
        foreach ($enrolments as $row) {
            $uid       = (int) $row->userid;
            $roleid    = $rolemap[$uid][$courseid] ?? 0;
            $iseditor  = ($roleid === $teacherroleid);
            $rolelabel = get_string($iseditor ? 'report_role_editor' : 'report_role_visitor', 'local_virtuallab');
            $enrolledat = userdate($row->enrolledat, $dtformat);
            $events     = $breakdown[$uid] ?? [];

            if (empty($events)) {
                $rows[] = [fullname($row), $rolelabel, $enrolledat, '', '', 0, ''];
                continue;
            }

            foreach ($events as $entry) {
                $rows[] = [
                    fullname($row),
                    $rolelabel,
                    $enrolledat,
                    report_repository::component_label($entry['component']),
                    report_repository::action_label($entry['action']),
                    $entry['cnt'],
                    userdate($entry['lasttime'], $dtformat),
                ];
            }
        }

        $filename = 'virtuallab_report_' . clean_filename(format_string($lab->coursename)) . '_' . date('Ymd');

        \core\dataformat::download_data($filename, $format, $columns, $rows);
        die();
    }
}
