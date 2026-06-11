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
 * Batch (turma) manager — CRUD operations for local_labvirtual_batches.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual\local;

/**
 * Handles creation and retrieval of Lab Virtual batches.
 */
class batch_manager {
    /**
     * Creates a new batch (turma) and returns its ID.
     *
     * @param string $name       Human-readable batch name.
     * @param int[]  $teacherids User IDs of the responsible teachers.
     * @param int    $categoryid Moodle course category ID.
     * @param string $nameprefix Prefix used to name labs (e.g. "Lab EAD").
     * @return int New batch ID.
     */
    public function create_batch(
        string $name,
        array $teacherids,
        int $categoryid,
        string $nameprefix
    ): int {
        global $DB;

        $record = (object) [
            'name'        => $name,
            'categoryid'  => $categoryid,
            'nameprefix'  => $nameprefix,
            'timecreated' => time(),
        ];

        $batchid = $DB->insert_record('local_labvirtual_batches', $record);
        $this->set_teachers($batchid, $teacherids);

        return $batchid;
    }

    /**
     * Returns a single batch record.
     *
     * @param int $id Batch ID.
     * @return \stdClass Batch record.
     * @throws \dml_exception If the record does not exist.
     */
    public function get_batch(int $id): \stdClass {
        global $DB;

        return $DB->get_record('local_labvirtual_batches', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Replaces the set of responsible teachers for a batch.
     *
     * @param int   $batchid    Batch ID.
     * @param int[] $teacherids User IDs to set as responsible teachers.
     */
    public function set_teachers(int $batchid, array $teacherids): void {
        global $DB;

        $DB->delete_records('local_labvirtual_batch_teachers', ['batchid' => $batchid]);

        $rows = [];
        foreach (array_unique(array_filter(array_map('intval', $teacherids))) as $userid) {
            $rows[] = (object) ['batchid' => $batchid, 'userid' => $userid];
        }

        if ($rows) {
            $DB->insert_records('local_labvirtual_batch_teachers', $rows);
        }
    }

    /**
     * Returns the responsible teachers of a batch as full user records.
     *
     * @param int $batchid Batch ID.
     * @return \stdClass[] User records indexed by user ID, ordered by name.
     */
    public function get_teachers(int $batchid): array {
        global $DB;

        $sql = "SELECT u.*
                  FROM {local_labvirtual_batch_teachers} bt
                  JOIN {user} u ON u.id = bt.userid
                 WHERE bt.batchid = :batchid
                   AND u.deleted = 0
              ORDER BY u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, ['batchid' => $batchid]);
    }

    /**
     * Returns all batches ordered by creation date descending, with category name,
     * lab count and a joined-names string of responsible teachers (teachernames).
     *
     * @return \stdClass[] Indexed by batch ID.
     */
    public function list_batches(): array {
        global $DB;

        $sql = "SELECT b.id,
                       b.name,
                       b.categoryid,
                       b.nameprefix,
                       b.timecreated,
                       cat.name AS categoryname,
                       COUNT(lc.id) AS labcount
                  FROM {local_labvirtual_batches} b
                  JOIN {course_categories} cat ON cat.id = b.categoryid
             LEFT JOIN {local_labvirtual_courses} lc ON lc.batchid = b.id
              GROUP BY b.id,
                       b.name,
                       b.categoryid,
                       b.nameprefix,
                       b.timecreated,
                       cat.name
              ORDER BY b.timecreated DESC";

        $batches = $DB->get_records_sql($sql);

        if ($batches) {
            $this->attach_teacher_names($batches);
        }

        return $batches;
    }

    /**
     * Adds a comma-separated teachernames string to each batch in one bulk query.
     *
     * @param \stdClass[] $batches Batches indexed by ID (modified in place).
     */
    private function attach_teacher_names(array $batches): void {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(array_keys($batches), SQL_PARAMS_NAMED);

        $sql = "SELECT bt.id,
                       bt.batchid,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename
                  FROM {local_labvirtual_batch_teachers} bt
                  JOIN {user} u ON u.id = bt.userid
                 WHERE bt.batchid $insql
                   AND u.deleted = 0
              ORDER BY u.lastname ASC, u.firstname ASC";

        $names = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $names[$row->batchid][] = fullname($row);
        }

        foreach ($batches as $batch) {
            $batch->teachernames = isset($names[$batch->id]) ? implode(', ', $names[$batch->id]) : '';
        }
    }
}
