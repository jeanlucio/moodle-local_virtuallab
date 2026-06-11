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
 * Maintenance service — reset and delete operations for Lab Virtual labs and batches.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual\local;

use local_labvirtual\event\batch_deleted;
use local_labvirtual\event\course_deleted;
use local_labvirtual\event\course_reset;

/**
 * Provides reset and delete operations for managed lab courses and batches.
 *
 * All write operations validate ownership (batchid ↔ labid) before acting,
 * preventing cross-batch modification of unrelated courses.
 */
class maintenance_service {
    /**
     * Resets a single lab course: clears all user data and activities,
     * unenrols all users, and updates lastreset timestamp.
     *
     * @param int $labid   Row ID in local_labvirtual_courses.
     * @param int $batchid Batch the lab must belong to (ownership check).
     * @throws \moodle_exception If lab does not exist or belongs to a different batch.
     */
    public function reset_lab(int $labid, int $batchid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $lab = $DB->get_record_sql(
            "SELECT lc.* FROM {local_labvirtual_courses} lc
              WHERE lc.id = :id AND lc.batchid = :batchid",
            ['id' => $labid, 'batchid' => $batchid],
            MUST_EXIST
        );

        $resetdata = $this->build_reset_data($lab->courseid);
        reset_course_userdata($resetdata);

        // Clearing lastwarn starts a fresh lifecycle cycle so a new warning is sent next time.
        $DB->update_record('local_labvirtual_courses', (object) [
            'id'        => $labid,
            'lastreset' => time(),
            'lastwarn'  => 0,
        ]);

        $event = course_reset::create([
            'objectid' => $lab->courseid,
            'context'  => \context_course::instance($lab->courseid),
            'other'    => ['batchid' => $batchid, 'labid' => $labid],
        ]);
        $event->trigger();
    }

    /**
     * Deletes a single lab course: removes enrol instances, deletes the Moodle
     * course and removes the plugin registry row.
     *
     * @param int $labid   Row ID in local_labvirtual_courses.
     * @param int $batchid Batch the lab must belong to (ownership check).
     * @throws \moodle_exception If lab does not exist or belongs to a different batch.
     */
    public function delete_lab(int $labid, int $batchid): void {
        global $DB;

        $lab = $DB->get_record_sql(
            "SELECT lc.* FROM {local_labvirtual_courses} lc
              WHERE lc.id = :id AND lc.batchid = :batchid",
            ['id' => $labid, 'batchid' => $batchid],
            MUST_EXIST
        );

        $this->do_delete_lab($lab);
    }

    /**
     * Deletes a batch and all its labs: removes every lab course, their enrol
     * instances, all registry rows, and the batch record itself.
     *
     * @param int $batchid Batch ID.
     * @throws \dml_exception If the batch record does not exist.
     */
    public function delete_batch(int $batchid): void {
        global $DB;

        $DB->get_record('local_labvirtual_batches', ['id' => $batchid], 'id', MUST_EXIST);

        $labs = $DB->get_records('local_labvirtual_courses', ['batchid' => $batchid]);

        if ($labs) {
            // Pre-fetch all enrol instances in one query to avoid N+1.
            $enrolids = [];
            foreach ($labs as $lab) {
                $enrolids[] = $lab->teacher_enrolid;
                $enrolids[] = $lab->student_enrolid;
            }
            [$insql, $params] = $DB->get_in_or_equal(array_unique($enrolids), SQL_PARAMS_NAMED);
            $enrolinstances = $DB->get_records_select('enrol', "id $insql", $params);

            $enrolplugin = enrol_get_plugin('self');

            // Delete each lab course; delete_course() is a per-course Moodle API call so the loop is unavoidable.
            foreach ($labs as $lab) {
                $context = \context_course::instance($lab->courseid, IGNORE_MISSING);

                // Remove enrol instances before course deletion.
                foreach ([$lab->teacher_enrolid, $lab->student_enrolid] as $enrolid) {
                    if (isset($enrolinstances[$enrolid])) {
                        $enrolplugin->delete_instance($enrolinstances[$enrolid]);
                    }
                }

                $event = course_deleted::create([
                    'objectid' => $lab->id,
                    'context'  => $context ?? \context_system::instance(),
                    'other'    => ['courseid' => $lab->courseid, 'batchid' => $batchid],
                ]);

                delete_course($lab->courseid, false);

                $event->trigger();
            }

            $DB->delete_records('local_labvirtual_courses', ['batchid' => $batchid]);
        }

        $DB->delete_records('local_labvirtual_batches', ['id' => $batchid]);

        $event = batch_deleted::create([
            'objectid' => $batchid,
            'context'  => \context_system::instance(),
            'other'    => ['labcount' => count($labs)],
        ]);
        $event->trigger();
    }

    /**
     * Internal helper: deletes a single lab without the ownership pre-check.
     * Used by delete_batch to avoid redundant lookups when iterating over labs
     * already validated to belong to the batch.
     *
     * @param \stdClass $lab Row from local_labvirtual_courses.
     */
    private function do_delete_lab(\stdClass $lab): void {
        global $DB;

        $context = \context_course::instance($lab->courseid, IGNORE_MISSING);

        // Fetch both enrol instances in one query.
        [$insql, $params] = $DB->get_in_or_equal(
            [$lab->teacher_enrolid, $lab->student_enrolid],
            SQL_PARAMS_NAMED
        );
        $enrolinstances = $DB->get_records_select('enrol', "id $insql", $params);

        $enrolplugin = enrol_get_plugin('self');
        foreach ($enrolinstances as $instance) {
            $enrolplugin->delete_instance($instance);
        }

        $event = course_deleted::create([
            'objectid' => $lab->id,
            'context'  => $context ?? \context_system::instance(),
            'other'    => ['courseid' => $lab->courseid, 'batchid' => $lab->batchid],
        ]);

        delete_course($lab->courseid, false);
        $DB->delete_records('local_labvirtual_courses', ['id' => $lab->id]);

        $event->trigger();
    }

    /**
     * Builds the reset data object for reset_course_userdata().
     *
     * Fetches editingteacher, teacher and student role IDs dynamically so the
     * reset unenrols all users regardless of site-specific role configuration.
     *
     * @param int $courseid Course to reset.
     * @return \stdClass Reset parameters accepted by reset_course_userdata().
     */
    private function build_reset_data(int $courseid): \stdClass {
        global $DB;

        $roles = $DB->get_records_list(
            'role',
            'shortname',
            ['editingteacher', 'teacher', 'student'],
            '',
            'id'
        );
        $roleids = array_keys($roles);

        $data = new \stdClass();
        $data->id                       = $courseid;
        $data->reset_start_date         = 0;
        $data->reset_events             = 1;
        $data->reset_notes              = 1;
        $data->delete_blog_associations = 1;
        $data->reset_completion         = 1;
        $data->reset_roles_overrides    = 1;
        $data->reset_roles_local        = 1;
        // Grade cleanup happens via enrol_self::unenrol_user(); grade_course_reset() warns on false when no items exist.
        $data->reset_gradebook_grades   = 0;
        $data->reset_gradebook_items    = 0;
        $data->reset_groups_remove      = 0;
        $data->reset_groupings_remove   = 0;
        $data->unenrol_users            = $roleids;

        return $data;
    }
}
