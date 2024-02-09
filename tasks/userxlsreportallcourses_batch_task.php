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
require_once($CFG->dirroot.'/report/trainingsessions/lib/excellib.php');

$id = required_param('id', PARAM_INT); // The course id (context for user targets).
$userid = required_param('userid', PARAM_INT); // User id.
$filename = optional_param('outputname', '', PARAM_FILE);
$rt = \report\trainingsessions\trainingsessions::instance();
$renderer = new \report\trainingsessions\XlsRenderer($rt);

if (empty($filename)) {
    // TODO : this is a quick fix. Should see if report_trainingsessions_batch_input is usable here.
    $filename = "ts_allcourses_{$userid}_report_".date('Ymd_His', time()).'.xls';
}

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

$input = $rt->batch_input($course);

// Security.

$rt->back_office_access($course, $userid);

$PAGE->set_context($context);

$config = get_config('report_trainingsessions');

// Get all results for this user.
$logs = use_stats_extract_logs($input->from, $input->to, $user->id, 0);
$aggregate = use_stats_aggregate_logs($logs, $input->from, $input->to, '', false, 0);

$fulltotal = 0;
list($displaycourses,
     $courseshort,
     $coursefull,
     $courseelapsed,
     $courseevents) = block_use_stats_get_coursetable($aggregate, $fulltotal, $fullevents);

// Generate XLS.
$workbook = new MoodleExcelWorkbookTS("-");
if (!$workbook) {
    die("Excel Librairies Failure");
}

// Sending HTTP headers.
ob_end_clean();
$workbook->send($filename);

$xlsformats = $renderer->xls_formats($workbook);

// Define variables.
$startrow = $renderer->count_header_rows($course->id);
$worksheet = $renderer->init_worksheet($user->id, $startrow, $xlsformats, $workbook, 'allcourses');
$cols = $rt->get_summary_cols();
$headdata = $rt->map_summary_cols($cols, $user, $aggregate, $weekaggregate, $course->id, true /* associative */);
$headdata = (object) $headdata;
$headdata->from = $input->from;
$headdata->to = $input->to;
$startrow = $renderer->print_header_xls($worksheet, $user->id, 0, $headdata, $cols, $xlsformats);

$y = $renderer->print_allcourses_xls($worksheet, $aggregate, $startrow, $xlsformats);

// Sending HTTP headers.
ob_end_clean();
$workbook->close();