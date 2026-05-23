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
     * @param int    $teacherid  User ID of the responsible teacher.
     * @param int    $categoryid Moodle course category ID.
     * @param string $nameprefix Prefix used to name labs (e.g. "Lab EAD").
     * @return int New batch ID.
     */
    public function create_batch(
        string $name,
        int $teacherid,
        int $categoryid,
        string $nameprefix
    ): int {
        global $DB;

        $record = (object) [
            'name'        => $name,
            'teacherid'   => $teacherid,
            'categoryid'  => $categoryid,
            'nameprefix'  => $nameprefix,
            'timecreated' => time(),
        ];

        return $DB->insert_record('local_labvirtual_batches', $record);
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
     * Returns all batches ordered by creation date descending, with teacher name,
     * category name and lab count joined in a single query.
     *
     * @return \stdClass[] Indexed by batch ID.
     */
    public function list_batches(): array {
        global $DB;

        $sql = "SELECT b.id,
                       b.name,
                       b.teacherid,
                       b.categoryid,
                       b.nameprefix,
                       b.timecreated,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename,
                       cat.name AS categoryname,
                       COUNT(lc.id) AS labcount
                  FROM {local_labvirtual_batches} b
                  JOIN {user} u ON u.id = b.teacherid
                  JOIN {course_categories} cat ON cat.id = b.categoryid
             LEFT JOIN {local_labvirtual_courses} lc ON lc.batchid = b.id
              GROUP BY b.id,
                       b.name,
                       b.teacherid,
                       b.categoryid,
                       b.nameprefix,
                       b.timecreated,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename,
                       cat.name
              ORDER BY b.timecreated DESC";

        return $DB->get_records_sql($sql);
    }
}
