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
 * Panel repository — bulk query for the student lab-selection panel.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

/**
 * Fetches all panel data for a batch in a single JOIN query, aggregating in PHP.
 */
class panel_repository {
    /** @var int Maximum editors per lab (effective value for the batch being rendered). */
    private int $maxteachers;

    /** @var int Role ID for editingteacher, cached to avoid repeated queries. */
    private int $teacherroleid;

    /** @var \stdClass Batch being rendered, used to resolve lifecycle deadlines. */
    private \stdClass $batch;

    /** @var int Effective lifecycle action of the batch (0 disabled, 1 reset, 2 delete). */
    private int $lifecycleaction;

    /**
     * Constructor reads plugin settings once.
     */
    public function __construct() {
        global $DB;
        $this->teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
    }

    /**
     * Returns enriched lab data for all labs in the batch, ready for Mustache rendering.
     *
     * @param int $batchid Batch ID.
     * @param int $userid  Current user ID (used to detect existing enrolments).
     * @return array[] Array of associative arrays, one per lab, ordered by lab ID.
     */
    public function get_panel_data(int $batchid, int $userid): array {
        global $DB;

        // Resolve the editor cap from the batch's effective settings (per-batch override or
        // global default) so the panel status matches what view.php enforces on enrolment.
        $batch = $DB->get_record('local_virtuallab_batches', ['id' => $batchid], '*', MUST_EXIST);
        $this->batch           = $batch;
        $effective             = batch_settings::effective($batch);
        $this->maxteachers     = $effective->maxteachers;
        $this->lifecycleaction = $effective->lifecycleaction;

        $sql = "SELECT lc.id          AS labid,
                       lc.courseid,
                       lc.batchid,
                       lc.timecreated,
                       lc.lastreset,
                       c.fullname      AS coursename,
                       u.id            AS editorid,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename
                  FROM {local_virtuallab_courses} lc
                  JOIN {course} c    ON c.id    = lc.courseid
             LEFT JOIN {context} ctx ON ctx.instanceid   = lc.courseid
                                    AND ctx.contextlevel  = :contextlevel
             LEFT JOIN {role_assignments} ra
                    ON ra.contextid = ctx.id
                   AND ra.roleid    = :roleid
             LEFT JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                 WHERE lc.batchid = :batchid
              ORDER BY lc.id ASC, u.lastname ASC, u.firstname ASC";

        $recordset = $DB->get_recordset_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'roleid'       => $this->teacherroleid,
            'batchid'      => $batchid,
        ]);
        $rows = [];
        foreach ($recordset as $row) {
            $rows[] = $row;
        }
        $recordset->close();

        $userenrolments = $this->get_user_enrolments($batchid, $userid);

        return $this->aggregate($rows, $userenrolments['enrolled'], $userenrolments['iseditoranywhere']);
    }

    /**
     * Returns which labs in the batch the user is already enrolled in, and whether they
     * hold the editingteacher role in any of them.
     *
     * @param int $batchid Batch ID.
     * @param int $userid  User ID to check.
     * @return array{enrolled: array<int,bool>, iseditoranywhere: bool}
     */
    private function get_user_enrolments(int $batchid, int $userid): array {
        global $DB;

        $sql = "SELECT ra.id, lc.courseid, ra.roleid
                  FROM {local_virtuallab_courses} lc
                  JOIN {context} ctx ON ctx.instanceid  = lc.courseid
                                    AND ctx.contextlevel = :contextlevel
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                            AND ra.userid    = :userid
                 WHERE lc.batchid = :batchid";

        $rows = $DB->get_records_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid'       => $userid,
            'batchid'      => $batchid,
        ]);

        $enrolled         = [];
        $iseditoranywhere = false;
        foreach ($rows as $row) {
            $enrolled[(int) $row->courseid] = true;
            if ((int) $row->roleid === $this->teacherroleid) {
                $iseditoranywhere = true;
            }
        }
        return ['enrolled' => $enrolled, 'iseditoranywhere' => $iseditoranywhere];
    }

    /**
     * Aggregates flat SQL rows into one array entry per lab, computing status.
     *
     * @param \stdClass[] $rows               Raw rows from the query.
     * @param bool[]      $enrolledcourseids  Keyed by course ID; true if the user is enrolled.
     * @param bool        $useriseditoranywhere Whether the user holds editingteacher in any batch lab.
     * @return array[] Enriched lab data for Mustache.
     */
    private function aggregate(array $rows, array $enrolledcourseids, bool $useriseditoranywhere): array {
        $labs = [];

        foreach ($rows as $row) {
            if (!isset($labs[$row->courseid])) {
                $labs[$row->courseid] = [
                    'labid'                   => $row->labid,
                    'courseid'                => $row->courseid,
                    'coursename'              => format_string($row->coursename),
                    'user_enrolled_here'      => !empty($enrolledcourseids[(int) $row->courseid]),
                    'user_is_editor_anywhere' => $useriseditoranywhere,
                    'deadline'                => lifecycle::deadline($this->batch, $row),
                    'editors'                 => [],
                ];
            }

            if (!empty($row->editorid)) {
                $labs[$row->courseid]['editors'][] = [
                    'fullname' => s(fullname((object) [
                        'firstname'         => $row->firstname,
                        'lastname'          => $row->lastname,
                        'firstnamephonetic' => $row->firstnamephonetic,
                        'lastnamephonetic'  => $row->lastnamephonetic,
                        'middlename'        => $row->middlename,
                        'alternatename'     => $row->alternatename,
                    ])),
                ];
            }
        }

        return $this->compute_status($labs);
    }

    /**
     * Adds status flags and derived display fields to each lab entry.
     *
     * @param array[] $labs Aggregated lab data (by courseid).
     * @return array[] Re-indexed array with status fields added.
     */
    private function compute_status(array $labs): array {
        $result = [];

        foreach ($labs as $lab) {
            $editorcount         = count($lab['editors']);
            $lab['editorcount']  = $editorcount;
            $lab['status_available']   = ($editorcount === 0);
            $lab['status_in_use']     = ($editorcount > 0 && $editorcount < $this->maxteachers);
            $lab['status_full']       = ($editorcount >= $this->maxteachers);
            $lab['can_enrol_editor']  = !$lab['status_full']
                && !$lab['user_enrolled_here']
                && !$lab['user_is_editor_anywhere'];
            $lab['can_enrol_visitor'] = !$lab['user_enrolled_here'];
            // When the editor button is disabled, tell the student why: the one-editor rule
            // (editor elsewhere) takes precedence over a full lab, since the lab may have a
            // free slot yet still be blocked for this student.
            $lab['editor_blocked_elsewhere'] = !$lab['can_enrol_editor']
                && !$lab['user_enrolled_here']
                && $lab['user_is_editor_anywhere'];
            $lab['editor_block_aria'] = $lab['editor_blocked_elsewhere']
                ? get_string('cannot_editor_elsewhere', 'local_virtuallab')
                : get_string('lab_full', 'local_virtuallab');
            $lab['statuslabel']       = $this->status_label($lab);
            $lab['slotsleft_label']   = $lab['status_in_use']
                ? $this->slots_left_label($this->maxteachers - $editorcount)
                : '';
            $lab['deadline_label']    = $this->deadline_label((int) $lab['deadline']);

            $result[] = $lab;
        }

        return $result;
    }

    /**
     * Returns the localized "this lab will be reset/deleted on DATE" label.
     *
     * @param int $deadline Deadline timestamp, or 0 when the lifecycle is disabled.
     * @return string Localized label, or empty string when no deadline applies.
     */
    private function deadline_label(int $deadline): string {
        if ($deadline <= 0) {
            return '';
        }

        $key = $this->lifecycleaction === 2 ? 'next_action_delete' : 'next_action_reset';

        return get_string(
            $key,
            'local_virtuallab',
            userdate($deadline, get_string('strftimedate', 'langconfig'))
        );
    }

    /**
     * Returns the localized "X vagas" label for the remaining editor slots.
     *
     * @param int $count Number of remaining slots.
     * @return string Localized label.
     */
    private function slots_left_label(int $count): string {
        if ($count === 1) {
            return get_string('lab_slots_left_one', 'local_virtuallab');
        }
        return get_string('lab_slots_left', 'local_virtuallab', $count);
    }

    /**
     * Returns the localized status label for a lab.
     *
     * @param array $lab Lab data array with status flags.
     * @return string Localized status string.
     */
    private function status_label(array $lab): string {
        if ($lab['status_full']) {
            return get_string('lab_full', 'local_virtuallab');
        }
        if ($lab['status_in_use']) {
            return get_string('lab_in_use', 'local_virtuallab');
        }
        return get_string('lab_available', 'local_virtuallab');
    }
}
