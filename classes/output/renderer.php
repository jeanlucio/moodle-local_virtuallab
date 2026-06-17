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
 * Output renderer for local_virtuallab.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\output;

use local_virtuallab\local\batch_settings;
use local_virtuallab\local\lifecycle;
use local_virtuallab\local\report_repository;
use plugin_renderer_base;

/**
 * Renders Mustache templates for the plugin.
 */
class renderer extends plugin_renderer_base {
    /**
     * Renders the bulk action confirmation form.
     *
     * @param string $confirmstring Translated confirmation message.
     * @param string $formaction    URL the confirmation form posts to.
     * @param int    $batchid       Batch ID carried through as a hidden field.
     * @param string $bulkaction    Bulk action key ('reset' or 'delete').
     * @param int[]  $labids        Lab IDs selected for the operation.
     * @param string $cancelurl     URL for the Cancel button.
     * @return string Rendered HTML.
     */
    public function render_bulk_confirm(
        string $confirmstring,
        string $formaction,
        int $batchid,
        string $bulkaction,
        array $labids,
        string $cancelurl
    ): string {
        $labrows = [];
        foreach ($labids as $lid) {
            $labrows[] = ['labid' => (int) $lid];
        }

        $context = [
            'confirmstring' => $confirmstring,
            'formaction'    => $formaction,
            'batchid'       => $batchid,
            'bulkaction'    => $bulkaction,
            'sesskey'       => sesskey(),
            'cancelurl'     => $cancelurl,
            'labids'        => $labrows,
        ];

        return $this->render_from_template('local_virtuallab/manage_bulk_confirm', $context);
    }

    /**
     * Renders the student panel for a batch.
     *
     * @param \stdClass $batch        Batch record.
     * @param string    $teachernames Comma-separated names of the responsible teachers.
     * @param array     $labs         Enriched lab data from panel_repository.
     * @param int       $batchid      Batch ID (for form actions).
     * @param bool      $canmanage    Whether the viewer may manage the batch (shows the view-course link).
     * @return string Rendered HTML.
     */
    public function render_labs_panel(
        \stdClass $batch,
        string $teachernames,
        array $labs,
        int $batchid,
        bool $canmanage = false
    ): string {
        // When every lab shares one deadline, collapse it into a single line above the table;
        // only keep the per-lab date when an individual reset made the dates diverge.
        $summary = lifecycle::summarise_deadlines(array_map(
            static fn($lab) => (int) ($lab['deadline'] ?? 0),
            $labs
        ));

        $labrows = [];
        foreach ($labs as $lab) {
            $lab['courseurl'] = (new \moodle_url('/course/view.php', ['id' => $lab['courseid']]))->out(false);
            if ($summary['shared']) {
                $lab['deadline_label'] = '';
            }
            $labrows[] = $lab;
        }

        $context = [
            'batchname'       => format_string($batch->name),
            'teachername'     => $teachernames,
            'batchid'         => $batchid,
            'canmanage'       => $canmanage,
            'sesskey'         => sesskey(),
            'viewurl'         => (new \moodle_url('/local/virtuallab/view.php', ['batchid' => $batchid]))->out(false),
            'haslabs'         => !empty($labs),
            'labs'            => $labrows,
            'scheduledlabel'  => lifecycle::scheduled_label($batch, $summary['deadline']),
        ];

        return $this->render_from_template('local_virtuallab/labs_panel', $context);
    }

    /**
     * Renders the admin batches list.
     *
     * @param array  $batches      List of batch records from batch_manager::list_batches().
     * @param string $createurl    URL for the "New batch" action.
     * @param bool   $cancreate    Whether to show the "New batch" button.
     * @return string Rendered HTML.
     */
    public function render_batches_list(array $batches, string $createurl, bool $cancreate = false): string {
        $rows = [];

        foreach ($batches as $batch) {
            $rows[] = [
                'id'           => $batch->id,
                'name'         => format_string($batch->name),
                'teachername'  => $batch->teachernames,
                'categoryname' => format_string($batch->categoryname),
                'labcount'     => (int) $batch->labcount,
                'manageurl'    => (new \moodle_url(
                    '/local/virtuallab/manage.php',
                    ['batchid' => $batch->id]
                ))->out(false),
                'reporturl'    => (new \moodle_url(
                    '/local/virtuallab/report.php',
                    ['batchid' => $batch->id]
                ))->out(false),
                'reportlabel'  => get_string('report_view_report_label', 'local_virtuallab', format_string($batch->name)),
                'deleteurl'    => (new \moodle_url('/local/virtuallab/manage.php', [
                    'action'        => 'deletebatch',
                    'targetbatchid' => $batch->id,
                ]))->out(false),
                'deletelabel'  => get_string('delete_batch_label', 'local_virtuallab', format_string($batch->name)),
            ];
        }

        $context = [
            'batches'    => $rows,
            'hasbatches' => !empty($rows),
            'cancreate'  => $cancreate,
            'createurl'  => $createurl,
        ];

        return $this->render_from_template('local_virtuallab/manage_batches', $context);
    }

    /**
     * Renders the admin labs list for a batch, including reset/delete actions and bulk form.
     *
     * @param \stdClass $batch     Batch record.
     * @param array     $labs      Lab records from course_registry::list_labs().
     * @param string    $panelurl  Absolute URL to the student panel for this batch.
     * @param string    $createurl URL for the "Create labs" action.
     * @param string    $editurl   URL for the "Edit batch" action.
     * @param string    $syncurl   URL for the "Apply task list to existing labs" action (empty hides it).
     * @return string Rendered HTML.
     */
    public function render_labs_list(
        \stdClass $batch,
        array $labs,
        string $panelurl,
        string $createurl,
        string $editurl = '',
        string $syncurl = ''
    ): string {
        $qrsvg = '';
        if ($panelurl !== '') {
            try {
                $qrcode = new \core_qrcode($panelurl);
                $qrsvg = 'data:image/svg+xml;base64,' . base64_encode($qrcode->getBarcodeSVGcode(4, 4));
            } catch (\Throwable $e) {
                $qrsvg = '';
            }
        }

        $action = (int) batch_settings::effective($batch)->lifecycleaction;
        $actionkey = $action === 2 ? 'next_action_delete' : 'next_action_reset';

        // Collapse a shared deadline into one line above the table; show the per-lab column
        // only when an individual reset made the dates diverge.
        $deadlines = [];
        foreach ($labs as $lab) {
            $deadlines[$lab->id] = lifecycle::deadline($batch, $lab);
        }
        $summary   = lifecycle::summarise_deadlines($deadlines);
        $divergent = !$summary['shared'] && (bool) array_filter($deadlines);

        $rows = [];

        foreach ($labs as $lab) {
            $deadline = $deadlines[$lab->id];
            $rows[] = [
                'id'          => $lab->id,
                'coursename'  => format_string($lab->coursename),
                'shortname'   => s($lab->shortname),
                'courseurl'   => (new \moodle_url('/course/view.php', ['id' => $lab->courseid]))->out(false),
                'nextaction'  => $deadline > 0
                    ? get_string(
                        $actionkey,
                        'local_virtuallab',
                        userdate($deadline, get_string('strftimedate', 'langconfig'))
                    )
                    : '—',
                'lastreset'   => $lab->lastreset > 0
                    ? userdate($lab->lastreset, get_string('strftimedatetime', 'langconfig'))
                    : '—',
                'reseturl'    => (new \moodle_url('/local/virtuallab/manage.php', [
                    'batchid' => $batch->id,
                    'action'  => 'resetlab',
                    'labid'   => $lab->id,
                ]))->out(false),
                'deleteurl'   => (new \moodle_url('/local/virtuallab/manage.php', [
                    'batchid' => $batch->id,
                    'action'  => 'deletelab',
                    'labid'   => $lab->id,
                ]))->out(false),
                'resetlabel'  => get_string('reset_lab_label', 'local_virtuallab', format_string($lab->coursename)),
                'deletelabel' => get_string('delete_lab_label', 'local_virtuallab', format_string($lab->coursename)),
            ];
        }

        $context = [
            'batchname'    => format_string($batch->name),
            'batchid'      => $batch->id,
            'manageurl'    => (new \moodle_url('/local/virtuallab/manage.php'))->out(false),
            'labs'         => $rows,
            'haslabs'      => !empty($rows),
            'panelurl'     => $panelurl,
            'qrsvg'        => $qrsvg,
            'qralt'        => get_string('panel_url_qrcode', 'local_virtuallab'),
            'createurl'    => $createurl,
            'editurl'      => $editurl,
            'syncurl'      => $syncurl,
            'sesskey'      => sesskey(),
            'strpanelhelp' => get_string('panel_url_help', 'local_virtuallab'),
            'scheduledlabel' => lifecycle::scheduled_label($batch, $summary['deadline']),
            'divergent'    => $divergent,
        ];

        return $this->render_from_template('local_virtuallab/manage_labs', $context);
    }

    /**
     * Renders the paginated batch overview table for the usage report.
     *
     * @param \stdClass   $batch       Batch record.
     * @param \stdClass[] $enrolments  Paginated enrolment rows from report_repository.
     * @param array       $rolemap     Role map keyed by [userid][courseid].
     * @param array       $logsummary  Log summary keyed by [userid][courseid].
     * @param int         $batchid     Batch ID (for the detail link).
     * @return string Rendered HTML.
     */
    public function render_report_batch(
        \stdClass $batch,
        array $enrolments,
        array $rolemap,
        array $logsummary,
        int $batchid
    ): string {
        $dtformat = get_string('strftimedatetime', 'langconfig');
        $teacherroleid = $this->get_teacher_role_id();

        $rows = [];
        foreach ($enrolments as $row) {
            $uid = (int) $row->userid;
            $cid = (int) $row->courseid;
            $lid = (int) $row->labid;

            $roleid     = $rolemap[$uid][$cid] ?? 0;
            $iseditor   = ($roleid === $teacherroleid);
            $logentry   = $logsummary[$uid][$cid] ?? null;
            $lastaccess = $logentry ? userdate($logentry['lastactivity'], $dtformat) : '';
            $eventcount = $logentry ? $logentry['eventcount'] : 0;

            $rows[] = [
                'labname'       => format_string($row->coursename),
                'labdetailurl'  => (new \moodle_url(
                    '/local/virtuallab/report.php',
                    ['batchid' => $batchid, 'labid' => $lid]
                ))->out(false),
                'fullname'      => s(fullname($row)),
                'iseditor'      => $iseditor,
                'rolelabel'     => get_string(
                    $iseditor ? 'report_role_editor' : 'report_role_visitor',
                    'local_virtuallab'
                ),
                'enrolledat'    => userdate($row->enrolledat, $dtformat),
                'haslastaccess' => $lastaccess !== '',
                'lastaccess'    => $lastaccess,
                'eventcount'    => $eventcount,
            ];
        }

        $context = [
            'batchname'      => format_string($batch->name),
            'manageurl'      => (new \moodle_url('/local/virtuallab/manage.php', ['batchid' => $batchid]))->out(false),
            'exportcsvurl'   => (new \moodle_url(
                '/local/virtuallab/report_export.php',
                ['batchid' => $batchid, 'format' => 'csv']
            ))->out(false),
            'exportexcelurl' => (new \moodle_url(
                '/local/virtuallab/report_export.php',
                ['batchid' => $batchid, 'format' => 'excel']
            ))->out(false),
            'hasrows'        => !empty($rows),
            'rows'           => $rows,
        ];

        return $this->render_from_template('local_virtuallab/report_batch', $context);
    }

    /**
     * Renders the per-student event summary for a single lab.
     *
     * @param \stdClass   $batch       Batch record.
     * @param \stdClass   $lab         Lab record (with coursename).
     * @param \stdClass[] $enrolments  All enrolled users from report_repository.
     * @param array       $rolemap     Role map keyed by [userid][courseid].
     * @param array       $breakdown   Event breakdown keyed by userid.
     * @return string Rendered HTML.
     */
    public function render_report_lab_detail(
        \stdClass $batch,
        \stdClass $lab,
        array $enrolments,
        array $rolemap,
        array $breakdown
    ): string {
        $dtformat      = get_string('strftimedatetime', 'langconfig');
        $teacherroleid = $this->get_teacher_role_id();
        $courseid      = (int) $lab->courseid;
        $batchid       = (int) $lab->batchid;

        $users = [];
        foreach ($enrolments as $row) {
            $uid      = (int) $row->userid;
            $roleid   = $rolemap[$uid][$courseid] ?? 0;
            $iseditor = ($roleid === $teacherroleid);

            $eventrows = [];
            foreach ($breakdown[$uid] ?? [] as $entry) {
                $eventrows[] = [
                    'componentlabel' => s(report_repository::component_label($entry['component'])),
                    'actionlabel'    => s(report_repository::action_label($entry['action'])),
                    'cnt'            => $entry['cnt'],
                    'lasttime'       => userdate($entry['lasttime'], $dtformat),
                ];
            }

            $users[] = [
                'fullname'  => s(fullname($row)),
                'iseditor'  => $iseditor,
                'rolelabel' => get_string(
                    $iseditor ? 'report_role_editor' : 'report_role_visitor',
                    'local_virtuallab'
                ),
                'enrolledat' => userdate($row->enrolledat, $dtformat),
                'hasevents'  => !empty($eventrows),
                'events'     => $eventrows,
            ];
        }

        $context = [
            'batchname'      => format_string($batch->name),
            'labname'        => format_string($lab->coursename),
            'reporturl'      => (new \moodle_url('/local/virtuallab/report.php', ['batchid' => $batchid]))->out(false),
            'manageurl'      => (new \moodle_url('/local/virtuallab/manage.php', ['batchid' => $batchid]))->out(false),
            'exportcsvurl'   => (new \moodle_url(
                '/local/virtuallab/report_export.php',
                ['batchid' => $batchid, 'labid' => $lab->id, 'format' => 'csv']
            ))->out(false),
            'exportexcelurl' => (new \moodle_url(
                '/local/virtuallab/report_export.php',
                ['batchid' => $batchid, 'labid' => $lab->id, 'format' => 'excel']
            ))->out(false),
            'hasusers'       => !empty($users),
            'users'          => $users,
        ];

        return $this->render_from_template('local_virtuallab/report_lab_detail', $context);
    }

    /**
     * Returns the editingteacher role ID, fetched once per request.
     *
     * @return int
     */
    private function get_teacher_role_id(): int {
        global $DB;
        static $id = null;
        if ($id === null) {
            $id = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        }
        return $id;
    }
}
