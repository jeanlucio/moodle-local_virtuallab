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
 * Course registry — CRUD for local_labvirtual_courses.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual\local;

/**
 * Reads and validates lab course registrations.
 */
class course_registry {
    /**
     * Returns true if the given course ID is managed by Lab Virtual.
     *
     * @param int $courseid Moodle course ID.
     * @return bool
     */
    public function is_managed(int $courseid): bool {
        global $DB;

        return $DB->record_exists('local_labvirtual_courses', ['courseid' => $courseid]);
    }

    /**
     * Returns the lab record for the given course ID, validating it belongs to the given batch.
     *
     * @param int $courseid Moodle course ID.
     * @param int $batchid  Expected batch ID (ownership check).
     * @return \stdClass Lab record.
     * @throws \moodle_exception If the course is not managed or does not belong to the batch.
     */
    public function get_lab_for_batch(int $courseid, int $batchid): \stdClass {
        global $DB;

        $sql = "SELECT lc.*
                  FROM {local_labvirtual_courses} lc
                 WHERE lc.courseid = :courseid
                   AND lc.batchid  = :batchid";

        $record = $DB->get_record_sql($sql, ['courseid' => $courseid, 'batchid' => $batchid]);

        if (!$record) {
            throw new \moodle_exception('error_course_not_managed', 'local_labvirtual');
        }

        return $record;
    }

    /**
     * Returns all lab records for a given batch, ordered by creation time.
     *
     * @param int $batchid Batch ID.
     * @return \stdClass[] Array of lab records indexed by ID.
     */
    public function list_labs(int $batchid): array {
        global $DB;

        $sql = "SELECT lc.*, c.fullname AS coursename, c.shortname
                  FROM {local_labvirtual_courses} lc
                  JOIN {course} c ON c.id = lc.courseid
                 WHERE lc.batchid = :batchid
              ORDER BY lc.id ASC";

        return $DB->get_records_sql($sql, ['batchid' => $batchid]);
    }

    /**
     * Validates that the given enrolment instance belongs to the given course in this batch.
     *
     * Used to prevent cross-course or cross-batch enrolment injection.
     *
     * @param int $enrolid   Enrolment instance ID to validate.
     * @param int $courseid  Expected Moodle course ID.
     * @param int $batchid   Expected batch ID.
     * @return \stdClass The lab record.
     * @throws \moodle_exception If validation fails.
     */
    public function validate_enrol_instance(int $enrolid, int $courseid, int $batchid): \stdClass {
        global $DB;

        $sql = "SELECT lc.*
                  FROM {local_labvirtual_courses} lc
                 WHERE lc.courseid = :courseid
                   AND lc.batchid  = :batchid
                   AND (lc.teacher_enrolid = :eid1 OR lc.student_enrolid = :eid2)";

        $record = $DB->get_record_sql($sql, [
            'courseid' => $courseid,
            'batchid'  => $batchid,
            'eid1'     => $enrolid,
            'eid2'     => $enrolid,
        ]);

        if (!$record) {
            throw new \moodle_exception('error_enrol_mismatch', 'local_labvirtual');
        }

        return $record;
    }
}
