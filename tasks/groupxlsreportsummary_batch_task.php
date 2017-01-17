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
 * It may produce a group csv report.
 * groupid must be provided.
 * This script should be sheduled in a redirect bouncing process for maintaining
 * memory level available for huge batches.
 *
 * @package    report_trainingsessions
 * @category   report
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');

ob_start();
require_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
require_once $CFG->dirroot.'/report/trainingsessions/locallib.php';
require_once $CFG->dirroot.'/report/trainingsessions/renderers/xlsrenderers.php';
require_once($CFG->libdir.'/excellib.class.php');
require_once $CFG->libdir.'/gradelib.php';

$id = required_param('id', PARAM_INT); // The course id.
$groupid = required_param('groupid', PARAM_INT); // The group id.

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    // Do NOT print_error here as we are a document writer.
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);

$input = report_trainingsessions_batch_input($course);

// Security.

report_trainingsessions_back_office_access($course);

$coursestructure = report_trainingsessions_get_course_structure($course->id, $items);

// Compute target group.

if ($groupid) {
    $group = $DB->get_record('groups', array('id' => $groupid));
    $targetusers = groups_get_members($groupid);
} else {
    $targetusers = get_enrolled_users($context);
}

// Filter out non compiling users.
report_trainingsessions_filter_unwanted_users($targetusers, $course);

// print result.

if (!empty($targetusers)) {

    // generate XLS.

    if ($groupid) {
        $filename = "trainingsessions_group_{$groupid}_summary_".$input->filenametimesession.".xls";
    } else {
        $filename = "trainingsessions_course_{$course->id}_summary_".$input->filenametimesession.".xls";
    }
    $workbook = new MoodleExcelWorkbook('-');

    // Sending HTTP headers.
    ob_end_clean();
    $workbook->send($filename);

    $xlsformats = report_trainingsessions_xls_formats($workbook);

    $row = 0;
    $sheetdate = date('d-M-Y', time());
    $worksheet = $workbook->add_worksheet($sheetdate);

    $headtitles = report_trainingsessions_get_summary_cols('title');
    $dataformats = report_trainingsessions_get_summary_cols('format');

    report_trainingsessions_add_graded_columns($headtitles, $headerformats, $dataformats);

    $headerformats = array_pad(array(), count($headtitles), 'a');

    $row = report_trainingsessions_print_rawline_xls($worksheet, $headtitles, $headerformats, $row, $xlsformats);

    foreach ($targetusers as $auser) {

        $logs = use_stats_extract_logs($input->from, $input->to, array($auser->id), $course->id);
        $aggregate = use_stats_aggregate_logs($logs, 'module');

        $weeklogs = use_stats_extract_logs($input->to - DAYSECS * 7, $input->to, array($auser->id), $course->id);
        $weekaggregate = use_stats_aggregate_logs($weeklogs, 'module');

        $cols = report_trainingsessions_get_summary_cols();
        $data = report_trainingsessions_map_summary_cols($cols, $auser, $aggregate, $weekaggregate, $course->id);

        report_trainingsessions_add_graded_data($data, $auser->id, $aggregate);
        $row = report_trainingsessions_print_rawline_xls($worksheet, $data, $dataformats, $row, $xlsformats);

    }
    $workbook->close();
}