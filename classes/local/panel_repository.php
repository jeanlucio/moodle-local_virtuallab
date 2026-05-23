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
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual\local;

/**
 * Fetches all panel data for a batch in a single JOIN query, aggregating in PHP.
 *
 * Reads enrolment keys directly from {enrol}.password — no copy in plugin tables.
 */
class panel_repository {
    /** @var int Maximum editors per lab (from plugin config). */
    private int $maxteachers;

    /** @var bool Whether to show keys in the panel. */
    private bool $showkeys;

    /**
     * Constructor reads plugin settings once.
     */
    public function __construct() {
        $this->maxteachers = (int) get_config('local_labvirtual', 'max_teachers_per_lab') ?: 3;
        $this->showkeys    = (bool) get_config('local_labvirtual', 'show_keys_in_panel');
        if ((string) get_config('local_labvirtual', 'show_keys_in_panel') === '') {
            $this->showkeys = true; // Default on.
        }
    }

    /**
     * Returns enriched lab data for all labs in the batch, ready for Mustache rendering.
     *
     * @param int $batchid Batch ID.
     * @return array[] Array of associative arrays, one per lab, ordered by lab ID.
     */
    public function get_panel_data(int $batchid): array {
        global $DB;

        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);

        $sql = "SELECT lc.id          AS labid,
                       lc.courseid,
                       lc.batchid,
                       lc.teacher_enrolid,
                       lc.student_enrolid,
                       lc.timecreated,
                       lc.lastreset,
                       c.fullname      AS coursename,
                       enrt.password   AS teacherkey,
                       enrs.password   AS studentkey,
                       u.id            AS editorid,
                       u.firstname,
                       u.lastname
                  FROM {local_labvirtual_courses} lc
                  JOIN {course} c    ON c.id    = lc.courseid
                  JOIN {enrol} enrt  ON enrt.id = lc.teacher_enrolid
                  JOIN {enrol} enrs  ON enrs.id = lc.student_enrolid
             LEFT JOIN {context} ctx ON ctx.instanceid   = lc.courseid
                                    AND ctx.contextlevel  = :contextlevel
             LEFT JOIN {role_assignments} ra
                    ON ra.contextid = ctx.id
                   AND ra.roleid    = :roleid
             LEFT JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                 WHERE lc.batchid = :batchid
              ORDER BY lc.id ASC, u.lastname ASC, u.firstname ASC";

        $rows = $DB->get_records_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'roleid'       => $teacherroleid,
            'batchid'      => $batchid,
        ]);

        return $this->aggregate($rows);
    }

    /**
     * Aggregates flat SQL rows into one array entry per lab, computing status.
     *
     * @param \stdClass[] $rows Raw rows from the query.
     * @return array[] Enriched lab data for Mustache.
     */
    private function aggregate(array $rows): array {
        $labs = [];

        foreach ($rows as $row) {
            if (!isset($labs[$row->courseid])) {
                $labs[$row->courseid] = [
                    'labid'            => $row->labid,
                    'courseid'         => $row->courseid,
                    'coursename'       => format_string($row->coursename),
                    'teacher_enrolid'  => $row->teacher_enrolid,
                    'student_enrolid'  => $row->student_enrolid,
                    'teacherkey'       => $this->showkeys ? s($row->teacherkey) : '',
                    'studentkey'       => $this->showkeys ? s($row->studentkey) : '',
                    'showkeys'         => $this->showkeys,
                    'editors'          => [],
                ];
            }

            if (!empty($row->editorid)) {
                $labs[$row->courseid]['editors'][] = [
                    'fullname' => s(fullname((object) [
                        'firstname' => $row->firstname,
                        'lastname'  => $row->lastname,
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
            $lab['status_available'] = ($editorcount === 0);
            $lab['status_in_use']    = ($editorcount > 0 && $editorcount < $this->maxteachers);
            $lab['status_full']      = ($editorcount >= $this->maxteachers);
            $lab['can_enrol_editor'] = !$lab['status_full'];
            $lab['statuslabel']      = $this->status_label($lab);

            if ($lab['status_full']) {
                $lab['teacherkey'] = '';
            }

            $result[] = $lab;
        }

        return $result;
    }

    /**
     * Returns the localized status label for a lab.
     *
     * @param array $lab Lab data array with status flags.
     * @return string Localized status string.
     */
    private function status_label(array $lab): string {
        if ($lab['status_full']) {
            return get_string('lab_full', 'local_labvirtual');
        }
        if ($lab['status_in_use']) {
            return get_string('lab_in_use', 'local_labvirtual');
        }
        return get_string('lab_available', 'local_labvirtual');
    }
}
