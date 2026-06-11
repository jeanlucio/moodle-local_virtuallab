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
 * PHPUnit tests for course_factory.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual;

use advanced_testcase;
use local_labvirtual\local\batch_manager;
use local_labvirtual\local\course_factory;

/**
 * Tests for bulk lab creation: courses, enrolment instances and registry rows.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class course_factory_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a batch and returns its ID alongside a freshly-created category and user.
     *
     * @return array{batchid: int, categoryid: int}
     */
    private function create_batch(): array {
        $user     = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $mgr      = new batch_manager();
        $batchid  = $mgr->create_batch('Test Batch', [$user->id], $category->id, 'Lab');
        return ['batchid' => $batchid, 'categoryid' => $category->id];
    }

    /**
     * create_labs creates exactly N courses and N registry rows for the batch.
     */
    public function test_create_labs_creates_correct_number(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch();

        $factory    = new course_factory();
        $courseids  = $factory->create_labs($batchid, 3);

        $this->assertCount(3, $courseids);
        $this->assertEquals(3, $DB->count_records('local_labvirtual_courses', ['batchid' => $batchid]));

        foreach ($courseids as $courseid) {
            $this->assertTrue($DB->record_exists('course', ['id' => $courseid]));
        }
    }

    /**
     * Each lab gets exactly two enrol_self instances, one per role (editingteacher + student).
     */
    public function test_create_labs_creates_two_enrol_instances_per_lab(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch();

        $factory   = new course_factory();
        $courseids = $factory->create_labs($batchid, 2);

        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);

        foreach ($courseids as $courseid) {
            $enrols = $DB->get_records('enrol', ['courseid' => $courseid, 'enrol' => 'self']);
            $this->assertCount(2, $enrols, "Course $courseid should have exactly 2 enrol_self instances.");

            $roleids = array_column((array) $enrols, 'roleid');
            $this->assertContains((string) $teacherroleid, $roleids);
            $this->assertContains((string) $studentroleid, $roleids);
        }
    }

    /**
     * Self-enrolment via the standard course form is disabled (newenrols = 0) and no
     * enrolment key is stored; the panel enrols programmatically instead.
     */
    public function test_create_labs_disables_self_enrolment(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch();

        $factory = new course_factory();
        $factory->create_labs($batchid, 1);

        $lab     = $DB->get_record('local_labvirtual_courses', ['batchid' => $batchid]);
        $teacher = $DB->get_record('enrol', ['id' => $lab->teacher_enrolid]);
        $student = $DB->get_record('enrol', ['id' => $lab->student_enrolid]);

        $this->assertEmpty($teacher->password);
        $this->assertEmpty($student->password);
        $this->assertEquals(0, (int) $teacher->customint6);
        $this->assertEquals(0, (int) $student->customint6);
    }

    /**
     * The returned array contains every created course ID and each maps to a registry row.
     */
    public function test_create_labs_returns_all_registered_course_ids(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch();

        $factory   = new course_factory();
        $courseids = $factory->create_labs($batchid, 4);

        foreach ($courseids as $courseid) {
            $this->assertTrue(
                $DB->record_exists('local_labvirtual_courses', ['batchid' => $batchid, 'courseid' => $courseid])
            );
        }
    }
}
