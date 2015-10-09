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
 */
require('../../config.php');

ob_start();
require_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
require_once $CFG->dirroot.'/report/trainingsessions/locallib.php';
require_once $CFG->dirroot.'/report/trainingsessions/xlsrenderers.php';
require_once($CFG->libdir.'/excellib.class.php');
require_once $CFG->libdir.'/gradelib.php';

$id = required_param('id', PARAM_INT) ; // the course id
$startday = optional_param('startday', -1, PARAM_INT) ; // from (-1 is from course start)
$startmonth = optional_param('startmonth', -1, PARAM_INT) ; // from (-1 is from course start)
$startyear = optional_param('startyear', -1, PARAM_INT) ; // from (-1 is from course start)
$endday = optional_param('endday', -1, PARAM_INT) ; // to (-1 is till now)
$endmonth = optional_param('endmonth', -1, PARAM_INT) ; // to (-1 is till now)
$endyear = optional_param('endyear', -1, PARAM_INT) ; // to (-1 is till now)
$fromstart = optional_param('fromstart', 0, PARAM_INT) ; // force reset to course startdate
$from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$groupid = required_param('groupid', PARAM_INT) ; // group id
$timesession = required_param('timesession', PARAM_INT) ; // time of the generation batch
$readabletimesession = date('Ymd_H_i_s', $timesession);
$sessionday = date('Ymd', $timesession);

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);

// Security
trainingsessions_back_office_access($course);

$coursestructure = trainingsessions_get_course_structure($course->id, $items);

// TODO : secure groupid access depending on proper capabilities

// calculate start time

if ($from == -1) { // maybe we get it from parameters
    if ($startday == -1 || $fromstart) {
        $from = $course->startdate;
    } else {
        if ($startmonth != -1 && $startyear != -1) {
            $from = mktime(0, 0, 8, $startmonth, $startday, $startyear);
        } else {
            print_error('Bad start date');
        }
    }
}

if ($to == -1) { // maybe we get it from parameters
    if ($endday == -1) {
        $to = time();
    } else {
        if ($endmonth != -1 && $endyear != -1) {
            $to = mktime(0,0,8,$endmonth, $endday, $endyear);
        } else {
            print_error('Bad end date');
        }
    }
}

// compute target group

if ($groupid) {
    $group = $DB->get_record('groups', array('id' => $groupid));
    $targetusers = groups_get_members($groupid);
} else {
    $targetusers = get_users_by_capability($context, 'moodle/course:view', 'u.id,'.get_all_user_name_fields(true, 'u').', email, institution, idnumber, firstaccess, lastlogin', 'lastname,firstname');
}

// Filter out non compiling users.
trainingsessions_filter_unwanted_users($targetusers);

// print result

if (!empty($targetusers)) {
    /// generate XLS

    if ($groupid) {
        $filename = "trainingsessions_group_{$groupid}_summary_".date('d-M-Y', time()).".xls";
    } else {
        $filename = "trainingsessions_course_{$course->id}_summary_".date('d-M-Y', time()).".xls";
    }
    $workbook = new MoodleExcelWorkbook("-");
    // Sending HTTP headers
    ob_end_clean();
    $workbook->send($filename);

    $xls_formats = trainingsessions_xls_formats($workbook);

    $row = 0;
    // $sheettitle = '';
    $sheetdate = date('d-M-Y', time());
    $worksheet = $workbook->add_worksheet($sheetdate);

    $headdata = array(
        get_string('idnumber'),
        get_string('lastname'),
        get_string('firstname'),
        get_string('institution'),
        get_string('firstaccess'),
        get_string('lastlogin'),
        get_string('sessions', 'report_trainingsessions'),
        get_string('items', 'report_trainingsessions'),
        get_string('visiteditems', 'report_trainingsessions'),
        get_string('timespent', 'report_trainingsessions'),
        get_string('hits', 'report_trainingsessions'),
        get_string('timespentlastweek', 'report_trainingsessions'),
        get_string('hitslastweek', 'report_trainingsessions'),
    );

    $headerformats = array('p', 'p', 'p', 'p', 'p', 'p', 'p', 'p', 'p', 'p', 'p', 'p', 'p');
    $dataformats = array('a2', 'a2', 'a2', 'a2', 'zd', 'zd', 'z', 'z', 'z', 'zt', 'z', 'zt', 'z');

    trainingsessions_add_graded_columns($headdata, $headerformats, $dataformats);

    $row = trainingsessions_print_rawline_xls($worksheet, $headdata, $headerformats, $row, $xls_formats);

    $logs = use_stats_extract_logs($from, $to, array_keys($targetusers), $course->id);
    $aggregate = use_stats_aggregate_logs_per_user($logs, 'module');

    // print_object($aggregate);

    $weeklogs = use_stats_extract_logs($to - DAYSECS * 7, $to, array_keys($targetusers), $course->id);
    $weekaggregate = use_stats_aggregate_logs_per_user($weeklogs, 'module');

    // print_object($weekaggregate);

    foreach ($targetusers as $auser) {

        $data = array();
        $data[] = $auser->idnumber;
        $data[] = $auser->lastname;
        $data[] = $auser->firstname;
        $data[] = $auser->institution;
        $data[] = $auser->firstaccess;
        $data[] = $auser->lastlogin;
        $data[] = count(@$aggregate[$auser->id]['sessions']);
        $data[] = 0 + @$items;
        $data[] = (is_array(@$aggregate[$auser->id]['activities']->instances)) ? count(@$aggregate[$auser->id]['activities']->instances) : 0;
        $data[] = 0 + @$aggregate[$auser->id]['coursetotal'][$course->id]->elapsed;
        $data[] = 0 + @$aggregate[$auser->id]['coursetotal'][$course->id]->events;
        $data[] = 0 + @$weekaggregate[$auser->id]['coursetotal'][$course->id]->elapsed;
        $data[] = 0 + @$weekaggregate[$auser->id]['coursetotal'][$course->id]->events;

        trainingsessions_add_graded_data($data, $auser->id);

        $row = trainingsessions_print_rawline_xls($worksheet, $data, $dataformats, $row, $xls_formats);

    }
    $workbook->close();
}

// echo '200';
