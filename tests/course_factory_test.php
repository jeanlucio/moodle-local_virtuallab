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
     * Creates a batch and returns its ID.
     *
     * @return array{batchid: int}
     */
    private function create_batch(): array {
        $user     = $this->getDataGenerator()->create_user();
        $mgr      = new batch_manager();
        $batchid  = $mgr->create_batch('Test Batch', [$user->id], 'Lab');
        return ['batchid' => $batchid];
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
     * Each lab records the course manual enrolment instance and keeps no self-enrolment instance.
     */
    public function test_create_labs_uses_manual_enrolment(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch();

        $factory   = new course_factory();
        $courseids = $factory->create_labs($batchid, 2);

        foreach ($courseids as $courseid) {
            $lab    = $DB->get_record('local_labvirtual_courses', ['courseid' => $courseid]);
            $manual = $DB->get_record('enrol', ['id' => $lab->enrolid], '*', MUST_EXIST);

            $this->assertSame('manual', $manual->enrol);
            $this->assertEquals($courseid, (int) $manual->courseid);
            $this->assertFalse(
                $DB->record_exists('enrol', ['courseid' => $courseid, 'enrol' => 'self']),
                "Course $courseid should have no self-enrolment instance."
            );
        }
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
