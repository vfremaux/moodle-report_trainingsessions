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
 * Course summary view report.
 *
 * @package    report_trainingsessions
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/selector_form.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/htmlrenderers.php');

$rt = \report\trainingsessions\trainingsessions::instance();

// Parameters.
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
    $data->asxls = optional_param('asxls', '0', PARAM_BOOL); // Obsolete.
}

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('coursemisconf');
}

// Require appropriate rights.
$context = context_course::instance($course->id);
if (!has_capability('report/trainingsessions:viewother', $context, $USER->id)) {
    throw new Exception("User doesn't have rights to see this view");
}
$config = get_config('report_trainingsessions');

$rt->process_bounds($data, $course);

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
}

// Filter out non compiling users.
$rt->filter_unwanted_users($targetusers, $course);

// Note: targetusers shoud default to array() if empty. Emptyness is considered later.

// Setup column list.
$durationcols = array('activitytime',
                      'equlearningtime',
                      'elapsed',
                      'extelapsed',
                      'extelapsedlastweek',
                      'extother',
                      'extotherlastweek',
                      'coursetime',
                      'elapsedlastweek',
                      'extelapsedlastweek');
$datecols = array('lastlogin',
                  'firstcourseaccess',
                  'lastcourseaccess',
                  'firstaccess');
$rightaligncols = array('workingsessions');

// Get base data from moodle and bake it into a local format.
$courseid = $course->id;
$coursestructure = $rt->get_course_structure($courseid, $items);
$coursename = $course->fullname;

// Initialize summary cols.
$colskeys = $rt->get_summary_cols();
$colstitles = $rt->get_summary_cols('title');
$colsformats = $rt->get_summary_cols('format');

// Add potential additional grading cols.
$pregradekeysnum = count($colskeys); //  Controls
$rt->add_graded_columns($colskeys, $colstitles, $colsformats);
$postgradekeysnum = count($colskeys); //  Controls

$summarizedusers = array();
foreach ($targetusers as $user) {

    // Get data from moodle.
    $logs = use_stats_extract_logs($data->from, $data->to, $user->id, $courseid);
    $aggregate = use_stats_aggregate_logs($logs, $data->from, $data->to);

    $weeklogs = use_stats_extract_logs($data->to - DAYSECS * 7, $data->to, array($user->id), $courseid);
    $weekaggregate = use_stats_aggregate_logs($weeklogs, $data->to - DAYSECS * 7, $data->to);

    @$aggregate['coursetotal'][$courseid]->items = $items;

    $elapsed = 0 + @$aggregate['coursetotal'][$course->id]->elapsed;

    $colsdata = $rt->map_summary_cols($colskeys, $user, $aggregate, $weekaggregate, $courseid);
    $pregradecolsnum = count($colsdata); //  Controls
    if ($pregradekeysnum != $pregradecolsnum) {
        throw(new moodle_exception("Not same number of columns (1). " . implode(',', $colskeys)." vs. ".implode(',', $colsdata)));
    }

    // Fetch and add eventual additional score columns.
    $rt->add_graded_data($colsdata, $user->id, $aggregate);
    $postgradecolsnum = count($colsdata); //  Controls
    if ($postgradekeysnum != $postgradecolsnum) {
        throw(new moodle_exception("Not same number of columns (2). " . implode(',', $colskeys)." vs. ".implode(',', $colsdata)));
    }

    // Assemble keys and data.
    if (!empty($colskeys)) {
        $userrow = array_combine($colskeys, $colsdata);
        $summarizedusers[] = $userrow;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->container_start();
echo $rtrenderer->tabs($course, $view, $data->from, $data->to);
echo $OUTPUT->container_end();

echo $OUTPUT->box_start('block');
$data->view = $view;
$selform->set_data($data);
$selform->display();
echo $OUTPUT->box_end();

$config = get_config('report_trainingsessions');
if (!empty($config->showseconds)) {
    $durationformat = 'htmlds';
} else {
    $durationformat = 'htmld';
}

$template = new StdClass;

$template->from = userdate($data->from);
$template->to = userdate($data->to);

if (!empty($summarizedusers)) {
    // Add a table header row.

    foreach ($colstitles as $title) {
        $coltpl = new StdClass;
        $coltpl->title = $title;
        $template->colstitles[] = $coltpl;
    }

    // Add a row for each user.
    $line = 1;
    foreach ($summarizedusers as $auser) {
        if (empty($auser)) {
            continue;
        }
        $userdatatpl = new StdClass;
        $userdatatpl->line = $line;
        $col = 1;
        foreach ($auser as $fieldname => $field) {
            $fieldtpl = new StdClass;
            $fieldtpl->col = $col;
            if (in_array($fieldname, $durationcols)) {
                $fieldtpl->class = 'report-col-right';
                $fieldtpl->value = $rt->format_time($field, $durationformat);
            } else if (in_array($fieldname, $datecols)) {
                $fieldtpl->class = 'report-col-right';
                $fieldtpl->value = $rt->format_time($field, 'html');
            } else if (in_array($fieldname, $rightaligncols)) {
                $fieldtpl->class = 'report-col-right';
                $fieldtpl->value = $field;
            } else if (in_array($fieldname, $colskeys)) {
                // Those may come from grade columns.
                $fieldtpl->value = $field;
            }
            $userdatatpl->fields[] = $fieldtpl;
            $col++;
        }
        $userdatatpl->line = $line;
        $template->userdata[] = $userdatatpl;
        ++$line;
    }

    $params = array('id' => $course->id,
                    'groupid' => $data->groupid,
                    'from' => $data->from,
                    'to' => $data->to);
    $label = get_string('generatecsv', 'report_trainingsessions');
    $buttonurl = new moodle_url('/report/trainingsessions/tasks/groupcsvreportsummary_batch_task.php', $params);
    $template->generatecsvbutton = $OUTPUT->single_button($buttonurl, $label);

    // Add a 'generate XLS' button after the table.
    $params = array('id' => $course->id,
                    'groupid' => $data->groupid,
                    'from' => $data->from,
                    'to' => $data->to);
    $label = get_string('generatexls', 'report_trainingsessions');
    $buttonurl = new moodle_url('/report/trainingsessions/tasks/groupxlsreportsummary_batch_task.php', $params);
    $template->generatexlsbutton = $OUTPUT->single_button($buttonurl, $label);

    echo $OUTPUT->render_from_template('report_trainingsessions/coursesummary', $template);
} else {
    echo $OUTPUT->notification(get_string('nothing', 'report_trainingsessions'));
}

