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
 * Event observer — enforces the one-editor-per-batch rule for self-enrolments.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual;

/**
 * Reacts to Moodle core events to enforce Lab Virtual business rules.
 */
class observer {
    /**
     * Enforces the one-editor-per-batch rule for self-enrolments.
     *
     * When a user self-enrols via a managed teacher key, checks whether they already
     * hold the editingteacher role in another lab of the same batch. If so, the
     * enrolment is immediately reversed.
     *
     * Manual enrolments (enrol plugin != 'self') are ignored, so admins retain the
     * freedom to assign a user as editor in multiple labs when needed.
     *
     * @param \core\event\user_enrolment_created $event Fired after a user enrolment record is created.
     * @return void
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        global $CFG, $DB;

        if (($event->other['enrol'] ?? '') !== 'self') {
            return;
        }

        $ue = $DB->get_record('user_enrolments', ['id' => $event->objectid], 'id, enrolid, userid');
        if (!$ue) {
            return;
        }

        $lab = $DB->get_record('local_labvirtual_courses', ['teacher_enrolid' => $ue->enrolid]);
        if (!$lab) {
            return;
        }

        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        if (!$teacherroleid) {
            return;
        }

        $sql = "SELECT ra.id
                  FROM {local_labvirtual_courses} lc
                  JOIN {context} ctx ON ctx.instanceid  = lc.courseid
                                    AND ctx.contextlevel = :ctxlevel
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                            AND ra.userid    = :userid
                                            AND ra.roleid    = :roleid
                 WHERE lc.batchid   = :batchid
                   AND lc.courseid != :currentcourse";

        $iseditorelsewhere = $DB->record_exists_sql($sql, [
            'ctxlevel'      => CONTEXT_COURSE,
            'userid'        => $ue->userid,
            'roleid'        => $teacherroleid,
            'batchid'       => $lab->batchid,
            'currentcourse' => $event->courseid,
        ]);

        if (!$iseditorelsewhere) {
            return;
        }

        require_once($CFG->libdir . '/enrollib.php');

        $enrolinstance = $DB->get_record('enrol', ['id' => $ue->enrolid]);
        if ($enrolinstance) {
            enrol_get_plugin('self')->unenrol_user($enrolinstance, $ue->userid);
        }
    }
}
