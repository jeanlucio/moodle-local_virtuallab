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
 * Copies the student panel URL to the clipboard when the button is clicked.
 *
 * @module     local_labvirtual/copy_panel_link
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import {add as addNotification} from 'core/notification';

/**
 * Initialises the copy-to-clipboard button for the panel URL.
 */
export const init = () => {
    const btn = document.getElementById('local-labvirtual-copy-link');
    const input = document.getElementById('local-labvirtual-panel-url');

    if (!btn || !input) {
        return;
    }

    btn.addEventListener('click', async() => {
        try {
            await navigator.clipboard.writeText(input.value);
            const msg = await getString('panel_url_copied', 'local_labvirtual');
            addNotification({message: msg, type: 'success'});
        } catch {
            input.select();
            document.execCommand('copy');
        }
    });
};
