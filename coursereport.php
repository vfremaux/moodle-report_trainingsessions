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
 * Course trainingsessions report
 *
 * @package    report_trainingsessions
 * @category   report
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

ob_start();

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/selector_form.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/htmlrenderers.php');

$id = required_param('id', PARAM_INT); // The course id.
$rt = \report\trainingsessions\trainingsessions::instance();
$renderer = new \report\trainingsessions\HtmlRenderer($rt);

// Calculate start time.

$selform = new SelectorForm($id, 'course');
if (!$data = $selform->get_data()) {
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
$config = get_config('report_trainingsessions');

// Calculate start time.

$rt->process_bounds($data, $course);

if ($data->output == 'html') {
    echo $OUTPUT->header();
    echo $OUTPUT->container_start();
    echo $rtrenderer->tabs($course, $view, $data->from, $data->to);
    echo $OUTPUT->container_end();

    echo $OUTPUT->box_start('block');
    $selform->set_data($data);
    $selform->display();
    echo $OUTPUT->box_end();

    echo get_string('from', 'report_trainingsessions')." : ".userdate($data->from);
    echo ' '.get_string('to', 'report_trainingsessions')."  : ".userdate($data->to);
}

// Compute target group.

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
    $targetusers = get_enrolled_users($context, '', $data->groupid, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
} else {
    $targetusers = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
    if (count($targetusers) > 100) {
        if (!empty($allowedgroupids)) {
            $OUTPUT->notification(get_string('errorcoursetoolarge', 'report_trainingsessions'));
            $data->groupid = $allowedgroupids[0];
            // Refetch again after eventual group correction.
            $targetusers = get_enrolled_users($context, '', $data->groupid, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
        } else {
            // DO NOT COMPILE.
            echo $OUTPUT->notification(get_string('coursetoolargenotice', 'report_trainingsessions'));
            echo $OUTPUT->footer($course);
            die;
        }
    }
}

// Filter out non compiling users.
$rt->filter_unwanted_users($targetusers, $course);

// Get course structure.
$coursestructure = $rt->get_course_structure($course->id, $items);

// Print result.

if ($config->disablesuspendedenrolments && has_capability('report/trainingsessions:viewother', $context)) {
    echo $OUTPUT->notification(get_string('hasdisabledenrolmentsrestriction', 'report_trainingsessions'));
}

echo '<link rel="stylesheet" href="reports.css" type="text/css" />';

$cols = $rt->get_summary_cols('keys');
$rt->add_graded_columns($cols, $unusedtitles);

if (!empty($targetusers)) {
    foreach ($targetusers as $auser) {

        use_stats_fix_last_course_access($auser->id, $course->id);

        $logusers = $auser->id;
        $logs = use_stats_extract_logs($data->from, $data->to, $auser->id, $course);
        $aggregate = use_stats_aggregate_logs($logs, $data->from, $data->to);

        if (empty($aggregate['sessions'])) {
            $aggregate['sessions'] = array();
        }

        $headdata = (object) $rt->map_summary_cols($cols, $auser, $aggregate, $weekaggregate, $course->id, true);
        $headdata->gradecols = [];
        $rt->add_graded_data($headdata->gradecols, $auser->id, $aggregate);

        $headdata->done = 0;
        $headdata->items = $items;

        if (!empty($aggregate)) {

            // Calculate everything.
            $sesscount = $rt->count_sessions_in_course($aggregate['sessions'], $course->id);
            $headdata->sessions = (!empty($aggregate['sessions'])) ? $sesscount : 0;

            foreach (array_keys($aggregate) as $module) {
                /*
                 * Exclude from calculation some pseudo-modules that are not part of
                 * a course structure.
                 */
                if (preg_match('/course|user|upload|sessions|system|activities|other/', $module)) {
                    continue;
                }
                $headdata->done += count($aggregate[$module]);
            }
        } else {
            $headdata->sessions = 0;
        }
        if ($headdata->done > $items) {
            $headdata->done = $items;
        }

        $headdata->linktousersheet = 1;
        $headdata->from = $data->from;
        $headdata->to = $data->to;
        echo $renderer->print_header_html($auser, $course, $headdata, $cols, true /* short */);
    }
} else {
    echo $OUTPUT->notification(get_string('nothing', 'report_trainingsessions'));
}

$options['id'] = $course->id;
$options['groupid'] = $data->groupid;
$options['from'] = $data->from; // Alternate way.
$options['to'] = $data->to; // Alternate way.
$options['output'] = 'xls'; // Ask for XLS.
$options['asxls'] = 'xls'; // Force XLS for index.php.
$options['view'] = 'course'; // Force course view.

$template = new StdClass;
$params = array('id' => $course->id,
                'from' => $data->from,
                'to' => $data->to,
                'timesession' => time(),
                'groupid' => $data->groupid);
$csvurl = new moodle_url('/report/trainingsessions/tasks/groupcsvreportonerow_batch_task.php', $params);
$template->generatecsvbutton = $OUTPUT->single_button($csvurl, get_string('generatecsv', 'report_trainingsessions'), 'get');

$params = array('id' => $course->id,
                'view' => 'course',
                'groupid' => $data->groupid,
                'from' => $data->from,
                'to' => $data->to,
                'output' => 'xls');
$url = new moodle_url('/report/trainingsessions/tasks/groupxlsreportperuser_batch_task.php', $params);
$template->generatexlsbutton = $OUTPUT->single_button($url, get_string('generatexls', 'report_trainingsessions'), 'get');

if (report_trainingsessions_supports_feature('format/pdf')) {
    $params = array('id' => $course->id,
                    'view' => 'course',
                    'groupid' => $data->groupid,
                    'from' => $data->from,
                    'to' => $data->to);
    $url = new moodle_url('/report/trainingsessions/pro/tasks/grouppdfreportperuser_batch_task.php', $params);
    $template->generatepdfbutton = $OUTPUT->single_button($url, get_string('generatepdf', 'report_trainingsessions'), 'get');
}

echo $OUTPUT->render_from_template('report_trainingsessions/coursereportbuttons', $template);
