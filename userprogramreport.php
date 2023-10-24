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
 * Course trainingsessions report for a single user, but aggegating all courses in a courseset.
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
    $data->from = optional_param('from', $course->startdate, PARAM_NUMBER);
    $data->to = optional_param('to', time(), PARAM_NUMBER);
    if (has_capability('report/trainingsessions:viewother', $context, $USER->id)) {
        $firstcompiledusers = get_users_by_capability($context, 'report/trainingsessions:iscompiled', 'u.*', 'u.lastname, u.firstname', 0, 1);
        if (!empty($firstcompiledusers)) {
            $user = array_shift($firstcompiledusers);
            $data->userid = $user->id;
        } else {
            // No users in the course. Use "me".
            $data->userid = $USER->id;
        }
    } else {
        $data->userid = $USER->id;
    }
    $data->fromstart = optional_param('fromstart', $tsconfig->defaultstartdate, PARAM_TEXT);
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

if (empty($aggregate['sessions'])) {
    $aggregate['sessions'] = array();
}

if (!isset($user)) {
    $user = $DB->get_record('user', array('id' => $data->userid));
}

// Get courseset infos in trainingsessions condig.
$courseset = $rt->get_courseset($course->id);

if (is_null($courseset)) {
    echo $OUTPUT->notification("Not a course set.");
    return;
}

$sessions = [];

foreach ($courseset as $course) {

    // Get data.
    use_stats_fix_last_course_access($data->userid, $course->id);
    $logs = use_stats_extract_logs($data->from, $data->to, $data->userid, $course->id);
    $aggregate = use_stats_aggregate_logs($logs, $data->from, $data->to);
    $weekaggregate = use_stats_aggregate_logs($logs, $data->to - WEEKSECS, $data->to);

    // Get course structure.
    $coursestructure = $rt->get_course_structure($course->id, $items);
    $cols = $rt->get_summary_cols('keys');
    $courseheaddata = $rt->map_summary_cols($cols, $user, $aggregate, $weekaggregate, $course->id, true);
    $rt->add_graded_columns($cols, $unusedtitles);
    $rt->add_graded_data($gradedata, $data->userid, $aggregate);
    if (is_null($headdata)) {
        $headdata = (object) $courseheaddata;
        $headdata->gradecols = $gradedata;
        $headdata->done = 0;
        $headdata->items = 0;
    } else {
        $headdata = $rt->aggregate_objects($headdata, (object) $courseheaddata);
        $headdata->gradecols = $headdata->gradecols + $gradedata; // Agregate grade columns.
    }

    $str = $renderer->print_html($coursestructure, $aggregate, $done);
    $headdata->done = $done;
    $headdata->items = $items;
    if (!empty($tsconfig->showsessions)) {
        $sessions[$course->id] = $aggregate['sessions'];
    }
}

echo $renderer->print_header_html($user, $course, $headdata, $cols);
echo $str;

if (!empty($tsconfig->showsessions)) {
    foreach ($courseset as $course) {
        echo $renderer->print_session_list($sessions[$course->id], $course->id, $data->userid);
    }
}

echo $rtrenderer->xls_userexport_button($data);

if (report_trainingsessions_supports_feature('format/pdf')) {
    include_once($CFG->dirroot.'/report/trainingsessions/pro/renderer.php');
    $rendererext = new \report_trainingsessions\output\pro_renderer($PAGE, '');
    echo $rendererext->pdf_userexport_buttons($data);
}

echo '<br/>';

