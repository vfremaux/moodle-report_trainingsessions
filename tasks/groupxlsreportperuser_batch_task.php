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
$groupid = optional_param('groupid', 0, PARAM_INT); // The group id.
$rt = \report\trainingsessions\trainingsessions::instance();
$renderer = new \report\trainingsessions\XlsRenderer($rt);
$config = get_config('report_trainingsessions');

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    // Do NOT print_error here as we are a document writer.
    die('Invalid course ID');
}
$context = context_course::instance($course->id);

$input = $rt->batch_input($course);

// Security.
$rt->back_office_access($course);

$PAGE->set_context($context);

$coursestructure = $rt->get_course_structure($course->id, $items);

// TODO : secure groupid access depending on proper capabilities.
// Compute target group.

if ($groupid) {
    $targetusers = groups_get_members($groupid);
    $filename = "ts_course_{$course->shortname}_group_{$groupid}_report_".$input->filenametimesession.".xls";
} else {
    $targetusers = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
    $filename = "ts_course_{$course->shortname}_report_".$input->filenametimesession.".xls";
}

$rt->filter_unwanted_users($targetusers, $course);

// Print result.

// Generate XLS.

$workbook = new MoodleExcelWorkbookTS("-");
if (!$workbook) {
    die("Excel Librairies Failure");
}

// Sending HTTP headers.
ob_end_clean();
$workbook->send($filename);

$xlsformats = $renderer->xls_formats($workbook);
$startrow = $renderer->count_header_rows($course->id) + 5;

$cols = $rt->get_summary_cols();

if (!empty($targetusers)) {
    foreach ($targetusers as $auser) {

        $row = $startrow;
        $worksheet = $renderer->init_worksheet($auser->id, $row, $xlsformats, $workbook);

        $logusers = $auser->id;
        $logs = use_stats_extract_logs($input->from, $input->to, $auser->id, $course->id);
        $aggregate = use_stats_aggregate_logs($logs, $input->from, $input->to, '', false, $course);
        $weekaggregate = use_stats_aggregate_logs($logs, $input->to - WEEKSECS, $input->to, '', false, $course);

        $headdata = (object) $rt->map_summary_cols($cols, $auser, $aggregate, $weekaggregate, $course->id, true /* associative */);

        $renderer->print_xls($worksheet, $coursestructure, $aggregate, $done, $row, $xlsformats);
        $renderer->print_header_xls($worksheet, $auser->id, $course->id, $headdata, $cols, $xlsformats);

        // Print separate page for sessions.
        if (!empty($config->showsessions)) {
            $worksheet = $renderer->init_worksheet($auser->id, $startrow, $xlsformats, $workbook, 'sessions');
            $renderer->print_sessions_xls($worksheet, $startrow, @$aggregate['sessions'], $course, $xlsformats);
            $renderer->print_header_xls($worksheet, $auser->id, $course->id, $headdata, $cols, $xlsformats);
        }
    }
} else {
    $workbook->add_worksheet('No users');
}

$workbook->close();
