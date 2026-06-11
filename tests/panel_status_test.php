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
 * PHPUnit tests for panel status computation in panel_repository.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual;

use advanced_testcase;
use local_labvirtual\local\batch_manager;
use local_labvirtual\local\course_factory;
use local_labvirtual\local\panel_repository;

/**
 * Tests for lab status flags, key visibility, and enrol button logic in the student panel.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class panel_status_test extends advanced_testcase {
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
    private function create_batch_with_labs(int $labcount = 2): array {
        $user     = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $mgr      = new batch_manager();
        $batchid  = $mgr->create_batch('Panel Test Batch', [$user->id], $category->id, 'Lab');
        $factory  = new course_factory();
        $courseids = $factory->create_labs($batchid, $labcount);
        return ['batchid' => $batchid, 'courseids' => $courseids];
    }

    /**
     * Enrolls a user as editingteacher in the given course.
     *
     * @param int $userid   User to enrol.
     * @param int $courseid Target course.
     */
    private function enrol_as_editor(int $userid, int $courseid): void {
        $this->getDataGenerator()->enrol_user($userid, $courseid, 'editingteacher');
    }

    /**
     * A lab with no editors has status_available = true.
     */
    public function test_status_available_when_no_editors(): void {
        ['batchid' => $batchid] = $this->create_batch_with_labs(1);
        $viewer = $this->getDataGenerator()->create_user();

        $repo = new panel_repository();
        $labs = $repo->get_panel_data($batchid, $viewer->id);

        $this->assertCount(1, $labs);
        $this->assertTrue($labs[0]['status_available']);
        $this->assertFalse($labs[0]['status_in_use']);
        $this->assertFalse($labs[0]['status_full']);
    }

    /**
     * A lab with one editor below the maximum has status_in_use = true.
     */
    public function test_status_in_use_with_one_editor(): void {
        ['batchid' => $batchid, 'courseids' => $courseids] = $this->create_batch_with_labs(1);
        $editor = $this->getDataGenerator()->create_user();
        $this->enrol_as_editor($editor->id, $courseids[0]);

        $viewer = $this->getDataGenerator()->create_user();
        $repo   = new panel_repository();
        $labs   = $repo->get_panel_data($batchid, $viewer->id);

        $this->assertFalse($labs[0]['status_available']);
        $this->assertTrue($labs[0]['status_in_use']);
        $this->assertFalse($labs[0]['status_full']);
    }

    /**
     * A lab that reaches max_teachers_per_lab editors has status_full = true.
     */
    public function test_status_full_at_max_teachers(): void {
        set_config('max_teachers_per_lab', 2, 'local_labvirtual');

        ['batchid' => $batchid, 'courseids' => $courseids] = $this->create_batch_with_labs(1);

        for ($i = 0; $i < 2; $i++) {
            $this->enrol_as_editor($this->getDataGenerator()->create_user()->id, $courseids[0]);
        }

        $viewer = $this->getDataGenerator()->create_user();
        $repo   = new panel_repository();
        $labs   = $repo->get_panel_data($batchid, $viewer->id);

        $this->assertTrue($labs[0]['status_full']);
        $this->assertFalse($labs[0]['status_available']);
        $this->assertFalse($labs[0]['status_in_use']);
    }

    /**
     * A full lab disables the editor enrol button but keeps the visitor button active.
     */
    public function test_full_lab_disables_editor_button_only(): void {
        set_config('max_teachers_per_lab', 1, 'local_labvirtual');

        ['batchid' => $batchid, 'courseids' => $courseids] = $this->create_batch_with_labs(1);
        $this->enrol_as_editor($this->getDataGenerator()->create_user()->id, $courseids[0]);

        $viewer = $this->getDataGenerator()->create_user();
        $repo   = new panel_repository();
        $labs   = $repo->get_panel_data($batchid, $viewer->id);

        $this->assertFalse($labs[0]['can_enrol_editor']);
        $this->assertTrue($labs[0]['can_enrol_visitor']);
    }

    /**
     * A user already enrolled in a lab cannot enrol again as editor or visitor.
     */
    public function test_already_enrolled_blocks_both_buttons(): void {
        ['batchid' => $batchid, 'courseids' => $courseids] = $this->create_batch_with_labs(1);
        $user = $this->getDataGenerator()->create_user();
        $this->enrol_as_editor($user->id, $courseids[0]);

        $repo = new panel_repository();
        $labs = $repo->get_panel_data($batchid, $user->id);

        $this->assertTrue($labs[0]['user_enrolled_here']);
        $this->assertFalse($labs[0]['can_enrol_editor']);
        $this->assertFalse($labs[0]['can_enrol_visitor']);
    }

    /**
     * A user who is already editor in one lab cannot take the editor slot in another lab,
     * but can still join as visitor.
     */
    public function test_editor_elsewhere_blocks_editor_button_on_other_labs(): void {
        ['batchid' => $batchid, 'courseids' => $courseids] = $this->create_batch_with_labs(2);
        $user = $this->getDataGenerator()->create_user();
        $this->enrol_as_editor($user->id, $courseids[0]);

        $repo = new panel_repository();
        $labs = $repo->get_panel_data($batchid, $user->id);

        // Lab 2: user not enrolled here, but is editor somewhere else in the batch.
        $lab2 = $labs[1];
        $this->assertFalse($lab2['user_enrolled_here']);
        $this->assertFalse($lab2['can_enrol_editor']);
        $this->assertTrue($lab2['can_enrol_visitor']);
    }
}
