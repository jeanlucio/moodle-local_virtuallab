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
 * PHPUnit tests for report_repository labels.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\report_repository;

/**
 * Tests the localised component and action labels in the usage report.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class report_repository_test extends advanced_testcase {
    /**
     * A module component reuses its own localised plugin name, not a hardcoded label.
     */
    public function test_component_label_uses_module_pluginname(): void {
        $this->assertSame(
            get_string('pluginname', 'mod_assign'),
            report_repository::component_label('mod_assign')
        );
    }

    /**
     * The 'core' component maps to the plugin string, and unknown components fall back raw.
     */
    public function test_component_label_core_and_fallback(): void {
        $this->assertSame(
            get_string('report_component_core', 'local_virtuallab'),
            report_repository::component_label('core')
        );
        $this->assertSame('mod_unknownxyz', report_repository::component_label('mod_unknownxyz'));
    }

    /**
     * A known action maps to its plugin string; unknown actions fall back to the raw value.
     */
    public function test_action_label_known_and_fallback(): void {
        $this->assertSame(
            get_string('report_action_submitted', 'local_virtuallab'),
            report_repository::action_label('submitted')
        );
        $this->assertSame('somethingelse', report_repository::action_label('somethingelse'));
    }
}
