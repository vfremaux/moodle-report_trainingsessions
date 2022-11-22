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
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/xlsrenderers.php');
require_once($CFG->dirroot.'/report/trainingsessions/lib/excellib.php');
require_once($CFG->libdir.'/gradelib.php');

$id = required_param('id', PARAM_INT); // The course id.
$groupid = required_param('groupid', PARAM_INT); // The group id.
$rt = \report\trainingsessions\trainingsessions::instance();
$renderer = new \report\trainingsessions\XlsRenderer($rt);


ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    // Do NOT print_error here as we are a document writer.
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);
$config = get_config('report_trainingsessions');

$input = $rt->batch_input($course);

// Security.

$rt->back_office_access($course);

$PAGE->set_context($context);

$coursestructure = $rt->get_course_structure($course->id, $items);

// Compute target group.

if ($groupid) {
    $group = $DB->get_record('groups', array('id' => $groupid));
    $targetusers = get_enrolled_users($context, '', $groupid, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
} else {
    $targetusers = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
}

// Filter out non compiling users.
$rt->filter_unwanted_users($targetusers, $course);

// Print result.
if (!empty($targetusers)) {

    // Generate XLS.

    if ($groupid) {
        $filename = "ts_course_{$course->shortname}_group_{$groupid}_summary_".$input->filenametimesession.".xls";
    } else {
        $filename = "ts_course_{$course->shortname}_summary_".$input->filenametimesession.".xls";
    }
    $workbook = new MoodleExcelWorkbookTS('-');

    // Sending HTTP headers.
    ob_end_clean();
    $workbook->send($filename);

    $xlsformats = $renderer->xls_formats($workbook);

    $row = 0;
    $sheetdate = date('d-M-Y', time());
    $worksheet = $workbook->add_worksheet($sheetdate);

    $cols = $rt->get_summary_cols();
    $headtitles = $rt->get_summary_cols('title');
    $dataformats = $rt->get_summary_cols('format');

    $rt->add_graded_columns($cols, $headtitles, $dataformats);
    $rt->add_calculated_columns($cols, $headtitles, $dataformats);

    $headerformats = array_pad(array(), count($headtitles), 'a');

    $row = $renderer->print_rawline_xls($worksheet, $headtitles, $headerformats, $row, $xlsformats);

    $minrow = 2;
    $maxrow = 2;
    foreach ($targetusers as $auser) {

        $logs = use_stats_extract_logs($input->from, $input->to, array($auser->id), $course->id);
        $aggregate = use_stats_aggregate_logs($logs, $input->from, $input->to, '', false, $course);

        $weeklogs = use_stats_extract_logs($input->to - DAYSECS * 7, $input->to, array($auser->id), $course->id);
        $weekaggregate = use_stats_aggregate_logs($weeklogs, $input->to - DAYSECS * 7, $input->to, '', false, $course);

        $data = $rt->map_summary_cols($cols, $auser, $aggregate, $weekaggregate, $course->id);

        $rt->add_graded_data($data, $auser->id, $aggregate);
        $rt->add_calculated_data($data);
        $row = $renderer->print_rawline_xls($worksheet, $data, $dataformats, $row, $xlsformats);
        $maxrow++;
    }

    $select = " courseid = ? AND moduleid = ".TR_LINEAGGREGATORS;
    $params = array($COURSE->id);
    if ($summaryrec = $DB->get_record_select('report_trainingsessions', $select, $params)) {
        $renderer->print_sumline_xls($worksheet, $dataformats, $summaryrec->label, $minrow, $maxrow - 1, $xlsformats);
    }

    $workbook->close();
} else {
    echo $OUTPUT->notification(get_string('nothing', 'report_trainingsessions'), 'notifyerror');
}