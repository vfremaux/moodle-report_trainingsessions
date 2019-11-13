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
// require_once($CFG->dirroot.'/report/trainingsessions/lib/excellib.php');
require_once($CFG->dirroot.'/lib/excellib.class.php');
require_once($CFG->dirroot.'/report/learningtimecheck/lib.php');

$id = required_param('id', PARAM_INT); // The course id.
$groupid = required_param('groupid', PARAM_INT); // The group id.

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    // Do NOT print_error here as we are a document writer.
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);
$config = get_config('report_trainingsessions');

$input = report_trainingsessions_batch_input($course);

// Security.

report_trainingsessions_back_office_access($course);

<<<<<<< HEAD
=======
$PAGE->set_context($context);

>>>>>>> MOODLE_37_STABLE
// Compute target group.

if ($groupid) {
    $group = $DB->get_record('groups', array('id' => $groupid));
    $targetusers = get_enrolled_users($context, '', $groupid, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
} else {
    $targetusers = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
}

// Filter out non compiling users.
report_trainingsessions_filter_unwanted_users($targetusers, $course);

// Print result.

if (!empty($targetusers)) {

    // Generate XLS.

    if ($groupid) {
<<<<<<< HEAD
        $filename = "trainingsessions_group_{$groupid}_workingdays_".$input->filenametimesession.".xls";
    } else {
        $filename = "trainingsessions_course_{$course->id}_workingdays_".$input->filenametimesession.".xls";
=======
        $filename = "ts_course_{$course->shortname}_group_{$groupid}_workingdays_".$input->filenametimesession.".xls";
    } else {
        $filename = "ts_course_{$course->shortname}_workingdays_".$input->filenametimesession.".xls";
>>>>>>> MOODLE_37_STABLE
    }
    $workbook = new MoodleExcelWorkbook('-');

    // Sending HTTP headers.
    ob_end_clean();
    $workbook->send($filename);

    $xlsformats = report_trainingsessions_xls_formats($workbook);

    $row = 0;
    $sheetdate = date('d-M-Y', time());
    $worksheet = $workbook->add_worksheet($sheetdate);

    $cols = report_trainingsessions_get_workingdays_cols();
    $headtitles = report_trainingsessions_get_workingdays_cols('title');
    $headerformats = array('a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a');
    $dataformats = report_trainingsessions_get_workingdays_cols('format');
    $studentformats = report_trainingsessions_get_workingdays_cols('studentwdstatsformat');

    $row = report_trainingsessions_print_rawline_xls($worksheet, $headtitles, $headerformats, $row, $xlsformats);

    $minrow = 2;
    $maxrow = 2;
    foreach ($targetusers as $auser) {

        $events = report_learningtimecheck_get_user_workdays($auser->id);
        $totaltime = 0;
        $totalweeks = 0;
        $totaldays = 0;
        $weekmem = 0;

        if ($events) {
            foreach ($events as $e) {
                $totaldays++;
                // Workdays events are given at noon.
                $start = $e->timestart - 12 * HOURSECS + 1;
                $end = $e->timestart + 12 * HOURSECS - 1;

                $logs = use_stats_extract_logs($start, $end, $auser->id);
                $aggregate = use_stats_aggregate_logs($logs, $start, $end);
                $weeknum = date('W', $e->timestart);

                if ($weeknum != $weekmem) {
                    $totalweeks++;
                    $weekmem = $weeknum;
                }

                $wdtime = 0;
                if (!empty($aggregate['sessions'])) {
                    foreach ($aggregate['sessions'] as $s) {
                        $wdtime += 0 + @$s->elapsed;
                    }
                }

                $traversedcourses = array();
                if (!empty($aggregate['course'])) {
                    foreach (array_keys($aggregate['course']) as $courseid) {
                        $traversedcourses[] = $DB->get_field('course', 'shortname', array('id' => $courseid));
                    }
                }

                $data = array();
                $data[0] = $auser->id;
                $data[1] = $auser->username;
                $data[2] = fullname($auser);
                $data[3] = strftime(get_string('strfdate', 'report_trainingsessions'), $e->timestart);
                $data[4] = $weeknum;
                $data[5] = count($aggregate['sessions']);
                $data[6] = $wdtime;
                $data[7] = report_trainingsessions_format_time($wdtime, $mode = 'htmld');
                $data[8] = strftime(get_string('strftime', 'report_trainingsessions'), @$aggregate['sessions'][0]->sessionstart);
                $data[9] = implode(", ", $traversedcourses);

                $row = report_trainingsessions_print_rawline_xls($worksheet, $data, $dataformats, $row, $xlsformats);
                $maxrow++;
            }

            $data = array();
            $data[] = get_string('totalwdtime', 'report_trainingsessions');
            $data[] = $totaltime;
            $data[] = report_trainingsessions_format_time($totaltime, $mode = 'htmld');
            $data[] = get_string('meanweektime', 'report_trainingsessions');
            $meanweeks = ($totalweeks) ? $totaltime / $totalweeks : 0;
            $data[] = $meanweeks;
            $data[] = report_trainingsessions_format_time($meanweeks, $mode = 'htmld');
            $data[] = get_string('meandaytime', 'report_trainingsessions');
            $meandays = ($totaldays) ? $totaltime / $totaldays : 0;
            $data[] = $meandays;
            $data[] = report_trainingsessions_format_time($meandays, $mode = 'htmld');
            $row = report_trainingsessions_print_rawline_xls($worksheet, $data, $studentformats, $row, $xlsformats);
            $row++; // Jump a row.
            $maxrow++;
        }
    }

    $workbook->close();
}