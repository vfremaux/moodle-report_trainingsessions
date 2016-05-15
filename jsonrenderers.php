<?php
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * json data format
 *
 * @package     report_trainingsessions
 * @category    report
 * @copyright   Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function report_trainingsessions_get_usersessions(&$result, $userid) {
    // Get user.
    $user = $DB->get_record('user', array('id' => $userid));

    // Get data
    $logs = use_stats_extract_logs($from, $to, $user->id);
    $aggregate = use_stats_aggregate_logs($logs, 'module');

    $total = new StdClass();
    $total->duration = 0;
    $total->events = 0;
    foreach ($aggregate->sessions as $sessionid => $session) {

        // Fix eventual missing session end.
        if (empty($session->sessionend)) {
            $session->sessionend = $session->sessionstart + $session->elapsed;
        }

        $total->duration += $session->elapsed;
        $total->events++;
    }

    $result->sessions = $aggregate->sessions;
    $result->total = $total;
}
