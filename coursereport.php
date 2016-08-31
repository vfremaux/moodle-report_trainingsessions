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

defined('MOODLE_INTERNAL') || die();

/**
 * Course trainingsessions report
 *
 * @package    report_trainingsessions
 * @category   report
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * direct log construction implementation
 *
 */
ob_start();

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/selector_form.php');

$id = required_param('id', PARAM_INT) ; // the course id

// calculate start time

$selform = new SelectorForm($id, 'course');
if ($data = $selform->get_data()) {
} else {
    $data = new StdClass;
    $data->from = optional_param('from', -1, PARAM_NUMBER);
    $data->to = optional_param('to', -1, PARAM_NUMBER);
    $data->userid = optional_param('userid', $USER->id, PARAM_INT);
    $data->fromstart = optional_param('fromstart', 0, PARAM_BOOL);
    $data->tonow = optional_param('tonow', 0, PARAM_BOOL);
    $data->output = optional_param('output', 'html', PARAM_ALPHA);
    $data->groupid = optional_param('group', '0', PARAM_ALPHA);
}

$context = context_course::instance($id);

// calculate start time

if ($data->from == -1 || @$data->fromstart) { // maybe we get it from parameters
    $data->from = $course->startdate;
}

if ($data->to == -1 || @$data->tonow){ // maybe we get it from parameters
    $data->to = time();
} else {
    // the displayed time in form is giving a 0h00 time. We should push till
    // 23h59 of the given day
    $data->to = min(time(), $data->to + DAYSECS - 1);
}

if ($data->output == 'html') {
    echo $OUTPUT->header();
    echo $OUTPUT->container_start();
    echo $renderer->tabs($course, $view, $data->from, $data->to);
    echo $OUTPUT->container_end();

    echo $OUTPUT->box_start('block');
    $selform->set_data($data);
    $selform->display();
    echo $OUTPUT->box_end();
}

// compute target group

$allgroupsaccess = has_capability('moodle/site:accessallgroups', $context);

if (!$allgroupsaccess) {
    $mygroups = groups_get_my_groups();

    $allowedgroupids = array();
    if ($mygroups) {
        foreach ($mygroups as $g) {
            $allowedgroupids[] = $g->id;
        }
        if (empty($data->groupid) || !in_array($data->groupid, $allowedgroupids)) {
            $data->groupid = $allowedgroupids[0];
        }
    } else {
        echo $OUTPUT->notification(get_string('errornotingroups', 'report_trainingsessions'));
        echo $OUTPUT->footer($course);
        die;
    }
} else {
    if ($allowedgroups = groups_get_all_groups($COURSE->id, $USER->id, 0, 'g.id,g.name')) {
        $allowedgroupids = array_keys($allowedgroups);
    }
}

if ($data->groupid) {
    $targetusers = get_enrolled_users($context, '', $data->groupid);
} else {
    $targetusers = get_enrolled_users($context);
    if (count($targetusers) > 100) {
        if (!empty($allowedgroupids)) {
            $OUTPUT->notification(get_string('errorcoursetoolarge', 'report_trainingsessions'));
            $data->groupid = $allowedgroupids[0];
            // refetch again after eventual group correction
            $targetusers = get_enrolled_users($context, '', $data->groupid);
        } else {
            // DO NOT COMPILE 
            echo $OUTPUT->notification('Course is too large and no groups in. Cannot compile.');
            echo $OUTPUT->footer($course);
            die;
        }
    }
}

// Filter out non compiling users.
report_trainingsessions_filter_unwanted_users($targetusers, $course);

// get course structure
$coursestructure = report_trainingsessions_get_course_structure($course->id, $items);

// print result

if ($data->output == 'html') {

    include_once($CFG->dirroot.'/report/trainingsessions/renderers/htmlrenderers.php');

    echo '<link rel="stylesheet" href="reports.css" type="text/css" />';

    if (!empty($targetusers)) {
        foreach ($targetusers as $auser) {

            $logusers = $auser->id;
            $logs = use_stats_extract_logs($data->from, $data->to, $auser->id, $course);
            $aggregate = use_stats_aggregate_logs($logs, 'module', 0, $data->from, $data->to);

            if (empty($aggregate['sessions'])) {
                $aggregate['sessions'] = array();
            }

            $data->items = $items;

            $data->activityelapsed = @$aggregate['activities'][$course->id]->elapsed;
            $data->activityevents = @$aggregate['activities'][$course->id]->events;
            $data->otherelapsed = @$aggregate['other'][$course->id]->elapsed;
            $data->otherevents = @$aggregate['other'][$course->id]->events;
            $data->done = 0;

            if (!empty($aggregate)) {

                $data->course = new StdClass();
                $data->course->elapsed = 0;
                $data->course->events = 0;

                if (!empty($aggregate['course'])) {
                    $data->course->elapsed = 0 + @$aggregate['course'][$course->id]->elapsed;
                    $data->course->events = 0 + @$aggregate['course'][$course->id]->events;
                }

                // Calculate everything.

                $data->elapsed = $data->activityelapsed + $data->otherelapsed + $data->course->elapsed;
                $data->events = $data->activityevents + $data->otherevents + $data->course->events;

                $data->sessions = (!empty($aggregate['sessions'])) ? report_trainingsessions_count_sessions_in_course($aggregate['sessions'], $course->id) : 0;

                foreach (array_keys($aggregate) as $module) {
                    // exclude from calculation some pseudo-modules that are not part of 
                    // a course structure.
                    if (preg_match('/course|user|upload|sessions|system|activities|other/', $module)) continue;
                    $data->done += count($aggregate[$module]);
                }
            } else {
                $data->sessions = 0;
            }
            if ($data->done > $items) {
                $data->done = $items;
            }

            $data->linktousersheet = 1;
            echo report_trainingsessions_print_header_html($auser->id, $course->id, $data, true);

        }
    } else {
        echo $OUTPUT->notification(get_string('nousersfound'));
    }

    $options['id'] = $course->id;
    $options['groupid'] = $data->groupid;
    $options['from'] = $data->from; // alternate way
    $options['to'] = $data->to; // alternate way
    $options['output'] = 'xls'; // ask for XLS
    $options['asxls'] = 'xls'; // force XLS for index.php
    $options['view'] = 'course'; // force course view

    echo '<br/><center>';
    // echo count($targetusers).' found in this selection';
    $params = array('id' => $course->id, 'view' => 'course', 'groupid' => $data->groupid, 'from' => $data->from, 'to' => $data->to, 'output' => 'xls');
    $url = new moodle_url('/report/trainingsessions/index.php', $params);
    echo $OUTPUT->single_button($url, get_string('generateXLS', 'report_trainingsessions'), 'get');

    $params = array('id' => $course->id, 'view' => 'course', 'groupid' => $data->groupid, 'from' => $data->from, 'to' => $data->to, 'output' => 'pdf');
    $url = new moodle_url('/report/trainingsessions/index.php', $params);
    echo $OUTPUT->single_button($url, get_string('generatePDF', 'report_trainingsessions'), 'get');

    $params = array('id' => $course->id, 'view' => 'course', 'groupid' => $data->groupid, 'from' => $data->from, 'to' => $data->to, 'output' => 'csv');
    $url = new moodle_url('/report/trainingsessions/index.php', $params);
    echo $OUTPUT->single_button($url, get_string('generateCSV', 'report_trainingsessions'), 'get');
    echo '</center>';
    echo '<br/>';
} elseif ($output == 'xls') {

    require_once($CFG->libdir.'/excellib.class.php');
    require_once($CFG->dirroot.'/report/trainingsessions/renderers/xlsrenderers.php');

    /// generate XLS

    if ($data->groupid) {
        $filename = 'training_group_'.$data->groupid.'_report_'.date('d-M-Y', time()).'.xls';
    } else {
        $filename = 'training_course_'.$id.'_report_'.date('d-M-Y', time()).'.xls';
    }

    $workbook = new MoodleExcelWorkbook("-");
    if (!$workbook) {
        die("Null workbook");
    }
    // Sending HTTP headers
    ob_end_clean();
    $workbook->send($filename);

    $xls_formats = report_trainingsessions_xls_formats($workbook);
    $startrow = 15;

    if (!empty($targetusers)) {
        foreach ($targetusers as $auser) {
    
            $row = $startrow;
            $worksheet = report_trainingsessions_init_worksheet($auser->id, $row, $xls_formats, $workbook);
    
            $logusers = $auser->id;
            $logs = use_stats_extract_logs($data->from, time(), $auser->id, $course->id);
            $aggregate = use_stats_aggregate_logs($logs, 'module');
    
            if (empty($aggregate['sessions'])) $aggregate['sessions'] = array();
    
            $overall = report_trainingsessions_print_xls($worksheet, $coursestructure, $aggregate, $done, $row, $xls_formats);
            $data->items = $items;
            $data->done = $done;
            $data->elapsed = $overall->elapsed;
            $data->events = $overall->events;
            report_trainingsessions_print_header_xls($worksheet, $auser->id, $course->id, $data, $xls_formats);
    
            $worksheet = report_trainingsessions_init_worksheet($auser->id, $startrow, $xls_formats, $workbook, 'sessions');
            report_trainingsessions_print_sessions_xls($worksheet, 15, $aggregate['sessions'], $COURSE->id, $xls_formats);
            report_trainingsessions_print_header_xls($worksheet, $auser->id, $course->id, $data, $xls_formats);
        }
    }
    $workbook->close();
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Not yet supported');
    echo $OUTPUT->footer();
}

