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
 * PHPUnit tests for maintenance_service.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_factory;
use local_virtuallab\local\maintenance_service;

/**
 * Tests for reset and delete operations, including cross-batch ownership checks.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class maintenance_service_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a batch with the given number of labs and returns IDs.
     *
     * @param int $labcount Number of labs to create.
     * @return array{batchid: int, courseids: int[]}
     */
    private function create_batch_with_labs(int $labcount = 2): array {
        $user     = $this->getDataGenerator()->create_user();
        $mgr      = new batch_manager();
        $batchid  = $mgr->create_batch('Test Batch', [$user->id], 'Lab');
        $factory  = new course_factory();
        $courseids = $factory->create_labs($batchid, $labcount);
        return ['batchid' => $batchid, 'courseids' => $courseids];
    }

    /**
     * Resetting a lab updates lastreset and leaves the course intact.
     */
    public function test_reset_lab_updates_lastreset(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);

        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $this->assertEquals(0, (int) $lab->lastreset);

        $service = new maintenance_service();
        $service->reset_lab((int) $lab->id, $batchid);

        $updated = $DB->get_record('local_virtuallab_courses', ['id' => $lab->id]);
        $this->assertGreaterThan(0, (int) $updated->lastreset);
        $this->assertTrue($DB->record_exists('course', ['id' => $lab->courseid]));
    }

    /**
     * Resetting a lab restores the course fullname and shortname a student renamed.
     */
    public function test_reset_lab_restores_original_name(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);

        $lab           = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $originalname  = $lab->originalfullname;
        $originalshort = $lab->originalshortname;
        $this->assertNotEmpty($originalname);

        // Simulate a student renaming their sandbox course.
        $DB->set_field('course', 'fullname', 'Renamed by student', ['id' => $lab->courseid]);
        $DB->set_field('course', 'shortname', 'renamed-short', ['id' => $lab->courseid]);

        $service = new maintenance_service();
        $service->reset_lab((int) $lab->id, $batchid);

        $course = $DB->get_record('course', ['id' => $lab->courseid], 'fullname, shortname');
        $this->assertEquals($originalname, $course->fullname);
        $this->assertEquals($originalshort, $course->shortname);
    }

    /**
     * The shortname is not restored when another course already holds it, so the reset
     * cannot fail on the unique-shortname constraint.
     */
    public function test_reset_lab_keeps_shortname_when_taken(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);
        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);

        // Rename the lab, then let another course occupy the original shortname.
        $DB->set_field('course', 'shortname', 'renamed-short', ['id' => $lab->courseid]);
        $this->getDataGenerator()->create_course(['shortname' => $lab->originalshortname]);

        $service = new maintenance_service();
        $service->reset_lab((int) $lab->id, $batchid);

        $course = $DB->get_record('course', ['id' => $lab->courseid], 'fullname, shortname');
        $this->assertEquals('renamed-short', $course->shortname);
        $this->assertEquals($lab->originalfullname, $course->fullname);
    }

    /**
     * Resetting a lab with a wrong batchid throws because the ownership check fails.
     */
    public function test_reset_lab_wrong_batch_throws(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);
        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);

        $service = new maintenance_service();
        $this->expectException(\dml_missing_record_exception::class);
        $service->reset_lab((int) $lab->id, 99999);
    }

    /**
     * Deleting a lab removes the Moodle course and the registry row.
     */
    public function test_delete_lab_removes_course_and_registry(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);
        $lab      = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $courseid = (int) $lab->courseid;

        $service = new maintenance_service();
        $service->delete_lab((int) $lab->id, $batchid);

        $this->assertFalse($DB->record_exists('local_virtuallab_courses', ['id' => $lab->id]));
        $this->assertFalse($DB->record_exists('course', ['id' => $courseid]));
    }

    /**
     * Deleting a lab with a wrong batchid throws because the ownership check fails.
     */
    public function test_delete_lab_wrong_batch_throws(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);
        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);

        $service = new maintenance_service();
        $this->expectException(\dml_missing_record_exception::class);
        $service->delete_lab((int) $lab->id, 99999);
    }

    /**
     * Deleting a batch removes every lab course and the batch record itself.
     */
    public function test_delete_batch_removes_all_labs_and_batch(): void {
        global $DB;

        ['batchid' => $batchid, 'courseids' => $courseids] = $this->create_batch_with_labs(3);

        $service = new maintenance_service();
        $service->delete_batch($batchid);

        $this->assertFalse($DB->record_exists('local_virtuallab_batches', ['id' => $batchid]));
        $this->assertEquals(0, $DB->count_records('local_virtuallab_courses', ['batchid' => $batchid]));

        foreach ($courseids as $courseid) {
            $this->assertFalse($DB->record_exists('course', ['id' => $courseid]));
        }
    }
}
