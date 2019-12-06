<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Pro additional lib. The herein functions are provided for the "Pro" support
 * of the plugin.
 *
 * @package    report_trainingsessions
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A wrapper function to the internal ticket maker. Gets a valid ticket to authentify an
 * internal CURL call to a batch or a task.
 *
 * @see report/trainingsessions/locallib.php for internal implementation.
 * @return encrypted short delay access ticket
 */
function report_trainingsessions_ext_get_ticket() {
    global $CFG;

    include_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
    $rt = report\trainingsessions\trainingsessions::instance();
    return $rt->back_office_get_ticket();
}