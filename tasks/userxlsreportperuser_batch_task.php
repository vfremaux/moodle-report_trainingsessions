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
require_once($CFG->dirroot.'/report/trainingsessions/lib/excellib.php');

$id = required_param('id', PARAM_INT); // The course id.
$userid = required_param('userid', PARAM_INT); // The group id.
$reportscope = optional_param('scope', 'currentcourse', PARAM_TEXT); // currentcourse or courseset.
$rt = \report\trainingsessions\trainingsessions::instance();
$renderer = new \report\trainingsessions\XlsRenderer($rt);
$config = get_config('report_trainingsessions');

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);
$PAGE->set_context($context);
$input = $rt->batch_input($course);

if ($reportscope == 'currentcourse') {
    $reportcourses[] = $course;
    $filename = "ts_course_{$course->shortname}_user_{$userid}_report_".$input->filenametimesession.'.xls';
} else if ($reportscope == 'courseset') {
    $reportcourses = $rt->get_courseset($course->id);
    $courseidstr = implode('_', array_keys($reportcourses));
    $filename = "ts_courseset_{$courseidstr}_user_{$userid}_report_".$input->filenametimesession.'.xls';
}

if (!$user = $DB->get_record('user', array('id' => $userid))) {
    // Do NOT print_error here as we are a document writer.
    die ('Invalid user ID');
}

// Security.
$rt->back_office_access($course, $userid);

$PAGE->set_context($context);

// Generate XLS.

$workbook = new MoodleExcelWorkbookTS("-");
if (!$workbook) {
    die("Excel Librairies Failure");
}

$auser = $DB->get_record('user', array('id' => $userid));

// Sending HTTP headers.
ob_end_clean();
$workbook->send($filename);

$xlsformats = $renderer->xls_formats($workbook);
$startrow = $renderer->count_header_rows($course->id) + 5;

$row = $startrow;
$worksheet = $renderer->init_worksheet($auser->id, $row, $xlsformats, $workbook);

$logusers = $auser->id;
$sessions = [];

foreach ($reportcourses as $c) {

    $logs = use_stats_extract_logs($input->from, $input->to, $auser->id, $c->id);
    $aggregates[$c->id] = use_stats_aggregate_logs($logs, $input->from, $input->to, '', false, $c);
    $weekaggregate[$c->id] = use_stats_aggregate_logs($logs, $input->to - WEEKSECS, $input->to, '', false, $c);

    $coursestructures[$c->id] = $rt->get_course_structure($c->id, $items);
    $cols = $rt->get_summary_cols();
    $courseheaddata = $rt->map_summary_cols($cols, $auser, $aggregates[$c->id], $weekaggregates[$c->id], $c->id, true /* associative */);
    $rt->add_graded_columns($cols, $titles);
    $rt->add_graded_data($gradedata, $auser->id, $aggregates[$c->id]);
    $topdata = $rt->calculate_course_structure($coursestructures[$c->id], $aggregates[$c->id], $done, $items);
    $topdata->elapsedstr = $rt->format_time($topdata->elapsed, 'htmlds');

    debug_trace("XLS Top structure C".$c->id);
    debug_trace($topdata);

    if (!isset($headdata)) {
        $headdata = (object) $courseheaddata;
        $headdata->gradecols = $gradedata;
        $headdata->from = $input->from;
        $headdata->to = $input->to;
    } else {
        $rt->aggregate_objects($headdata, (object) $courseheaddata);
    }

    if ($reportscope == 'courseset') {
        $renderer->print_xls_coursehead($worksheet, $c, $row, $xlsformats);
    }
    $renderer->print_xls($worksheet, $coursestructures[$c->id], $aggregates[$c->id], $row, $xlsformats);
    $headdata->done = $done;

    if (!empty($config->showsessions)) {
        if (!empty($aggregates[$c->id]['sessions'])) {
            $sessions[$c->id] = $aggregates[$c->id]['sessions'];
        } else {
            $sessions[$c->id] = [];
        }
    }
}

if (count($reportcourses) == 1) {
    // single course case.
    $renderer->print_header_xls($worksheet, $auser->id, $course->id, $headdata, $cols, $xlsformats);
} else {
    // Course set case.
    debug_trace("course set report for ");
    debug_trace(array_keys($reportcourses));
    $renderer->print_header_xls($worksheet, $auser->id, array_keys($reportcourses), $headdata, $cols, $xlsformats);
}

if (!empty($config->showsessions)) {
    foreach ($reportcourses as $c) {
        $worksheet = $renderer->init_worksheet($auser->id, $startrow, $xlsformats, $workbook, 'sessions_'.$c->id);

        if (count($reportcourses) == 1) {
            // single course case.
            $renderer->print_header_xls($worksheet, $auser->id, $course->id, $headdata, $cols, $xlsformats);
        } else {
            // Course set case.
            debug_trace("course set report for ");
            debug_trace(array_keys($reportcourses));
            $renderer->print_header_xls($worksheet, $auser->id, array_keys($reportcourses), $headdata, $cols, $xlsformats);
        }

        $renderer->print_sessions_xls($worksheet, $startrow, $sessions[$c->id], $c, $xlsformats);
    }
}

$workbook->close();
