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
 * Course registry — CRUD for local_virtuallab_courses.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

/**
 * Reads and validates lab course registrations.
 */
class course_registry {
    /**
     * Returns true if the given course ID is managed by Virtual Lab.
     *
     * @param int $courseid Moodle course ID.
     * @return bool
     */
    public function is_managed(int $courseid): bool {
        global $DB;

        return $DB->record_exists('local_virtuallab_courses', ['courseid' => $courseid]);
    }

    /**
     * Returns the lab record for the given lab row ID, validating it belongs to the given batch.
     *
     * @param int $labid   Row ID in local_virtuallab_courses (PK).
     * @param int $batchid Expected batch ID (ownership check).
     * @return \stdClass Lab record with coursename populated.
     * @throws \moodle_exception If the lab does not exist or belongs to a different batch.
     */
    public function get_lab_for_batch(int $labid, int $batchid): \stdClass {
        global $DB;

        $sql = "SELECT lc.*, c.fullname AS coursename, c.shortname
                  FROM {local_virtuallab_courses} lc
                  JOIN {course} c ON c.id = lc.courseid
                 WHERE lc.id      = :id
                   AND lc.batchid = :batchid";

        $record = $DB->get_record_sql($sql, ['id' => $labid, 'batchid' => $batchid]);

        if (!$record) {
            throw new \moodle_exception('error_course_not_managed', 'local_virtuallab');
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
                  FROM {local_virtuallab_courses} lc
                  JOIN {course} c ON c.id = lc.courseid
                 WHERE lc.batchid = :batchid
              ORDER BY lc.id ASC";

        return $DB->get_records_sql($sql, ['batchid' => $batchid]);
    }

    /**
     * Returns lab records for a given set of lab IDs, validated against a batch.
     *
     * Fetches all matching rows in one query. Used by bulk operations to pre-validate
     * ownership and avoid per-lab round-trips.
     *
     * @param int[] $labids  Array of local_virtuallab_courses row IDs to fetch.
     * @param int   $batchid Batch all labs must belong to.
     * @return \stdClass[] Indexed by lab ID. Only rows matching both labids and batchid are returned.
     */
    public function get_labs_for_batch_bulk(array $labids, int $batchid): array {
        global $DB;

        if (empty($labids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($labids, SQL_PARAMS_NAMED, 'labid');
        $params['batchid'] = $batchid;

        $sql = "SELECT lc.*, c.fullname AS coursename, c.shortname
                  FROM {local_virtuallab_courses} lc
                  JOIN {course} c ON c.id = lc.courseid
                 WHERE lc.id $insql
                   AND lc.batchid = :batchid
              ORDER BY lc.id ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns the lab for the given course, validating it belongs to the given batch.
     *
     * Used by the panel before enrolling, so a forged course ID cannot target a course
     * outside the batch.
     *
     * @param int $courseid Expected Moodle course ID.
     * @param int $batchid  Expected batch ID.
     * @return \stdClass The lab record, including its manual enrolid.
     * @throws \moodle_exception If the course does not belong to the batch.
     */
    public function get_lab_for_enrol(int $courseid, int $batchid): \stdClass {
        global $DB;

        $record = $DB->get_record('local_virtuallab_courses', [
            'courseid' => $courseid,
            'batchid'  => $batchid,
        ]);

        if (!$record) {
            throw new \moodle_exception('error_enrol_mismatch', 'local_virtuallab');
        }

        return $record;
    }
}
