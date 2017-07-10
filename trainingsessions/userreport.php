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
 * Course trainingsessions report for a single user
 *
 * @package    report_trainingsessions
 * @category   report
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/htmlrenderers.php');

// Selector form.

require_once($CFG->dirroot.'/report/trainingsessions/selector_form.php');
$selform = new SelectorForm($id, 'user');
if (!$data = $selform->get_data()) {
    $data = new StdClass;
    $data->from = optional_param('from', -1, PARAM_NUMBER);
    $data->to = optional_param('to', -1, PARAM_NUMBER);
    $data->userid = optional_param('userid', $USER->id, PARAM_INT);
    $data->fromstart = optional_param('fromstart', 0, PARAM_BOOL);
    $data->tonow = optional_param('tonow', 0, PARAM_BOOL);
}

report_trainingsessions_process_bounds($data, $course);

echo $OUTPUT->header();
echo $OUTPUT->container_start();
echo $renderer->tabs($course, $view, $data->from, $data->to);
echo $OUTPUT->container_end();

echo $OUTPUT->box_start('block');
$selform->set_data($data);
$selform->display();
echo $OUTPUT->box_end();

echo get_string('from', 'report_trainingsessions')." : ".userdate($data->from);
echo ' '.get_string('to', 'report_trainingsessions')." : ".userdate($data->to);

// Get data.

$logs = use_stats_extract_logs($data->from, $data->to, $data->userid, $course->id);
$aggregate = use_stats_aggregate_logs($logs, 'module', 0, $data->from, $data->to);
$weekaggregate = use_stats_aggregate_logs($logs, 'module', 0, $data->to - WEEKSECS, $data->to);

$automatondebug = optional_param('debug', 0, PARAM_BOOL) && is_siteadmin();
if ($automatondebug) {
    echo '<h2>Aggregator output</h2>';
    block_use_stats_render_aggregate($aggregate);
}

if (empty($aggregate['sessions'])) {
    $aggregate['sessions'] = array();
}

// Get course structure.

$coursestructure = report_trainingsessions_get_course_structure($course->id, $items);

// Time period form.

$str = '';
$dataobject = report_trainingsessions_print_html($str, $coursestructure, $aggregate, $done);
if (empty($dataobject)) {
    $dataobject = new stdClass();
}
$dataobject->items = $items;
$dataobject->done = $done;

if ($dataobject->done > $items) {
    $dataobject->done = $items;
}

// In-activity.

$dataobject->activityelapsed = @$aggregate['activities'][$course->id]->elapsed;
$dataobject->activityevents = @$aggregate['activities'][$course->id]->events;
$dataobject->otherelapsed = @$aggregate['other'][$course->id]->elapsed;
$dataobject->otherevents = @$aggregate['other'][$course->id]->events;

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

if (array_key_exists('upload', $aggregate)) {
    $dataobject->elapsed += @$aggregate['upload'][0]->elapsed;
    $dataobject->upload = new StdClass;
    $dataobject->upload->elapsed = 0 + @$aggregate['upload'][0]->elapsed;
    $dataobject->upload->events = 0 + @$aggregate['upload'][0]->events;
}

$user = $DB->get_record('user', array('id' => $data->userid));
$cols = report_trainingsessions_get_summary_cols();
$headdata = report_trainingsessions_map_summary_cols($cols, $user, $aggregate, $weekaggregate, $course->id, true);

$headdata['items'] = $dataobject->items;
$headdata['done'] = $dataobject->done;

ob_start();
echo '<link rel="stylesheet" href="reports.css" type="text/css" />';
echo report_trainingsessions_print_header_html($data->userid, $course->id, (object)$headdata);

//report_trainingsessions_print_session_list($str, $aggregate['sessions'], $course->id, $data->userid);
echo $str;

// Get and display grades
report_trainingsessions_add_graded_data($gradecols, $data->userid, $aggregate);
echo report_trainingsessions_print_grades($gradecols);

$result = ob_get_clean();
echo $result;

echo '<br/><center>';

$params = array('id' => $course->id,
                'view' => 'user',
                'userid' => $data->userid,
                'from' => $data->from,
                'to' => $data->to);
$xlsurl = new moodle_url('/report/trainingsessions/tasks/userxlsreportperuser_batch_task.php', $params);
echo '<div class="trainingsessions-inline">';
echo $OUTPUT->single_button($xlsurl, get_string('generatexls', 'report_trainingsessions'));
echo '</div>';

$now = time();
$filename = 'report_user_detail_'.$data->userid.'_'.$course->id.'_'.date('Ymd_His', $now).'.pdf';
$params = array('coursename' => $course->idnumber . ' : ' . $course->fullname,
            'view' => 'userdetail',
            'userid' => $data->userid,
            'from' => userdate($data->from),
            'to' => userdate($data->to),
            'outputname' => $filename,
            'result' => $result);
$pdfurl = new moodle_url('/report/trainingsessions/generate_pdf.php', $params);
echo '<div class="trainingsessions-inline">';
echo $OUTPUT->single_button($pdfurl, get_string('generatepdf', 'report_trainingsessions'), 'post');
echo '</div>';

/*echo '<h3>'.get_string('quickmonthlyreport', 'report_trainingsessions').'</h3>';
echo $renderer->user_session_reports_buttons($data->userid, 'course');
echo "<!-- {$data->from} / {$data->to} -->";*/
echo '</center>';

echo '<br/>';
