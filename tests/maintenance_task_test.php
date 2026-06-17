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
 * PHPUnit tests for maintenance_task.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_factory;
use local_virtuallab\task\maintenance_task;

/**
 * Tests for the lifecycle scheduled task: disabled states, reset, delete, and date logic.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class maintenance_task_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a batch with the given number of labs and returns their IDs.
     *
     * @param int $labcount Number of labs to create.
     * @return array{batchid: int, courseids: int[]}
     */
    private function create_batch_with_labs(int $labcount = 1): array {
        $user     = $this->getDataGenerator()->create_user();
        $mgr      = new batch_manager();
        $batchid  = $mgr->create_batch('Task Test Batch', [$user->id], 'Lab');
        $factory  = new course_factory();
        $courseids = $factory->create_labs($batchid, $labcount);
        return ['batchid' => $batchid, 'courseids' => $courseids];
    }

    /**
     * Sets timecreated of all labs in a batch to $months months in the past.
     *
     * @param int $batchid Target batch.
     * @param int $months  How many months back to set the timestamp.
     */
    private function backdate_labs(int $batchid, int $months): void {
        global $DB;
        $past = mktime(0, 0, 0, (int)date('n') - $months, (int)date('j'), (int)date('Y'));
        $DB->set_field('local_virtuallab_courses', 'timecreated', $past, ['batchid' => $batchid]);
    }

    /**
     * Runs the task, suppressing mtrace output.
     */
    private function run_task(): void {
        ob_start();
        (new maintenance_task())->execute();
        ob_end_clean();
    }

    /**
     * The task does nothing when lifecycle_months = 0 (feature disabled).
     */
    public function test_execute_does_nothing_when_months_zero(): void {
        global $DB;

        set_config('lifecycle_months', 0, 'local_virtuallab');
        set_config('lifecycle_action', 1, 'local_virtuallab');

        ['batchid' => $batchid] = $this->create_batch_with_labs();
        $this->backdate_labs($batchid, 12);

        $this->run_task();

        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $this->assertEquals(0, (int) $lab->lastreset);
    }

    /**
     * The task does nothing when lifecycle_action = 0 (action disabled).
     */
    public function test_execute_does_nothing_when_action_zero(): void {
        global $DB;

        set_config('lifecycle_months', 6, 'local_virtuallab');
        set_config('lifecycle_action', 0, 'local_virtuallab');

        ['batchid' => $batchid] = $this->create_batch_with_labs();
        $this->backdate_labs($batchid, 12);

        $this->run_task();

        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $this->assertEquals(0, (int) $lab->lastreset);
    }

    /**
     * A per-batch override drives the action even when the global lifecycle is disabled.
     */
    public function test_execute_uses_per_batch_override(): void {
        global $DB;

        set_config('lifecycle_months', 0, 'local_virtuallab');
        set_config('lifecycle_action', 0, 'local_virtuallab');

        ['batchid' => $batchid] = $this->create_batch_with_labs();
        $this->backdate_labs($batchid, 7);

        $DB->set_field('local_virtuallab_batches', 'lifecyclemonths', 6, ['id' => $batchid]);
        $DB->set_field(
            'local_virtuallab_batches',
            'lifecycleaction',
            maintenance_task::ACTION_RESET,
            ['id' => $batchid]
        );

        $this->run_task();

        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $this->assertGreaterThan(0, (int) $lab->lastreset, 'The per-batch override should trigger the reset.');
    }

    /**
     * An overdue lab is reset (lastreset updated, course kept) when action = reset.
     */
    public function test_execute_resets_overdue_lab(): void {
        global $DB;

        set_config('lifecycle_months', 6, 'local_virtuallab');
        set_config('lifecycle_action', maintenance_task::ACTION_RESET, 'local_virtuallab');

        ['batchid' => $batchid, 'courseids' => $courseids] = $this->create_batch_with_labs();
        $this->backdate_labs($batchid, 7);

        $this->run_task();

        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $this->assertGreaterThan(0, (int) $lab->lastreset, 'lastreset should have been updated.');
        $this->assertTrue($DB->record_exists('course', ['id' => $courseids[0]]), 'Course should still exist after reset.');
    }

    /**
     * An overdue lab is deleted (course and registry row removed) when action = delete.
     */
    public function test_execute_deletes_overdue_lab(): void {
        global $DB;

        set_config('lifecycle_months', 6, 'local_virtuallab');
        set_config('lifecycle_action', maintenance_task::ACTION_DELETE, 'local_virtuallab');

        ['batchid' => $batchid, 'courseids' => $courseids] = $this->create_batch_with_labs();
        $this->backdate_labs($batchid, 7);

        $this->run_task();

        $this->assertFalse($DB->record_exists('local_virtuallab_courses', ['batchid' => $batchid]));
        $this->assertFalse($DB->record_exists('course', ['id' => $courseids[0]]));
    }

    /**
     * A lab created recently (within the lifecycle window) is not touched by the task.
     */
    public function test_execute_skips_recent_lab(): void {
        global $DB;

        set_config('lifecycle_months', 6, 'local_virtuallab');
        set_config('lifecycle_action', maintenance_task::ACTION_RESET, 'local_virtuallab');

        ['batchid' => $batchid] = $this->create_batch_with_labs();
        // No backdating: timecreated is now, which is within the 6-month window.

        $this->run_task();

        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $this->assertEquals(0, (int) $lab->lastreset, 'Recent lab should not have been reset.');
    }

    /**
     * The task uses lastreset (not timecreated) when lastreset > 0, so a recently-reset
     * lab with an old timecreated is not processed again.
     */
    public function test_execute_uses_lastreset_over_timecreated(): void {
        global $DB;

        set_config('lifecycle_months', 6, 'local_virtuallab');
        set_config('lifecycle_action', maintenance_task::ACTION_RESET, 'local_virtuallab');

        ['batchid' => $batchid] = $this->create_batch_with_labs();
        $this->backdate_labs($batchid, 12);

        $recentreset = time() - DAYSECS;
        $DB->set_field('local_virtuallab_courses', 'lastreset', $recentreset, ['batchid' => $batchid]);

        $this->run_task();

        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $this->assertEquals($recentreset, (int) $lab->lastreset, 'lastreset should not have changed.');
    }

    /**
     * A lab sitting in the warning window (due within warning_days_before) is warned
     * but not actioned: lastwarn is set and the teacher receives an email.
     */
    public function test_execute_warns_lab_in_window(): void {
        global $DB;

        set_config('lifecycle_months', 6, 'local_virtuallab');
        set_config('lifecycle_action', maintenance_task::ACTION_RESET, 'local_virtuallab');
        set_config('warning_days_before', 7, 'local_virtuallab');

        ['batchid' => $batchid] = $this->create_batch_with_labs();
        // Backdating exactly 6 months places the reference date at the cutoff: warned, not yet overdue.
        $this->backdate_labs($batchid, 6);

        $sink = $this->redirectEmails();
        $this->run_task();
        $messages = $sink->get_messages();
        $sink->close();

        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $this->assertGreaterThan(0, (int) $lab->lastwarn, 'lastwarn should have been set.');
        $this->assertEquals(0, (int) $lab->lastreset, 'Lab in the warning window should not be reset.');
        $this->assertGreaterThanOrEqual(1, count($messages), 'A warning email should have been sent.');
    }

    /**
     * A freshly set global epoch protects an already-overdue lab, so enabling the policy
     * never actions an old lab without giving it a fresh window first.
     */
    public function test_execute_global_epoch_protects_overdue_lab(): void {
        global $DB;

        set_config('lifecycle_months', 6, 'local_virtuallab');
        set_config('lifecycle_action', maintenance_task::ACTION_DELETE, 'local_virtuallab');

        ['batchid' => $batchid, 'courseids' => $courseids] = $this->create_batch_with_labs();
        $this->backdate_labs($batchid, 12);

        // Policy just enabled: the clock counts from now, so the old lab is not overdue.
        set_config('lifecycle_epoch', time(), 'local_virtuallab');

        $this->run_task();

        $this->assertTrue(
            $DB->record_exists('local_virtuallab_courses', ['batchid' => $batchid]),
            'A lab protected by a fresh epoch must not be deleted.'
        );
        $this->assertTrue($DB->record_exists('course', ['id' => $courseids[0]]));
    }

    /**
     * A per-batch epoch (lifecyclestart) defers the action for that batch's overdue labs.
     */
    public function test_execute_per_batch_epoch_protects_lab(): void {
        global $DB;

        set_config('lifecycle_months', 6, 'local_virtuallab');
        set_config('lifecycle_action', maintenance_task::ACTION_RESET, 'local_virtuallab');

        ['batchid' => $batchid] = $this->create_batch_with_labs();
        $this->backdate_labs($batchid, 12);

        $DB->set_field('local_virtuallab_batches', 'lifecyclestart', time(), ['id' => $batchid]);

        $this->run_task();

        $lab = $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid]);
        $this->assertEquals(0, (int) $lab->lastreset, 'A per-batch epoch must defer the reset.');
    }

    /**
     * A lab already warned (lastwarn > 0) is not warned again in the same cycle.
     */
    public function test_execute_does_not_rewarn(): void {
        global $DB;

        set_config('lifecycle_months', 6, 'local_virtuallab');
        set_config('lifecycle_action', maintenance_task::ACTION_RESET, 'local_virtuallab');
        set_config('warning_days_before', 7, 'local_virtuallab');

        ['batchid' => $batchid] = $this->create_batch_with_labs();
        $this->backdate_labs($batchid, 6);
        $DB->set_field('local_virtuallab_courses', 'lastwarn', time() - DAYSECS, ['batchid' => $batchid]);

        $sink = $this->redirectEmails();
        $this->run_task();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages, 'No warning email should be sent to an already-warned lab.');
    }

    /**
     * All labs in a batch are processed when multiple labs are overdue.
     */
    public function test_execute_processes_all_overdue_labs(): void {
        global $DB;

        set_config('lifecycle_months', 6, 'local_virtuallab');
        set_config('lifecycle_action', maintenance_task::ACTION_RESET, 'local_virtuallab');

        ['batchid' => $batchid] = $this->create_batch_with_labs(3);
        $this->backdate_labs($batchid, 7);

        $this->run_task();

        $labs = $DB->get_records('local_virtuallab_courses', ['batchid' => $batchid]);
        $this->assertCount(3, $labs);
        foreach ($labs as $lab) {
            $this->assertGreaterThan(0, (int) $lab->lastreset, "Lab $lab->id should have been reset.");
        }
    }
}
