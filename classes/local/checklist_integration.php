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
 * Optional integration with the block_teacher_checklist plugin.
 *
 * Every reference to the companion plugin is funnelled through this class so the
 * coupling has a single place to maintain should the checklist plugin change. All
 * methods degrade silently when the plugin is not installed.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

/**
 * Bridges a batch task list into the teacher checklist block of each lab course.
 */
class checklist_integration {
    /** @var string Source key recorded against provisioned checklist items. */
    private const SOURCE = 'local_virtuallab';

    /** @var string Block region used when auto-adding the checklist block to a lab. */
    private const BLOCKREGION = 'side-post';

    /**
     * Whether the teacher checklist block and its seeding API are available.
     *
     * @return bool True if the companion plugin is installed.
     */
    public static function is_available(): bool {
        return class_exists('\\block_teacher_checklist\\local\\external_tasks');
    }

    /**
     * Seeds the given task list into a lab course and ensures the block is visible.
     *
     * Does nothing when the companion plugin is absent.
     *
     * @param int      $courseid Lab course ID.
     * @param string[] $titles   Task titles to provision.
     */
    public function provision_course(int $courseid, array $titles): void {
        if (!self::is_available()) {
            return;
        }

        \block_teacher_checklist\local\external_tasks::replace($courseid, self::SOURCE, $titles);
        $this->ensure_block($courseid);
    }

    /**
     * Re-provisions every existing lab of a batch with the batch task list.
     *
     * @param int $batchid Batch ID.
     * @return int Number of labs synchronised.
     */
    public function sync_batch(int $batchid): int {
        global $DB;

        if (!self::is_available()) {
            return 0;
        }

        $titles    = (new task_manager())->get_tasks($batchid);
        $courseids = $DB->get_fieldset_select(
            'local_virtuallab_courses',
            'courseid',
            'batchid = :batchid',
            ['batchid' => $batchid]
        );

        if (!$courseids) {
            return 0;
        }

        foreach ($courseids as $courseid) {
            \block_teacher_checklist\local\external_tasks::replace(
                (int) $courseid,
                self::SOURCE,
                $titles
            );
        }

        $this->ensure_blocks_bulk($courseids);

        return count($courseids);
    }

    /**
     * Adds the checklist block to every course in the list that does not yet have one.
     *
     * Uses two bulk queries to determine which courses need the block, then fetches
     * course records for those courses in a third bulk query before acting.
     *
     * @param int[]|string[] $courseids Lab course IDs to inspect.
     */
    private function ensure_blocks_bulk(array $courseids): void {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $inparams['ctxlevel'] = CONTEXT_COURSE;
        $ctxrecords = $DB->get_records_sql(
            "SELECT id, instanceid FROM {context} WHERE contextlevel = :ctxlevel AND instanceid $insql",
            $inparams
        );

        if (!$ctxrecords) {
            return;
        }

        $contextidtocourseid = [];
        foreach ($ctxrecords as $rec) {
            $contextidtocourseid[(int) $rec->id] = (int) $rec->instanceid;
        }

        [$ctxinsql, $ctxparams] = $DB->get_in_or_equal(
            array_keys($contextidtocourseid),
            SQL_PARAMS_NAMED
        );
        $ctxparams['blockname'] = 'teacher_checklist';
        $existingctxids = $DB->get_fieldset_select(
            'block_instances',
            'parentcontextid',
            "blockname = :blockname AND parentcontextid $ctxinsql",
            $ctxparams
        );

        $existingset = array_flip($existingctxids);
        $missingids  = [];
        foreach ($contextidtocourseid as $ctxid => $courseid) {
            if (!isset($existingset[$ctxid])) {
                $missingids[] = $courseid;
            }
        }

        if (!$missingids) {
            return;
        }

        [$cinsql, $cparams] = $DB->get_in_or_equal($missingids, SQL_PARAMS_NAMED);
        $courses = $DB->get_records_sql("SELECT * FROM {course} WHERE id $cinsql", $cparams);
        foreach ($courses as $course) {
            $page = new \moodle_page();
            $page->set_course($course);
            $page->blocks->add_blocks(
                [self::BLOCKREGION => ['teacher_checklist']],
                'course-view-*'
            );
        }
    }

    /**
     * Adds the teacher checklist block to a course unless it already has one.
     *
     * @param int $courseid Lab course ID.
     */
    private function ensure_block(int $courseid): void {
        global $DB;

        $context = \context_course::instance($courseid);

        $exists = $DB->record_exists('block_instances', [
            'blockname'       => 'teacher_checklist',
            'parentcontextid' => $context->id,
        ]);
        if ($exists) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $page   = new \moodle_page();
        $page->set_course($course);
        $page->blocks->add_blocks(
            [self::BLOCKREGION => ['teacher_checklist']],
            'course-view-*'
        );
    }
}
