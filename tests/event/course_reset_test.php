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

namespace local_virtuallab\event;

use advanced_testcase;

/**
 * Tests that restoring a backup with logs treats the ids of the course_reset event correctly.
 *
 * Without a mapping the core prints a debugging message for every restored log record and copies the
 * ids as they were, which may name another course, batch or lab in the target site. The check is end
 * to end: a real event, a real backup with logs and a real restore into a second course.
 *
 * @package    local_virtuallab
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_virtuallab\event\course_reset
 */
final class course_reset_test extends advanced_testcase {
    /**
     * Backs the course up with its logs and restores it into a new course.
     *
     * @param \stdClass $course Source course.
     * @return \stdClass The course the backup was restored into.
     */
    private function backup_with_logs_and_restore(\stdClass $course): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $admin = get_admin();
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id
        );
        $bc->get_plan()->get_setting('logs')->set_value(true);
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $newcourse = $this->getDataGenerator()->create_course();
        $tempdir = \restore_controller::get_tempdir_name($newcourse->id, $admin->id);
        $backupfile->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($tempdir)
        );
        $rc = new \restore_controller(
            $tempdir,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return $newcourse;
    }

    /**
     * The restored record points at the restored course, and the lab and batch ids are marked as not mapped.
     */
    public function test_restored_log_points_at_the_restored_course(): void {
        global $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        // Triggered by an enrolled teacher: a log record whose user is not in the backup is skipped on
        // restore, with or without a mapping.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);
        course_reset::create([
            'objectid' => $course->id,
            'context'  => \context_course::instance($course->id),
            'other'    => ['batchid' => 7, 'labid' => 9],
        ])->trigger();
        $this->setAdminUser();

        $newcourse = $this->backup_with_logs_and_restore($course);

        $this->assertNotEquals($course->id, $newcourse->id);
        $restored = $DB->get_record('logstore_standard_log', [
            'eventname' => '\\local_virtuallab\\event\\course_reset',
            'courseid'  => $newcourse->id,
        ], '*', MUST_EXIST);
        $this->assertEquals($newcourse->id, $restored->objectid);
        $other = \tool_log\local\privacy\helper::decode_other($restored->other);
        $this->assertEquals(\core\event\base::NOT_MAPPED, $other['batchid']);
        $this->assertEquals(\core\event\base::NOT_MAPPED, $other['labid']);
    }
}
