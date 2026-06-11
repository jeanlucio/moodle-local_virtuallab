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
 * PHPUnit tests for course_registry.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual;

use advanced_testcase;
use local_labvirtual\local\batch_manager;
use local_labvirtual\local\course_factory;
use local_labvirtual\local\course_registry;

/**
 * Tests for course registry: ownership checks, bulk lookup and enrol validation.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class course_registry_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a batch with the given number of labs and returns useful IDs.
     *
     * @param int $labcount Number of labs to create.
     * @return array{batchid: int, courseids: int[]}
     */
    private function create_batch_with_labs(int $labcount = 2): array {
        $user     = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $mgr      = new batch_manager();
        $batchid  = $mgr->create_batch('Registry Test Batch', [$user->id], $category->id, 'Lab');
        $factory  = new course_factory();
        $courseids = $factory->create_labs($batchid, $labcount);
        return ['batchid' => $batchid, 'courseids' => $courseids];
    }

    /**
     * is_managed returns true for a course registered by the plugin.
     */
    public function test_is_managed_returns_true_for_managed_course(): void {
        ['courseids' => $courseids] = $this->create_batch_with_labs(1);

        $registry = new course_registry();
        $this->assertTrue($registry->is_managed($courseids[0]));
    }

    /**
     * is_managed returns false for a course that was never registered.
     */
    public function test_is_managed_returns_false_for_unknown_course(): void {
        $registry = new course_registry();
        $this->assertFalse($registry->is_managed(99999));
    }

    /**
     * get_lab_for_batch returns the correct record with coursename populated.
     */
    public function test_get_lab_for_batch_returns_record_with_coursename(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);
        $lab = $DB->get_record('local_labvirtual_courses', ['batchid' => $batchid]);

        $registry = new course_registry();
        $result   = $registry->get_lab_for_batch((int) $lab->id, $batchid);

        $this->assertEquals((int) $lab->id, (int) $result->id);
        $this->assertEquals((int) $lab->courseid, (int) $result->courseid);
        $this->assertNotEmpty($result->coursename);
    }

    /**
     * get_lab_for_batch throws when the lab belongs to a different batch.
     */
    public function test_get_lab_for_batch_wrong_batch_throws(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);
        $lab = $DB->get_record('local_labvirtual_courses', ['batchid' => $batchid]);

        $registry = new course_registry();
        $this->expectException(\moodle_exception::class);
        $registry->get_lab_for_batch((int) $lab->id, 99999);
    }

    /**
     * get_lab_for_batch throws when the lab ID does not exist at all.
     */
    public function test_get_lab_for_batch_nonexistent_throws(): void {
        $registry = new course_registry();
        $this->expectException(\moodle_exception::class);
        $registry->get_lab_for_batch(99999, 99999);
    }

    /**
     * get_labs_for_batch_bulk returns only labs that belong to the given batch.
     */
    public function test_get_labs_for_batch_bulk_filters_cross_batch(): void {
        global $DB;

        ['batchid' => $batch1] = $this->create_batch_with_labs(2);
        ['batchid' => $batch2] = $this->create_batch_with_labs(1);

        $allids = array_column(
            $DB->get_records('local_labvirtual_courses', [], 'id ASC', 'id'),
            null,
            'id'
        );
        $allids = array_map('intval', array_keys($allids));

        $registry = new course_registry();
        $result   = $registry->get_labs_for_batch_bulk($allids, $batch1);

        // Only the 2 labs from batch1 should be returned.
        $this->assertCount(2, $result);
        foreach ($result as $row) {
            $this->assertEquals($batch1, (int) $row->batchid);
        }
    }

    /**
     * get_labs_for_batch_bulk returns an empty array when given an empty ID list.
     */
    public function test_get_labs_for_batch_bulk_empty_input_returns_empty(): void {
        ['batchid' => $batchid] = $this->create_batch_with_labs(1);

        $registry = new course_registry();
        $result   = $registry->get_labs_for_batch_bulk([], $batchid);

        $this->assertEmpty($result);
    }

    /**
     * get_lab_for_enrol returns the lab when the course belongs to the batch.
     */
    public function test_get_lab_for_enrol_returns_lab(): void {
        global $DB;

        ['batchid' => $batchid] = $this->create_batch_with_labs(1);
        $lab = $DB->get_record('local_labvirtual_courses', ['batchid' => $batchid]);

        $registry = new course_registry();
        $result   = $registry->get_lab_for_enrol((int) $lab->courseid, $batchid);

        $this->assertEquals((int) $lab->id, (int) $result->id);
        $this->assertEquals((int) $lab->enrolid, (int) $result->enrolid);
    }

    /**
     * get_lab_for_enrol throws when the course belongs to a different batch.
     */
    public function test_get_lab_for_enrol_rejects_cross_batch(): void {
        global $DB;

        ['batchid' => $batch1] = $this->create_batch_with_labs(1);
        ['batchid' => $batch2] = $this->create_batch_with_labs(1);
        $lab2 = $DB->get_record('local_labvirtual_courses', ['batchid' => $batch2]);

        $registry = new course_registry();
        $this->expectException(\moodle_exception::class);
        $registry->get_lab_for_enrol((int) $lab2->courseid, $batch1);
    }

    /**
     * get_lab_for_enrol throws when the course is not managed at all.
     */
    public function test_get_lab_for_enrol_rejects_unknown_course(): void {
        ['batchid' => $batchid] = $this->create_batch_with_labs(1);

        $registry = new course_registry();
        $this->expectException(\moodle_exception::class);
        $registry->get_lab_for_enrol(99999, $batchid);
    }
}
