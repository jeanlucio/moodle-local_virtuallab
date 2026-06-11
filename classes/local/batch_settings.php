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
 * Resolves the effective lifecycle settings for a batch.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual\local;

/**
 * Merges per-batch overrides with the global defaults.
 *
 * A NULL (or empty) override on the batch means "use the global default".
 */
class batch_settings {
    /**
     * Returns the effective settings for a batch, falling back to global defaults.
     *
     * @param \stdClass $batch Batch record (may carry override columns).
     * @return \stdClass Object with maxteachers, lifecyclemonths, lifecycleaction and warningdays.
     */
    public static function effective(\stdClass $batch): \stdClass {
        return (object) [
            'maxteachers'     => self::resolve(
                $batch->maxteachers ?? null,
                (int) (get_config('local_labvirtual', 'max_teachers_per_lab') ?: 3)
            ),
            'lifecyclemonths' => self::resolve(
                $batch->lifecyclemonths ?? null,
                (int) get_config('local_labvirtual', 'lifecycle_months')
            ),
            'lifecycleaction' => self::resolve(
                $batch->lifecycleaction ?? null,
                (int) get_config('local_labvirtual', 'lifecycle_action')
            ),
            'warningdays'     => self::resolve(
                $batch->warningdays ?? null,
                (int) get_config('local_labvirtual', 'warning_days_before')
            ),
        ];
    }

    /**
     * Returns the override when set, otherwise the default.
     *
     * @param int|string|null $override Per-batch value (NULL or empty means inherit).
     * @param int             $default  Global default.
     * @return int
     */
    private static function resolve($override, int $default): int {
        return ($override === null || $override === '') ? $default : (int) $override;
    }
}
