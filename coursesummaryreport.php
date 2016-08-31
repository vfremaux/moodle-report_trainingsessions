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

/**
 * direct log construction implementation
 *
 */

// includes
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/selector_form.php');

// parameters
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
    $data->asxls = optional_param('asxls', '0', PARAM_BOOL); // Obsolete
}

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('coursemisconf');
}

// require appropriate rights
$context = context_course::instance($course->id);
if (!has_capability('report/trainingsessions:viewother', $context, $USER->id)){
    throw new Exception("User doesn't have rights to see this view");
}

// calculate start time
if ($data->from == -1) { // maybe we get it from parameters
    if ($startday == -1 || $data->fromstart) {
        $data->from = $course->startdate;
    } elseif ($data->startmonth != -1 && $data->startyear != -1) {
        $data->from = mktime(0, 0, 0, $data->startmonth, $data->startday, $data->startyear);
    } else {
        print_error('Bad start date');
    }
}

// calculate end time
if ($data->to == -1) { // maybe we get it from parameters
    if ($data->endday == -1 || $data->toend) {
        $data->to = time();
    } elseif ($data->endmonth != -1 && $data->endyear != -1) {
        $data->to = mktime(23, 59, 59, $data->endmonth, $data->endday, $data->endyear);
    } else {
        print_error('Bad end date');
    }
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
}

// Filter out non compiling users.
report_trainingsessions_filter_unwanted_users($targetusers, $course);

// setup local constants
$namedcols = array('id', 'idnumber', 'firstname', 'lastname', 'email', 'activitytime', 'equlearningtime', 'elapsed');
$durationcols = array('activitytime', 'equlearningtime', 'elapsed');

// get base data from moodle and bake it into a local format
$courseid = $course->id;
$coursestructure = report_trainingsessions_get_course_structure($courseid, $items);
$coursename = $course->fullname;

$gradecolumns = array();
report_trainingsessions_add_graded_columns($gradecolumns, $formats, $dformats);

$summarizedusers = array();
foreach ($targetusers as $user) {

    // get data from moodle
    $logs = use_stats_extract_logs($data->from, $data->to, $user->id);
    $aggregate = use_stats_aggregate_logs($logs, 'module', $user->id, $data->from, $data->to);
    // sum activity components of the data to get 
    // compose and store a derived user record
    $thisuser = array();
    $thisuser['lastname'] = $user->lastname;
    $thisuser['firstname'] = $user->firstname;
    $thisuser['email'] = $user->email;
    // SADGE SAYS: The following 2 lines were commented out at the request of MISchool - it would probably be sensible to add a configuration option to allow them to be reactivated
    //$thisuser['activitytime'] = training_reports_format_time($aggregate['activities']->elapsed, $output);
    //$thisuser['equlearningtime'] = training_reports_format_time($aggregate['activities']->elapsed+@$aggregate['course'][0]->elapsed, $output);
    $thisuser['elapsed'] = report_trainingsessions_format_time(0 + @$aggregate['coursetotal'][$course->id]->elapsed, $data->output == 'xls' ? 'xlsd' : 'html');

    // Fetch and add eventual additional score columns.

    $gradedata = array();
    report_trainingsessions_add_graded_data($gradedata, $user->id);

    if (!empty($gradecolumns)) {
        $gradeduser = array_combine($gradecolumns, $gradedata);
        foreach ($gradeduser as $k => $v) {
            $thisuser[$k] = ($v) ? sprintf('%.1f', $v) : '';
        }
    }

    $summarizedusers[] = $thisuser;
}

if ($data->output == 'html') {
    echo $OUTPUT->header();
    echo $OUTPUT->container_start();
    echo $renderer->tabs($course, $view, $data->from, $data->to);
    echo $OUTPUT->container_end();

    echo $OUTPUT->box_start('block');
    $data->view = $view;
    $selform->set_data($data);
    $selform->display();
    echo $OUTPUT->box_end();
}

// print result
if ($data->output == 'html') {

    require_once($CFG->dirroot.'/report/trainingsessions/renderers/htmlrenderers.php');

    // Time and group period form.
    echo '<br/>';

    if (!empty($summarizedusers)) {
        echo '<table class="coursesummary" width="100%">';
        // Add a table header row.
        echo '<tr><th></th>';
        foreach (array_values($summarizedusers)[0] as $fieldname => $field) {
            if (in_array($fieldname, $namedcols)) {
                // These are fixed translatable fields.
                echo '<th>'.get_string($fieldname, 'report_trainingsessions').'</th>';
            } elseif(in_array($fieldname, $gradecolumns)) {
                // Those may come from grade columns.
                echo '<th>'.$fieldname.'</th>';
            }
        }
        echo '</tr>';

        // Add a row for each user.
        $line = 1;
        foreach ($summarizedusers as $user) {
            echo '<tr><td>'.$line.'</td>';
            foreach ($user as $fieldname => $field) {
                if (in_array($fieldname, $namedcols)) {
                    $cssclass = (in_array($fieldname, $namedcols) && !in_array($fieldname, $durationcols))? 'left': 'right';
                    echo '<td class="'.$cssclass.'">'.$field.'</td>';
                } elseif(in_array($fieldname, $gradecolumns)) {
                    // Those may come from grade columns.
                    echo '<td>'.$field.'</td>';
                }
            }
            echo '</tr>';
            ++$line;
        }

        echo '</table>';
        echo '<br/>';

        // Add a 'generate XLS' button after the table.
        $options['id']      = $course->id;
        $options['groupid'] = $data->groupid;
        $options['from']    = $data->from;            // alternate way
        $options['to']      = $data->to;              // alternate way
        $options['output']  = 'xls';            // ask for XLS
        $options['asxls']   = 'xls';            // force XLS for index.php
        $options['view']    = 'coursesummary';  // force course summary view
        echo '<center>';
        echo $OUTPUT->single_button(new moodle_url('/report/trainingsessions/index.php', $options), get_string('generateXLS', 'report_trainingsessions'));
        echo '</center>';
        echo '<br/>';
    } else {
        echo $OUTPUT->notification('nousersfound');
    }

} else { // generate XLS

    // include xls libraries
    require_once($CFG->libdir.'/excellib.class.php');
    require_once($CFG->dirroot.'/report/trainingsessions/renderers/xlsrenderers.php');

    // Work out a name for the output file.
    if ($data->groupid) {
        $filename = 'training_group_'.$data->groupid.'_'.$id.'_summary_'.date('d-M-Y', time()).'.xls';
    } else {
        $filename = 'training_course_'.$id.'_summary_'.date('d-M-Y', time()).'.xls';
    }

    // Initialise the workbook object.
    $workbook = new MoodleExcelWorkbook("-");
    if (!$workbook) {
        die("Null workbook");
    }
    $workbook->send($filename);
    $xls_formats = report_trainingsessions_xls_formats($workbook);
    $worksheet = $workbook->add_worksheet($coursename);
    $worksheet->set_column(0, count($summarizedusers[0]) - 1, 30);

    // add a table header row
    $col = 0;
    foreach ($summarizedusers[0] as $fieldname => $field) {
        if (in_array($fieldname, $namedcols)) {
            $worksheet->write_string(0, $col, get_string($fieldname, 'report_trainingsessions'), $xls_formats['tt']);
        } else {
            // Eventually coming from variable grade column.
            $worksheet->write_string(0, $col, $fieldname, $xls_formats['tt']);
        }
        ++$col;
    }

    // Add a row for each user.
    $row = 1;
    foreach ($summarizedusers as $user) {
        $col = 0;
        foreach ($user as $fieldname => $field) {
            if (in_array($fieldname, $namedcols)) {
                // This is a named column so content is either text or duration.
                if (in_array($fieldname, $durationcols)) {
                    // $worksheet->write_string($row, $col, $field, $xls_formats['z']);
                    if ($field) {
                        $worksheet->write_number($row, $col, $field, $xls_formats['zt']);
                    }
                } else {
                    $worksheet->write_string($row, $col, $field, $xls_formats['z']);
                }
            }else{
                // This is a grade column so content is numeric.
                $worksheet->write_number($row, $col, $field, $xls_formats['z']);
            }
            ++$col;
        }
        ++$row;
    }

    $workbook->close();
}

