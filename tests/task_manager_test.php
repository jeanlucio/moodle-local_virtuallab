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
 * PHPUnit tests for task_manager.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\batch_manager;
use local_virtuallab\local\task_manager;

/**
 * Tests for batch task list storage and parsing.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_virtuallab\local\task_manager
 */
final class task_manager_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
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
     * set_tasks_from_text() splits on newlines, trims entries, and skips blank lines.
     */
    public function test_set_tasks_from_text_parses_correctly(): void {
        $batchid = $this->create_batch();
        $mgr     = new task_manager();

        $mgr->set_tasks_from_text($batchid, "  Task A  \nTask B\n\n   \nTask C");

        $this->assertSame(['Task A', 'Task B', 'Task C'], $mgr->get_tasks($batchid));
    }

    /**
     * A second call to set_tasks() replaces the whole list rather than appending.
     */
    public function test_set_tasks_replaces_entire_list(): void {
        $batchid = $this->create_batch();
        $mgr     = new task_manager();

        $mgr->set_tasks($batchid, ['Task A', 'Task B']);
        $mgr->set_tasks($batchid, ['Task X', 'Task Y', 'Task Z']);

        $this->assertSame(['Task X', 'Task Y', 'Task Z'], $mgr->get_tasks($batchid));
    }

    /**
     * get_tasks() returns titles in sortorder (insertion order).
     */
    public function test_get_tasks_respects_sortorder(): void {
        $batchid = $this->create_batch();
        $mgr     = new task_manager();

        $mgr->set_tasks($batchid, ['First', 'Second', 'Third']);

        $this->assertSame(['First', 'Second', 'Third'], $mgr->get_tasks($batchid));
    }

    /**
     * get_tasks_text() round-trips through set_tasks_from_text() without loss.
     */
    public function test_get_tasks_text_round_trip(): void {
        $batchid  = $this->create_batch();
        $mgr      = new task_manager();
        $original = "Task A\nTask B\nTask C";

        $mgr->set_tasks_from_text($batchid, $original);

        $this->assertSame($original, $mgr->get_tasks_text($batchid));
    }

    /**
     * set_tasks_from_text() with an empty string leaves the list empty.
     */
    public function test_set_tasks_from_text_empty_clears_list(): void {
        $batchid = $this->create_batch();
        $mgr     = new task_manager();

        $mgr->set_tasks($batchid, ['Task A', 'Task B']);
        $mgr->set_tasks_from_text($batchid, '');

        $this->assertSame([], $mgr->get_tasks($batchid));
    }

    /**
     * set_tasks() with an empty array leaves the list empty.
     */
    public function test_set_tasks_with_empty_array(): void {
        $batchid = $this->create_batch();
        $mgr     = new task_manager();

        $mgr->set_tasks($batchid, ['Task A']);
        $mgr->set_tasks($batchid, []);

        $this->assertSame([], $mgr->get_tasks($batchid));
    }

    /**
     * set_tasks_from_text() correctly handles Windows-style line endings.
     */
    public function test_set_tasks_from_text_handles_crlf(): void {
        $batchid = $this->create_batch();
        $mgr     = new task_manager();

        $mgr->set_tasks_from_text($batchid, "Task A\r\nTask B\r\nTask C");

        $this->assertSame(['Task A', 'Task B', 'Task C'], $mgr->get_tasks($batchid));
    }

    /**
     * get_tasks() returns an empty array when no tasks have been saved.
     */
    public function test_get_tasks_returns_empty_for_new_batch(): void {
        $batchid = $this->create_batch();

        $this->assertSame([], (new task_manager())->get_tasks($batchid));
    }
}
