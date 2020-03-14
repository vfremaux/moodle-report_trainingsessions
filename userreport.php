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

ob_start();

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/htmlrenderers.php');

$tsconfig = get_config('report_trainingsessions');
$rt = \report\trainingsessions\trainingsessions::instance();
$renderer = new \report\trainingsessions\HtmlRenderer($rt);

raise_memory_limit(MEMORY_EXTRA);

// Selector form.

require_once($CFG->dirroot.'/report/trainingsessions/selector_form.php');
$selform = new SelectorForm($id, 'user');
if (!$data = $selform->get_data()) {
    $data = new StdClass;
    $data->from = optional_param('from', -1, PARAM_NUMBER);
    $data->to = optional_param('to', -1, PARAM_NUMBER);
    $data->userid = optional_param('userid', $USER->id, PARAM_INT);
    $data->fromstart = optional_param('fromstart', @$tsconfig->defaultstartdate, PARAM_TEXT);
    $data->tonow = optional_param('tonow', 0, PARAM_BOOL);
}

$rt->process_bounds($data, $course);
// Need renew the form if process bounds have changed something.
$selform = new SelectorForm($id, 'user');

echo $OUTPUT->header();
echo $OUTPUT->container_start();
echo $rtrenderer->tabs($course, $view, $data->from, $data->to);
echo $OUTPUT->container_end();

echo $OUTPUT->box_start('block');
$selform->set_data($data);
$selform->display();
echo $OUTPUT->box_end();

echo get_string('from', 'report_trainingsessions')." : ".userdate($data->from);
echo ' '.get_string('to', 'report_trainingsessions')." : ".userdate($data->to);
$usconfig = get_config('block_use_stats');
if ($usconfig->enrolmentfilter && has_capability('report/trainingsessions:viewother', $context)) {
    echo $OUTPUT->notification(get_string('warningusestateenrolfilter', 'block_use_stats'));
}

// Get data.
use_stats_fix_last_course_access($data->userid, $course->id);
$logs = use_stats_extract_logs($data->from, $data->to, $data->userid, $course->id);
$aggregate = use_stats_aggregate_logs($logs, $data->from, $data->to);
$weekaggregate = use_stats_aggregate_logs($logs, $data->to - WEEKSECS, $data->to);

if (empty($aggregate['sessions'])) {
    $aggregate['sessions'] = array();
}

$user = $DB->get_record('user', array('id' => $data->userid));

// Get course structure.

$coursestructure = $rt->get_course_structure($course->id, $items);
$cols = $rt->get_summary_cols('keys');
$headdata = $rt->map_summary_cols($cols, $user, $aggregate, $weekaggregate, $course->id, true);
$rt->add_graded_columns($cols, $unusedtitles);
$rt->add_graded_data($gradedata, $data->userid, $aggregate);
$headdata = (object) $headdata;
$headdata->gradecols = $gradedata;

$str = $renderer->print_html($coursestructure, $aggregate, $done);
$headdata->done = $done;
$headdata->items = $items;

echo $renderer->print_header_html($user, $course, $headdata, $cols);
echo $str;

if (!empty($tsconfig->showsessions)) {
    echo $renderer->print_session_list($aggregate['sessions'], $course->id, $data->userid);
}

echo $rtrenderer->xls_userexport_button($data);

if (report_trainingsessions_supports_feature('format/pdf')) {
    include_once($CFG->dirroot.'/report/trainingsessions/pro/renderer.php');
    $rendererext = new \report_trainingsessions\output\pro_renderer($PAGE, '');
    echo $rendererext->pdf_userexport_buttons($data);
}

echo '<br/>';

