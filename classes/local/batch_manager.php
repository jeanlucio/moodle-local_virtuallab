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
 * Batch manager — CRUD operations for local_virtuallab_batches.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

/**
 * Handles creation and retrieval of Virtual Lab batches.
 */
class batch_manager {
    /**
     * Creates a new batch in its own subcategory and returns its ID.
     *
     * @param string $name       Human-readable batch name.
     * @param int[]  $teacherids User IDs of the responsible teachers.
     * @param string $nameprefix Prefix used to name labs (e.g. "Lab"); set later by the teacher.
     * @return int New batch ID.
     */
    public function create_batch(
        string $name,
        array $teacherids,
        string $nameprefix = ''
    ): int {
        global $DB;

        $categoryid = category_manager::create_batch_category($name);

        $record = (object) [
            'name'        => $name,
            'categoryid'  => $categoryid,
            'nameprefix'  => $nameprefix,
            'timecreated' => time(),
        ];

        $batchid = $DB->insert_record('local_virtuallab_batches', $record);
        $this->set_teachers($batchid, $teacherids);

        return $batchid;
    }

    /**
     * Updates a batch: name (renaming its subcategory), teachers, prefix and setting overrides.
     *
     * @param int    $batchid    Batch ID.
     * @param string $name       New batch name.
     * @param int[]  $teacherids Responsible teacher user IDs.
     * @param string $nameprefix Lab name prefix.
     * @param array  $settings   Override values keyed by maxteachers, lifecyclemonths,
     *                           lifecycleaction and warningdays; empty or null means inherit.
     */
    public function update_batch(
        int $batchid,
        string $name,
        array $teacherids,
        string $nameprefix,
        array $settings = []
    ): void {
        global $DB;

        $batch = $this->get_batch($batchid);

        $record = (object) [
            'id'         => $batchid,
            'name'       => $name,
            'nameprefix' => $nameprefix,
        ];
        foreach (['maxteachers', 'lifecyclemonths', 'lifecycleaction', 'warningdays'] as $key) {
            $value = $settings[$key] ?? null;
            $record->$key = ($value === null || $value === '') ? null : (int) $value;
        }

        // Changing the lifecycle policy restarts the clock from today for this batch, so
        // existing labs are never made instantly overdue by a stricter setting.
        $oldmonths = $batch->lifecyclemonths === null ? null : (int) $batch->lifecyclemonths;
        $oldaction = $batch->lifecycleaction === null ? null : (int) $batch->lifecycleaction;
        if ($record->lifecyclemonths !== $oldmonths || $record->lifecycleaction !== $oldaction) {
            $record->lifecyclestart = time();
        }

        $DB->update_record('local_virtuallab_batches', $record);

        if ($name !== $batch->name) {
            category_manager::rename_category((int) $batch->categoryid, $name);
        }

        $this->set_teachers($batchid, $teacherids);
    }

    /**
     * Sets the lab name prefix of a batch.
     *
     * @param int    $batchid    Batch ID.
     * @param string $nameprefix Lab name prefix.
     */
    public function set_prefix(int $batchid, string $nameprefix): void {
        global $DB;

        $DB->set_field('local_virtuallab_batches', 'nameprefix', $nameprefix, ['id' => $batchid]);
    }

    /**
     * Returns the category context of a batch (its own subcategory).
     *
     * @param int $batchid Batch ID.
     * @return \context_coursecat
     */
    public function get_batch_context(int $batchid): \context_coursecat {
        $batch = $this->get_batch($batchid);

        return \context_coursecat::instance($batch->categoryid);
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

        return $DB->get_record('local_virtuallab_batches', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Replaces the set of responsible teachers for a batch and notifies newly-added ones.
     *
     * @param int   $batchid    Batch ID.
     * @param int[] $teacherids User IDs to set as responsible teachers.
     */
    public function set_teachers(int $batchid, array $teacherids): void {
        global $DB;

        $teacherids = array_values(array_unique(array_filter(array_map('intval', $teacherids))));

        $existingids = array_map('intval', array_values(
            $DB->get_fieldset_select('local_virtuallab_batch_teachers', 'userid', 'batchid = :batchid', ['batchid' => $batchid])
        ));
        $newteacherids = array_values(array_diff($teacherids, $existingids));

        $DB->delete_records('local_virtuallab_batch_teachers', ['batchid' => $batchid]);

        $rows = [];
        foreach ($teacherids as $userid) {
            $rows[] = (object) ['batchid' => $batchid, 'userid' => $userid];
        }

        if ($rows) {
            $DB->insert_records('local_virtuallab_batch_teachers', $rows);
        }

        $this->sync_teacher_roles($batchid, $teacherids);

        if ($newteacherids) {
            $this->notify_new_teachers($batchid, $newteacherids);
        }
    }

    /**
     * Sends a Moodle message to each newly-assigned teacher with a direct link to manage.php.
     *
     * @param int   $batchid       Batch ID they were just assigned to.
     * @param int[] $newteacherids User IDs of the teachers newly added in this operation.
     */
    private function notify_new_teachers(int $batchid, array $newteacherids): void {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($newteacherids, SQL_PARAMS_NAMED);
        $users = $DB->get_records_select('user', "id $insql AND deleted = 0 AND suspended = 0", $params);

        if (empty($users)) {
            return;
        }

        $batch    = $this->get_batch($batchid);
        $url      = new \moodle_url('/local/virtuallab/manage.php');
        $from     = \core_user::get_noreply_user();
        $subject  = get_string('message_batch_assigned_subject', 'local_virtuallab', format_string($batch->name));
        $bodyp1   = get_string('message_batch_assigned_body', 'local_virtuallab', format_string($batch->name));
        $bodyp2   = \html_writer::link($url, get_string('manage_batches', 'local_virtuallab'));
        $bodyhtml = \html_writer::tag('p', $bodyp1) . \html_writer::tag('p', $bodyp2);
        $bodytext = html_to_text($bodyhtml);

        foreach ($users as $user) {
            $message = new \core\message\message();
            $message->component = 'local_virtuallab';
            $message->name = 'batch_assigned';
            $message->userfrom = $from;
            $message->userto = $user;
            $message->subject = $subject;
            $message->fullmessage = $bodytext;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $bodyhtml;
            $message->smallmessage = get_string('message_batch_assigned_small', 'local_virtuallab');
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('manage_batches', 'local_virtuallab');
            message_send($message);
        }
    }

    /**
     * Synchronises the delegated batch-manager role assignments with the teacher list,
     * at the batch subcategory context.
     *
     * @param int   $batchid    Batch ID.
     * @param int[] $teacherids Current responsible teacher user IDs.
     */
    private function sync_teacher_roles(int $batchid, array $teacherids): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/lib/accesslib.php');

        $categoryid = (int) $DB->get_field('local_virtuallab_batches', 'categoryid', ['id' => $batchid]);
        $context    = $categoryid ? \context_coursecat::instance($categoryid, IGNORE_MISSING) : false;
        if (!$context) {
            return;
        }

        $roleid = role_provisioner::get_role_id();

        role_unassign_all([
            'roleid'    => $roleid,
            'contextid' => $context->id,
            'component' => 'local_virtuallab',
            'itemid'    => $batchid,
        ]);

        foreach ($teacherids as $userid) {
            role_assign($roleid, $userid, $context->id, 'local_virtuallab', $batchid);
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
                  FROM {local_virtuallab_batch_teachers} bt
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
                  FROM {local_virtuallab_batches} b
                  JOIN {course_categories} cat ON cat.id = b.categoryid
             LEFT JOIN {local_virtuallab_courses} lc ON lc.batchid = b.id
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
            $batches = $this->filter_manageable($batches);
        }

        return $batches;
    }

    /**
     * Keeps only the batches the current user may manage: all of them for a system-level
     * manager (admin), otherwise just those whose subcategory grants them :manage.
     *
     * @param \stdClass[] $batches Batches indexed by ID.
     * @return \stdClass[] Filtered batches.
     */
    private function filter_manageable(array $batches): array {
        if (has_capability('local/virtuallab:manage', \context_system::instance())) {
            return $batches;
        }

        return array_filter($batches, static function (\stdClass $batch): bool {
            $context = \context_coursecat::instance($batch->categoryid, IGNORE_MISSING);

            return $context && has_capability('local/virtuallab:manage', $context);
        });
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
                  FROM {local_virtuallab_batch_teachers} bt
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
