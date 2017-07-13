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
 * This script handles the report generation in batch task for a single group.
 * It will produce a group Excel worksheet report that is pushed immediately to output
 * for downloading by a batch agent. No file is stored into the system.
 * groupid must be provided.
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
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/xlsrenderers.php');
require_once($CFG->libdir.'/excellib.class.php');

$id = required_param('id', PARAM_INT); // The course id.
$userid = required_param('userid', PARAM_INT); // The group id.
$reportscope = optional_param('scope', 'currentcourse', PARAM_TEXT); // Only currentcourse is consistant.

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);
$PAGE->set_context($context);

if (!$user = $DB->get_record('user', array('id' => $userid))) {
    // Do NOT print_error here as we are a document writer.
    die ('Invalid user ID');
}

$input = report_trainingsessions_batch_input($course);

// Security.
report_trainingsessions_back_office_access($course);

$coursestructure = report_trainingsessions_get_course_structure($course->id, $items);

// Generate XLS.

$filename = "trainingsessions_user_{$userid}_report_".$input->filenametimesession.'.xls';

$workbook = new MoodleExcelWorkbook("-");
if (!$workbook) {
    die("Excel Librairies Failure");
}

$auser = $DB->get_record('user', array('id' => $userid));

// Sending HTTP headers.
ob_end_clean();
$workbook->send($filename);

$xlsformats = report_trainingsessions_xls_formats($workbook);
$startrow = 15;

$row = $startrow;
$worksheet = report_trainingsessions_init_worksheet($auser->id, $row, $xlsformats, $workbook);

$logusers = $auser->id;
$logs = use_stats_extract_logs($input->from, $input->to, $auser->id, $course->id);
$aggregate = use_stats_aggregate_logs($logs, 'module', 0, $input->from, $input->to);

$overall = report_trainingsessions_print_xls($worksheet, $coursestructure, $aggregate, $done, $row, $xlsformats);
$data = new StdClass();
$data->items = $items;
$data->done = $done;
$data->from = $input->from;
$data->elapsed = $overall->elapsed;
$data->events = $overall->events;
report_trainingsessions_print_header_xls($worksheet, $auser->id, $course->id, $data, $xlsformats);

$worksheet = report_trainingsessions_init_worksheet($auser->id, $startrow, $xlsformats, $workbook, 'sessions');
report_trainingsessions_print_sessions_xls($worksheet, 15, @$aggregate['sessions'], $course, $xlsformats);
report_trainingsessions_print_header_xls($worksheet, $auser->id, $course->id, $data, $xlsformats);

$workbook->close();
