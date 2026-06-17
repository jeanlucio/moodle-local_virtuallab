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
 * PHPUnit tests for the lifecycle deadline helper.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\lifecycle;

/**
 * Tests deadline summarisation (collapse vs. expand) and the scheduled label.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class lifecycle_test extends advanced_testcase {
    /**
     * Labs sharing one deadline collapse to a single batch-wide line.
     */
    public function test_summarise_deadlines_shared(): void {
        $summary = lifecycle::summarise_deadlines([1000, 1000, 1000]);

        $this->assertTrue($summary['shared']);
        $this->assertEquals(1000, $summary['deadline']);
    }

    /**
     * Diverging deadlines (an individually reset lab) keep the per-lab column.
     */
    public function test_summarise_deadlines_divergent(): void {
        $summary = lifecycle::summarise_deadlines([1000, 2000, 1000]);

        $this->assertFalse($summary['shared']);
        $this->assertEquals(0, $summary['deadline']);
    }

    /**
     * A disabled lifecycle (all zero) is neither shared nor shown.
     */
    public function test_summarise_deadlines_disabled(): void {
        $summary = lifecycle::summarise_deadlines([0, 0]);

        $this->assertFalse($summary['shared']);
        $this->assertEquals(0, $summary['deadline']);
    }

    /**
     * The scheduled label uses the batch action verb, and is empty without a deadline.
     */
    public function test_scheduled_label(): void {
        $this->resetAfterTest();

        $resetbatch  = (object) ['lifecycleaction' => 1];
        $deletebatch = (object) ['lifecycleaction' => 2];

        $this->assertStringContainsString(
            get_string('scheduled_action_reset', 'local_virtuallab', userdate(1000, get_string('strftimedate', 'langconfig'))),
            lifecycle::scheduled_label($resetbatch, 1000)
        );
        $this->assertStringContainsString(
            get_string('scheduled_action_delete', 'local_virtuallab', userdate(1000, get_string('strftimedate', 'langconfig'))),
            lifecycle::scheduled_label($deletebatch, 1000)
        );
        $this->assertSame('', lifecycle::scheduled_label($resetbatch, 0));
    }
}
