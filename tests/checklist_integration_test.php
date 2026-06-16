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
 * PHPUnit integration tests for checklist_integration.
 *
 * These tests require block_teacher_checklist to be installed alongside this plugin
 * and are skipped automatically when that block is absent. In CI, the block is
 * configured as an extra-plugin so all tests run.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\batch_manager;
use local_virtuallab\local\checklist_integration;
use local_virtuallab\local\course_factory;
use local_virtuallab\local\task_manager;

/**
 * Tests for the integration bridge with block_teacher_checklist.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_virtuallab\local\checklist_integration
 */
final class checklist_integration_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        if (!checklist_integration::is_available()) {
            $this->markTestSkipped('block_teacher_checklist is not installed.');
        }
    }

    /**
     * Creates a minimal batch and returns its ID.
     *
     * @return int
     */
    private function create_batch(): int {
        $user = $this->getDataGenerator()->create_user();
        return (new batch_manager())->create_batch('Test Batch', [$user->id], 'Lab');
    }

    /**
     * is_available() returns true when the companion block is installed.
     */
    public function test_is_available_returns_true(): void {
        $this->assertTrue(checklist_integration::is_available());
    }

    /**
     * provision_course() seeds tasks into the checklist table with the correct source tag.
     */
    public function test_provision_course_seeds_tasks(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        (new checklist_integration())->provision_course($course->id, ['Task A', 'Task B']);

        $this->assertEquals(2, $DB->count_records('block_teacher_checklist', [
            'courseid' => $course->id,
            'subtype'  => 'local_virtuallab',
        ]));
    }

    /**
     * provision_course() is idempotent: a second call replaces, not appends.
     */
    public function test_provision_course_is_idempotent(): void {
        global $DB;

        $course       = $this->getDataGenerator()->create_course();
        $integration  = new checklist_integration();

        $integration->provision_course($course->id, ['Task A', 'Task B']);
        $integration->provision_course($course->id, ['Task A', 'Task B']);

        $this->assertEquals(2, $DB->count_records('block_teacher_checklist', [
            'courseid' => $course->id,
            'subtype'  => 'local_virtuallab',
        ]));
    }

    /**
     * provision_course() adds the teacher_checklist block to the course.
     */
    public function test_provision_course_adds_block(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $this->assertFalse($DB->record_exists('block_instances', [
            'blockname'       => 'teacher_checklist',
            'parentcontextid' => $context->id,
        ]), 'Block must not exist before provisioning.');

        (new checklist_integration())->provision_course($course->id, ['Task A']);

        $this->assertTrue($DB->record_exists('block_instances', [
            'blockname'       => 'teacher_checklist',
            'parentcontextid' => $context->id,
        ]), 'Block must exist after provisioning.');
    }

    /**
     * provision_course() does not add a second block when one is already present.
     */
    public function test_provision_course_does_not_duplicate_block(): void {
        global $DB;

        $course      = $this->getDataGenerator()->create_course();
        $context     = \context_course::instance($course->id);
        $integration = new checklist_integration();

        $integration->provision_course($course->id, ['Task A']);
        $integration->provision_course($course->id, ['Task A']);

        $this->assertEquals(1, $DB->count_records('block_instances', [
            'blockname'       => 'teacher_checklist',
            'parentcontextid' => $context->id,
        ]));
    }

    /**
     * sync_batch() provisions every lab in a batch and returns the lab count.
     */
    public function test_sync_batch_provisions_all_labs(): void {
        global $DB;

        $batchid = $this->create_batch();
        (new course_factory())->create_labs($batchid, 3);
        (new task_manager())->set_tasks($batchid, ['Task A', 'Task B']);

        $synced = (new checklist_integration())->sync_batch($batchid);

        $this->assertEquals(3, $synced);

        // All 3 labs × 2 tasks = 6 provisioned items.
        [$insql, $params] = $DB->get_in_or_equal(
            $DB->get_fieldset_select(
                'local_virtuallab_courses',
                'courseid',
                'batchid = :batchid',
                ['batchid' => $batchid]
            ),
            SQL_PARAMS_NAMED
        );
        $params['subtype'] = 'local_virtuallab';
        $total = $DB->count_records_select(
            'block_teacher_checklist',
            "courseid $insql AND subtype = :subtype",
            $params
        );
        $this->assertEquals(6, $total);
    }

    /**
     * sync_batch() with no tasks still returns the correct lab count without error.
     */
    public function test_sync_batch_with_no_tasks_returns_lab_count(): void {
        $batchid = $this->create_batch();
        (new course_factory())->create_labs($batchid, 2);

        $synced = (new checklist_integration())->sync_batch($batchid);

        $this->assertEquals(2, $synced);
    }
}
