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
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual;

use advanced_testcase;
use local_labvirtual\local\batch_manager;
use local_labvirtual\local\course_factory;
use local_labvirtual\local\maintenance_service;

/**
 * Tests for reset and delete operations, including cross-batch ownership checks.
 *
 * @package    local_labvirtual
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
        $category = $this->getDataGenerator()->create_category();
        $mgr      = new batch_manager();
        $batchid  = $mgr->create_batch('Test Batch', [$user->id], $category->id, 'Lab');
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

        $lab = $DB->get_record('local_labvirtual_courses', ['batchid' => $batchid]);
        $this->assertEquals(0, (int) $lab->lastreset);

        $service = new maintenance_service();
        $service->reset_lab((int) $lab->id, $batchid);

        $updated = $DB->get_record('local_labvirtual_courses', ['id' => $lab->id]);
        $this->assertGreaterThan(0, (int) $updated->lastreset);
        $this->assertTrue($DB->record_exists('course', ['id' => $lab->courseid]));
    }

    /**
     * Resetting a lab with a wrong batchid throws because the ownership check fails.
     */
    public function test_reset_lab_wrong_batch_throws(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);
        $lab = $DB->get_record('local_labvirtual_courses', ['batchid' => $batchid]);

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
        $lab      = $DB->get_record('local_labvirtual_courses', ['batchid' => $batchid]);
        $courseid = (int) $lab->courseid;

        $service = new maintenance_service();
        $service->delete_lab((int) $lab->id, $batchid);

        $this->assertFalse($DB->record_exists('local_labvirtual_courses', ['id' => $lab->id]));
        $this->assertFalse($DB->record_exists('course', ['id' => $courseid]));
    }

    /**
     * Deleting a lab with a wrong batchid throws because the ownership check fails.
     */
    public function test_delete_lab_wrong_batch_throws(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);
        $lab = $DB->get_record('local_labvirtual_courses', ['batchid' => $batchid]);

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

        $this->assertFalse($DB->record_exists('local_labvirtual_batches', ['id' => $batchid]));
        $this->assertEquals(0, $DB->count_records('local_labvirtual_courses', ['batchid' => $batchid]));

        foreach ($courseids as $courseid) {
            $this->assertFalse($DB->record_exists('course', ['id' => $courseid]));
        }
    }
}
