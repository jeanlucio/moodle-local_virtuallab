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
 * PHPUnit tests for hook_callbacks.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use core\hook\navigation\primary_extend;
use core\navigation\views\primary;
use local_virtuallab\local\batch_manager;

/**
 * Tests the primary navigation entry added for Virtual Lab managers.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class hook_callbacks_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Runs the primary_extend callback for the current user and reports whether the
     * Virtual Lab management node was added to the primary navigation.
     *
     * @return bool
     */
    private function node_added(): bool {
        global $PAGE;

        $primaryview = new primary($PAGE);
        $hook        = new primary_extend($primaryview);
        hook_callbacks::primary_extend($hook);

        return (bool) $primaryview->get('local_virtuallab_manage');
    }

    /**
     * A site manager (admin) sees the management node.
     */
    public function test_site_manager_sees_node(): void {
        $this->setAdminUser();
        $this->assertTrue($this->node_added());
    }

    /**
     * A responsible teacher of a batch sees the management node.
     */
    public function test_batch_teacher_sees_node(): void {
        $teacher = $this->getDataGenerator()->create_user();

        $sink = $this->redirectMessages();
        (new batch_manager())->create_batch('Turma', [$teacher->id], 'Lab');
        $sink->close();

        $this->setUser($teacher);
        $this->assertTrue($this->node_added());
    }

    /**
     * A regular user with no batch and no capability does not see the node.
     */
    public function test_regular_user_has_no_node(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse($this->node_added());
    }

    /**
     * The guest user never sees the node.
     */
    public function test_guest_has_no_node(): void {
        $this->setGuestUser();
        $this->assertFalse($this->node_added());
    }

    /**
     * A logged-out request never sees the node.
     */
    public function test_logged_out_has_no_node(): void {
        $this->setUser(null);
        $this->assertFalse($this->node_added());
    }
}
