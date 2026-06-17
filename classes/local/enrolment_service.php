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
 * Enrolment service — editor enrolment with cap enforcement under a lock.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

/**
 * Enrols students as editors while enforcing the per-batch editor cap and the
 * one-editor-anywhere rule, serialising concurrent requests with a per-course lock.
 */
class enrolment_service {
    /** @var int Seconds to wait for the per-course enrolment lock (the work itself is sub-second). */
    private const LOCK_TIMEOUT = 5;

    /**
     * Returns why a user cannot take an editor slot in the lab, or '' if they can.
     *
     * @param \stdClass $lab    Lab record with courseid.
     * @param \stdClass $batch  Batch record (resolves the effective editor cap).
     * @param int       $userid User attempting to enrol.
     * @return string '' when eligible, 'full' when the cap is reached, or 'elsewhere'
     *                when the user already holds an editor slot in another lab of the batch.
     */
    public function editor_block_reason(\stdClass $lab, \stdClass $batch, int $userid): string {
        global $DB;

        $roleid      = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $maxteachers = batch_settings::effective($batch)->maxteachers;

        $editorcount = $DB->count_records_sql(
            "SELECT COUNT(ra.id)
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel
              WHERE ctx.instanceid = :courseid
                AND ra.roleid = :roleid",
            ['ctxlevel' => CONTEXT_COURSE, 'courseid' => $lab->courseid, 'roleid' => $roleid]
        );

        if ($editorcount >= $maxteachers) {
            return 'full';
        }

        if ($this->is_editor_elsewhere((int) $lab->courseid, (int) $batch->id, $userid, (int) $roleid)) {
            return 'elsewhere';
        }

        return '';
    }

    /**
     * Enrols the user as editor under a per-course lock, re-checking the cap inside it so
     * two simultaneous requests cannot both exceed the limit.
     *
     * @param \stdClass $lab    Lab record with courseid and enrolid.
     * @param \stdClass $batch  Batch record.
     * @param int       $userid User to enrol.
     * @return string '' on success, 'full'/'elsewhere' when no longer eligible, or 'busy'
     *                when the lock could not be acquired.
     */
    public function enrol_editor(\stdClass $lab, \stdClass $batch, int $userid): string {
        global $CFG, $DB;

        require_once($CFG->libdir . '/enrollib.php');

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_virtuallab');
        $lock        = $lockfactory->get_lock('editorenrol_' . (int) $lab->courseid, self::LOCK_TIMEOUT);
        if (!$lock) {
            return 'busy';
        }

        try {
            $reason = $this->editor_block_reason($lab, $batch, $userid);
            if ($reason !== '') {
                return $reason;
            }

            $roleid   = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
            $instance = $DB->get_record(
                'enrol',
                ['id' => $lab->enrolid, 'courseid' => $lab->courseid],
                '*',
                MUST_EXIST
            );
            enrol_get_plugin('manual')->enrol_user($instance, $userid, $roleid, time(), 0);

            return '';
        } finally {
            $lock->release();
        }
    }

    /**
     * Maps an editor block/result key to its localised error message.
     *
     * @param string $reason Result key returned by editor_block_reason() or enrol_editor().
     * @return string Localised message.
     */
    public function error_message(string $reason): string {
        $keys = [
            'full'      => 'error_lab_full',
            'elsewhere' => 'error_already_editor_in_batch',
            'busy'      => 'error_enrol_busy',
        ];

        return get_string($keys[$reason] ?? 'error_lab_full', 'local_virtuallab');
    }

    /**
     * Returns true if the user already holds the editor role in another lab of the batch.
     *
     * @param int $courseid Current course (excluded from the check).
     * @param int $batchid  Batch to scan.
     * @param int $userid   User to check.
     * @param int $roleid   editingteacher role ID.
     * @return bool
     */
    private function is_editor_elsewhere(int $courseid, int $batchid, int $userid, int $roleid): bool {
        global $DB;

        $sql = "SELECT ra.id
                  FROM {local_virtuallab_courses} lc
                  JOIN {context} ctx ON ctx.instanceid  = lc.courseid
                                   AND ctx.contextlevel = :ctxlevel
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                            AND ra.userid    = :userid
                                            AND ra.roleid    = :roleid
                 WHERE lc.batchid   = :batchid
                   AND lc.courseid != :currentcourse";

        return $DB->record_exists_sql($sql, [
            'ctxlevel'      => CONTEXT_COURSE,
            'userid'        => $userid,
            'roleid'        => $roleid,
            'batchid'       => $batchid,
            'currentcourse' => $courseid,
        ]);
    }
}
