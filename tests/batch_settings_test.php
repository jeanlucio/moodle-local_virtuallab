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
 * PHPUnit tests for batch_settings.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual;

use advanced_testcase;
use local_labvirtual\local\batch_settings;

/**
 * Tests resolution of effective per-batch settings.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class batch_settings_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * NULL overrides fall back to the global defaults.
     */
    public function test_effective_uses_global_default_when_null(): void {
        set_config('max_teachers_per_lab', 4, 'local_labvirtual');
        set_config('lifecycle_months', 6, 'local_labvirtual');
        set_config('lifecycle_action', 1, 'local_labvirtual');
        set_config('warning_days_before', 7, 'local_labvirtual');

        $batch = (object) [
            'maxteachers'     => null,
            'lifecyclemonths' => null,
            'lifecycleaction' => null,
            'warningdays'     => null,
        ];

        $effective = batch_settings::effective($batch);

        $this->assertSame(4, $effective->maxteachers);
        $this->assertSame(6, $effective->lifecyclemonths);
        $this->assertSame(1, $effective->lifecycleaction);
        $this->assertSame(7, $effective->warningdays);
    }

    /**
     * Set overrides win over the global defaults, including an explicit zero.
     */
    public function test_effective_uses_override_when_set(): void {
        set_config('lifecycle_months', 6, 'local_labvirtual');
        set_config('warning_days_before', 7, 'local_labvirtual');

        $batch = (object) [
            'maxteachers'     => 1,
            'lifecyclemonths' => 12,
            'lifecycleaction' => 2,
            'warningdays'     => 0,
        ];

        $effective = batch_settings::effective($batch);

        $this->assertSame(1, $effective->maxteachers);
        $this->assertSame(12, $effective->lifecyclemonths);
        $this->assertSame(2, $effective->lifecycleaction);
        $this->assertSame(0, $effective->warningdays);
    }
}
