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
 * Lifecycle date helper — resolves the reset/delete deadline of a lab.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

/**
 * Computes the lifecycle reference date and deadline of a lab.
 *
 * The clock counts from an epoch so that enabling or tightening the policy never makes an
 * existing lab instantly overdue. The effective reference of a lab is the later of its own
 * timestamp (last reset, or creation) and the epoch floor, which is the later of the global
 * epoch and the batch epoch. Because both contributions can only push the reference forward,
 * a policy change can only ever delay an action, never bring it sooner without warning.
 */
class lifecycle {
    /**
     * Stores a fresh global epoch whenever a lifecycle setting changes.
     *
     * Wired as the updated-callback of the global lifecycle_months and lifecycle_action
     * settings, so changing either restarts the clock for every batch that inherits the
     * global policy.
     *
     * @param string $name Full name of the setting that changed (unused).
     */
    public static function on_global_setting_changed(string $name): void {
        set_config('lifecycle_epoch', time(), 'local_virtuallab');
    }

    /**
     * Returns the epoch floor that applies to a batch.
     *
     * @param \stdClass $batch Batch record (may carry a lifecyclestart column).
     * @return int Unix timestamp; 0 when no epoch has ever been set.
     */
    public static function epoch(\stdClass $batch): int {
        $global     = (int) get_config('local_virtuallab', 'lifecycle_epoch');
        $batchepoch = isset($batch->lifecyclestart) ? (int) $batch->lifecyclestart : 0;

        return max($global, $batchepoch);
    }

    /**
     * Returns the effective reference timestamp of a lab.
     *
     * @param \stdClass $batch Batch record.
     * @param \stdClass $lab   Lab row with lastreset and timecreated.
     * @return int Unix timestamp the lifecycle window counts from.
     */
    public static function reference(\stdClass $batch, \stdClass $lab): int {
        $ref = (int) $lab->lastreset > 0 ? (int) $lab->lastreset : (int) $lab->timecreated;

        return max($ref, self::epoch($batch));
    }

    /**
     * Returns the timestamp when the lifecycle action will run for a lab.
     *
     * @param \stdClass $batch Batch record.
     * @param \stdClass $lab   Lab row with lastreset and timecreated.
     * @return int Unix timestamp of the deadline, or 0 when the lifecycle is disabled.
     */
    public static function deadline(\stdClass $batch, \stdClass $lab): int {
        $settings = batch_settings::effective($batch);
        if ($settings->lifecyclemonths <= 0 || $settings->lifecycleaction <= 0) {
            return 0;
        }

        return strtotime('+' . $settings->lifecyclemonths . ' months', self::reference($batch, $lab));
    }

    /**
     * Decides whether a set of lab deadlines can collapse to a single batch-wide line.
     *
     * @param int[] $deadlines One deadline timestamp per lab (0 when the lifecycle is off).
     * @return array{shared: bool, deadline: int} shared is true only when every lab carries
     *         the same non-zero deadline; deadline then holds that shared timestamp, else 0.
     */
    public static function summarise_deadlines(array $deadlines): array {
        if (empty($deadlines)) {
            return ['shared' => false, 'deadline' => 0];
        }

        $distinct = array_unique($deadlines);
        if (count($distinct) === 1 && (int) reset($distinct) > 0) {
            return ['shared' => true, 'deadline' => (int) reset($distinct)];
        }

        return ['shared' => false, 'deadline' => 0];
    }

    /**
     * Builds the "Scheduled: reset/delete on DATE" label for a batch-wide deadline.
     *
     * @param \stdClass $batch    Batch record (resolves the reset/delete verb).
     * @param int       $deadline Shared deadline timestamp.
     * @return string Localised label, or empty string when no deadline applies.
     */
    public static function scheduled_label(\stdClass $batch, int $deadline): string {
        if ($deadline <= 0) {
            return '';
        }

        $key = (int) batch_settings::effective($batch)->lifecycleaction === 2
            ? 'scheduled_action_delete'
            : 'scheduled_action_reset';

        return get_string($key, 'local_virtuallab', userdate($deadline, get_string('strftimedate', 'langconfig')));
    }
}
