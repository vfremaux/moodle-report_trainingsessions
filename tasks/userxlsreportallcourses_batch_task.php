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
 * This script handles the session report generation in batch task for a single user.
 * It will produce a single PDF report that is pushed immediately to output
 * for downloading by a batch agent. No file is stored into the system.
 * userid must be provided.
 * This script should be sheduled in a CURL call stack or a multi_CURL parallel call.
 *
 * @package    report_trainingsessions
 * @category   report
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');

ob_start();
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/blocks/use_stats/xlib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/xlsrenderers.php');

$id = required_param('id', PARAM_INT); // The course id (context for user targets).
$userid = required_param('userid', PARAM_INT); // User id.
$filename = optional_param('outputname', '', PARAM_FILE);

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    // Do NOT print_error here as we are a document writer.
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);

if (!$user = $DB->get_record('user', array('id' => $userid))) {
    // Do NOT print_error here as we are a document writer.
    die ('Invalid user ID');
}

$input = report_trainingsessions_batch_input($course);

// Security.

report_trainingsessions_back_office_access($course);

$config = get_config('report_trainingsessions');

// Get all results for this user.
$logs = use_stats_extract_logs($input->from, $input->to, $user->id, 0);
$aggregate = use_stats_aggregate_logs($logs, 'module', 0, $input->from, $input->to);

$fulltotal = 0;
list($displaycourses, $courseshort, $coursefull, $courseelapsed, $courseevents) = block_use_stats_get_coursetable($aggregate, $fulltotal, $fullevents);

// Generate XLS.
$workbook = new MoodleExcelWorkbook("-");
if (!$workbook) {
    die("Excel Librairies Failure");
}

// Sending HTTP headers.
ob_end_clean();
$workbook->send($filename);

$xlsformats = report_trainingsessions_xls_formats($workbook);

// Define variables.

$y = report_trainingsessions_print_userinfo($workbook, $y, $user, $course, $input->from, $input->to, null, $config->recipient);

$y = report_trainingsessions_print_courseline_head($workbook, $y, $table);

foreach (array_keys($displaycourses) as $courseid) {
    $courseline = new StdClass;
    $courseline->idnumber = ''; // TODO fetch idnumber.
    $courseline->shortname = $courseshort[$courseid];
    $courseline->elapsed = $courseelapsed[$courseid];
    $courseline->events = $courseevents[$courseid];
    $y = report_trainingsessions_print_courseline($workbook, $y, $courseline);
}

$summators = array('', '', report_trainingsessions_format_time($fulltotal, 'xlsd'));
if (!empty($config->showhits)) {
    $summators[] = $fullevents;
}
$y = report_trainingsessions_print_sumline($pdf, $y, $summators, $table);

// Sending HTTP headers.
ob_end_clean();
$workbook->close();