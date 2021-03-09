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
 * Version details.
 *
 * @package     report_trainingsessions
 * @category    report
 * @author      Valery Fremaux (valery.fremaux@gmeil.com)
 * @copyright   2011 onwards Valery Fremaux (valery.fremaux@gmeil.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$plugin->version   = 2020100500; // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2018042700; // Requires this Moodle version.
$plugin->component = 'report_trainingsessions'; // Full name of the plugin (used for diagnostics).
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '3.5.0 (build 2020100500)';
$plugin->dependencies = array('block_use_stats' => '2020031300', 'auth_ticket' => '2012060400');

// Non moodle attributes.
$plugin->codeincrement = '3.5.0019';
$plugin->privacy = 'dualrelease';
