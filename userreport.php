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
 * Course trainingsessions report for a single user
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

// Selector form.

require_once($CFG->dirroot.'/report/trainingsessions/selector_form.php');
$selform = new SelectorForm($id, 'user');
if ($data = $selform->get_data()) {
} else {
    $data = new StdClass;
    $data->from = optional_param('from', -1, PARAM_NUMBER);
    $data->to = optional_param('to', -1, PARAM_NUMBER);
    $data->userid = optional_param('userid', $USER->id, PARAM_INT);
    $data->fromstart = optional_param('fromstart', 0, PARAM_BOOL);
    $data->tonow = optional_param('tonow', 0, PARAM_BOOL);
    $data->output = optional_param('output', 'html', PARAM_ALPHA);
}

if (($data->from == -1) || @$data->fromstart) {
    // maybe we get it from parameters
    $data->from = $course->startdate;
}

if (($data->to == -1) || @$data->tonow) {
    // maybe we get it from parameters
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

// Get data

$logs = use_stats_extract_logs($data->from, $data->to, $data->userid, $course->id);

$aggregate = use_stats_aggregate_logs($logs, 'module', 0, $data->from, $data->to);

if (empty($aggregate['sessions'])) {
    $aggregate['sessions'] = array();
}

// Get course structure.

$coursestructure = report_trainingsessions_get_course_structure($course->id, $items);
// Print result.

if ($data->output == 'html') {

    require_once($CFG->dirroot.'/report/trainingsessions/htmlrenderers.php');

    // Time period form.

    $str = '';
    $dataobject = report_trainingsessions_print_html($str, $coursestructure, $aggregate, $done);
    $dataobject->items = $items;
    $dataobject->done = $done;

    if ($dataobject->done > $items) {
        $dataobject->done = $items;
    }

    // In-activity.

    $dataobject->activityelapsed = @$aggregate['activities'][$COURSE->id]->elapsed;
    $dataobject->activityevents = @$aggregate['activities'][$COURSE->id]->events;
    $dataobject->otherelapsed = @$aggregate['other'][$COURSE->id]->elapsed;
    $dataobject->otherevents = @$aggregate['other'][$COURSE->id]->events;

    $dataobject->course = new StdClass;

    // Calculate in-course-out-activities.

    $dataobject->course->elapsed = 0;
    $dataobject->course->events = 0;

    if (!empty($aggregate['course'])) {
        $dataobject->course->elapsed = 0 + @$aggregate['course'][$course->id]->elapsed;
        $dataobject->course->events = 0 + @$aggregate['course'][$course->id]->events;
    }

    // Calculate everything.

    $dataobject->elapsed = $dataobject->activityelapsed + $dataobject->otherelapsed + $dataobject->course->elapsed;
    $dataobject->events = $dataobject->activityevents + $dataobject->otherevents + $dataobject->course->events;

    $dataobject->sessions = (!empty($aggregate['sessions'])) ? report_trainingsessions_count_sessions_in_course($aggregate['sessions'], $course->id) : 0;

    if (array_key_exists('upload', $aggregate)) {
        $dataobject->elapsed += @$aggregate['upload'][0]->elapsed;
        $dataobject->upload = new StdClass;
        $dataobject->upload->elapsed = 0 + @$aggregate['upload'][0]->elapsed;
        $dataobject->upload->events = 0 + @$aggregate['upload'][0]->events;
    }

    report_trainingsessions_print_header_html($data->userid, $course->id, $dataobject);

    report_trainingsessions_print_session_list($str, @$aggregate['sessions'], $course->id, $data->userid);

    echo $str;

    $params = array('id' => $course->id, 'view' => 'user', 'userid' => $data->userid, 'from' => $data->from, 'to' => $data->to, 'output' => 'xls');
    $url = new moodle_url('/report/trainingsessions/index.php', $params);
    echo '<br/><center>';
    echo $OUTPUT->single_button($url, get_string('generateXLS', 'report_trainingsessions'), 'get');
    echo $renderer->user_session_reports_buttons($data->userid, 'course');
    echo '</center>';
    echo '<br/>';

} else {
    require_once($CFG->dirroot.'/report/trainingsessions/xlsrenderers.php');

    // $CFG->trace = 'x_temp/xlsreport.log';

    require_once $CFG->libdir.'/excellib.class.php';

    $filename = 'training_sessions_report_'.date('d-M-Y', time()).'.xls';
    $workbook = new MoodleExcelWorkbook("-");

    // Sending HTTP headers.

    ob_end_clean();

    $workbook->send($filename);

    // Preparing some formats.

    $xls_formats = report_trainingsessions_xls_formats($workbook);
    $startrow = 15;
    $worksheet = report_trainingsessions_init_worksheet($data->userid, $startrow, $xls_formats, $workbook);
    $overall = report_trainingsessions_print_xls($worksheet, $coursestructure, $aggregate, $done, $startrow, $xls_formats);
    $datarec = new StdClass;
    $datarec->items = $items;
    $datarec->done = $done;
    $datarec->from = $data->from;
    $datarec->to = $data->to;
    $datarec->elapsed = $overall->elapsed;
    $datarec->events = $overall->events;
    report_trainingsessions_print_header_xls($worksheet, $data->userid, $course->id, $datarec, $xls_formats);

    $worksheet = report_trainingsessions_init_worksheet($data->userid, 15, $xls_formats, $workbook, 'sessions');
    report_trainingsessions_print_sessions_xls($worksheet, 15, $aggregate['sessions'], $course->id, $xls_formats);
    report_trainingsessions_print_header_xls($worksheet, $data->userid, $course->id, $datarec, $xls_formats);

    $workbook->close();
}
