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
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

use local_virtuallab\event\batch_deleted;
use local_virtuallab\event\course_deleted;
use local_virtuallab\event\course_reset;

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
     * @param int $labid   Row ID in local_virtuallab_courses.
     * @param int $batchid Batch the lab must belong to (ownership check).
     * @throws \moodle_exception If lab does not exist or belongs to a different batch.
     */
    public function reset_lab(int $labid, int $batchid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $lab = $DB->get_record_sql(
            "SELECT lc.* FROM {local_virtuallab_courses} lc
              WHERE lc.id = :id AND lc.batchid = :batchid",
            ['id' => $labid, 'batchid' => $batchid],
            MUST_EXIST
        );

        $resetdata = $this->build_reset_data($lab->courseid);
        reset_course_userdata($resetdata);

        // A reset returns the sandbox to its starting point, so restore the name and
        // shortname it had at creation in case a student renamed the course.
        $this->restore_course_name($lab);

        // Clearing lastwarn starts a fresh lifecycle cycle so a new warning is sent next time.
        $DB->update_record('local_virtuallab_courses', (object) [
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
     * Restores a lab course to the fullname and shortname captured at creation.
     *
     * The shortname is only restored when it is still free, so a name taken elsewhere
     * cannot make the reset fail on the unique-shortname constraint. Requires
     * course/lib.php to be loaded by the caller.
     *
     * @param \stdClass $lab Lab row carrying originalfullname and originalshortname.
     * @return void
     */
    private function restore_course_name(\stdClass $lab): void {
        global $DB;

        if (empty($lab->originalfullname) && empty($lab->originalshortname)) {
            return;
        }

        $course  = $DB->get_record('course', ['id' => $lab->courseid], 'id, fullname, shortname', MUST_EXIST);
        $changed = false;

        if (!empty($lab->originalfullname) && $course->fullname !== $lab->originalfullname) {
            $course->fullname = $lab->originalfullname;
            $changed = true;
        }

        $shortnametaken = !empty($lab->originalshortname) && $DB->record_exists_select(
            'course',
            'shortname = :shortname AND id <> :id',
            ['shortname' => $lab->originalshortname, 'id' => $course->id]
        );
        $restoreshortname = !empty($lab->originalshortname)
            && $course->shortname !== $lab->originalshortname
            && !$shortnametaken;

        if ($restoreshortname) {
            $course->shortname = $lab->originalshortname;
            $changed = true;
        }

        if ($changed) {
            update_course($course);
        }
    }

    /**
     * Deletes a single lab course: removes enrol instances, deletes the Moodle
     * course and removes the plugin registry row.
     *
     * @param int $labid   Row ID in local_virtuallab_courses.
     * @param int $batchid Batch the lab must belong to (ownership check).
     * @throws \moodle_exception If lab does not exist or belongs to a different batch.
     */
    public function delete_lab(int $labid, int $batchid): void {
        global $DB;

        $lab = $DB->get_record_sql(
            "SELECT lc.* FROM {local_virtuallab_courses} lc
              WHERE lc.id = :id AND lc.batchid = :batchid",
            ['id' => $labid, 'batchid' => $batchid],
            MUST_EXIST
        );

        $this->do_delete_lab($lab);
    }

    /**
     * Deletes a batch and all its labs: removes every lab course (and its enrolments),
     * all registry rows, and the batch record itself.
     *
     * @param int $batchid Batch ID.
     * @throws \dml_exception If the batch record does not exist.
     */
    public function delete_batch(int $batchid): void {
        global $DB;

        $batch = $DB->get_record('local_virtuallab_batches', ['id' => $batchid], 'id, categoryid', MUST_EXIST);

        $labs = $DB->get_records('local_virtuallab_courses', ['batchid' => $batchid]);

        if ($labs) {
            // Deleting the course also removes its enrolment instances; the loop is
            // unavoidable because delete_course() is a per-course Moodle API call.
            foreach ($labs as $lab) {
                $context = \context_course::instance($lab->courseid, IGNORE_MISSING);

                $event = course_deleted::create([
                    'objectid' => $lab->id,
                    'context'  => $context ?? \context_system::instance(),
                    'other'    => ['courseid' => $lab->courseid, 'batchid' => $batchid],
                ]);

                delete_course($lab->courseid, false);

                $event->trigger();
            }

            $DB->delete_records('local_virtuallab_courses', ['batchid' => $batchid]);
        }

        $DB->delete_records('local_virtuallab_batches', ['id' => $batchid]);

        // Remove the now-empty batch subcategory (its context and role assignments go with it).
        category_manager::delete_category((int) $batch->categoryid);

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
     * @param \stdClass $lab Row from local_virtuallab_courses.
     */
    private function do_delete_lab(\stdClass $lab): void {
        global $DB;

        $context = \context_course::instance($lab->courseid, IGNORE_MISSING);

        $event = course_deleted::create([
            'objectid' => $lab->id,
            'context'  => $context ?? \context_system::instance(),
            'other'    => ['courseid' => $lab->courseid, 'batchid' => $lab->batchid],
        ]);

        delete_course($lab->courseid, false);
        $DB->delete_records('local_virtuallab_courses', ['id' => $lab->id]);

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
        // Grade cleanup happens when users are unenrolled; grade_course_reset() warns on false when no items exist.
        $data->reset_gradebook_grades   = 0;
        $data->reset_gradebook_items    = 0;
        $data->reset_groups_remove      = 0;
        $data->reset_groupings_remove   = 0;
        $data->unenrol_users            = $roleids;

        return $data;
    }
}
