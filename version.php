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

$plugin->version   = 2021102100; // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2022112801; // Requires this Moodle version.
$plugin->component = 'report_trainingsessions'; // Full name of the plugin (used for diagnostics).
$plugin->maturity = MATURITY_STABLE;
<<<<<<< HEAD
$plugin->release = '4.1.0 (build 2021102100)';
$plugin->dependencies = array('block_use_stats' => '2020031300', 'auth_ticket' => '2012060400');
=======
$plugin->release = '4.1.0 (build 2023060500)';
$plugin->dependencies = array('block_use_stats' => '2023060500', 'auth_ticket' => '2012060400');
>>>>>>> MOODLE_401_STABLE
$plugin->supported = [401, 402];

// Non moodle attributes.
$plugin->codeincrement = '4.1.0023';
$plugin->privacy = 'dualrelease';
