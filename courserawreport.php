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
if (!defined('MOODLE_INTERNAL')) die ('You cannot access directly to this script');

/**
 * direct log construction implementation
 *
 */
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/selector_form.php');
require_once($CFG->dirroot.'/report/trainingsessions/task_form.php');

$offset = optional_param('offset', 0, PARAM_INT);
$page = 20;

ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities

$id = required_param('id', PARAM_INT) ; // the course id

// For tasks
$selform = new StdClass();
$selform->from = optional_param_array('from', null, PARAM_INT);
if (empty($selform->from) || @$selform->fromstart) {
    // maybe we get it from parameters
    $from = $course->startdate;
    $selform->from['day'] = date('d', $from);
    $selform->from['year'] = date('Y', $from);
    $selform->from['month'] = date('m', $from);
} else {
    $from = mktime(0,0,0,$selform->from['month'], $selform->from['day'], $selform->from['year']);
}
$startday =  $selform->from['day']; // from (-1 is from course start)
$startmonth = $selform->from['month']; // from (-1 is from course start)
$startyear = $selform->from['year']; // from (-1 is from course start)

$selform->to = optional_param_array('to', null, PARAM_INT);
if (empty($selform->to) || @$selform->tonow) {
    // maybe we get it from parameters
    $to = time();
    $selform->to['day'] = date('d', $to);
    $selform->to['year'] = date('Y', $to);
    $selform->to['month'] = date('m', $to);
} else {
    $to = mktime(0,0,0,$selform->to['month'], $selform->to['day'], $selform->to['year']);
}
$endday =  $selform->to['day']; // to (-1 is from course start)
$endmonth = $selform->to['month']; // to (-1 is from course start)
$endyear = $selform->to['year']; // to (-1 is from course start)

// calculate start time
$selform->groupid = optional_param('groupid', '', PARAM_INT);
$selform->fromstart = optional_param('fromstart', 0, PARAM_BOOL);
$selform->tonow = optional_param('tonow', 0, PARAM_BOOL);

$selformform = new SelectorForm($id, 'courseraw');

$context = context_course::instance($id);
$config = get_config('report_trainingsessions');

// compute target group

if (!empty($selform->groupid)) {
    $targetusers = groups_get_members($selform->groupid);
    $groupname = $DB->get_field('groups', 'name', array('id' => $selform->groupid));
} else {
    $targetusers = get_enrolled_users($context);

    $hasgroups = $DB->count_records('groups', array('courseid' => $id));

    if ($hasgroups && count($targetusers) > 50 || !has_capability('moodle/site:accessallgroups', $context)) {
        // In that case we need force groupid to some value.
        $selform->groupid = groups_get_course_group($COURSE);
        $groupname = $DB->get_field('groups', 'name', array('id' => $group->id));
        $targetusers = groups_get_members($selform->groupid);

        if (count($targetusers) > 50) {
            $OUTPUT->notification(get_string('errorcoursetoolarge', 'report_trainingsessions'));
        }
    } else {
        // We can compile for all course.
        $selform->groupid = 0;
        $groupname = '';
    }
}

// Filter out non compiling users.
report_trainingsessions_filter_unwanted_users($targetusers, $course);

// Print result.
echo $OUTPUT->header();
echo $OUTPUT->container_start();
echo $renderer->tabs($course, $view, $from, $to);
echo $OUTPUT->container_end();

echo $OUTPUT->box_start('block');
$selformform->set_data($selform);
$selformform->display();
echo $OUTPUT->box_end();

if (!empty($targetusers)) {

    if (count($targetusers) < 50) {
        // This is a quick immediate compilation for small groups.
        echo get_string('quickgroupcompile', 'report_trainingsessions', count($targetusers));

        $logs = use_stats_extract_logs($from, $to, array_keys($targetusers), $COURSE->id);
        $aggregate = use_stats_aggregate_logs_per_user($logs, 'module');

        $weeklogs = use_stats_extract_logs($to - DAYSECS * 7, $to, array_keys($targetusers), $COURSE->id);
        $weekaggregate = use_stats_aggregate_logs_per_user($weeklogs, 'module');

        $timestamp = time();
        $rawstr = '';
        $resultset[] = get_string('group'); // groupname
        $resultset[] = get_string('idnumber'); // userid
        $resultset[] = get_string('lastname'); // user name 
        $resultset[] = get_string('firstname'); // user name 
        $resultset[] = get_string('firstenrolldate', 'report_trainingsessions'); // enrol start date
        $resultset[] = get_string('firstaccess'); // fist trace
        $resultset[] = get_string('lastaccess'); // last trace
        $resultset[] = get_string('startdate', 'report_trainingsessions'); // compile start date
        $resultset[] = get_string('todate', 'report_trainingsessions'); // compile end date
        $resultset[] = get_string('weekstartdate', 'report_trainingsessions'); // last week start date 
        $resultset[] = get_string('timeelapsed', 'report_trainingsessions');
        $resultset[] = get_string('timeelapsedcurweek', 'report_trainingsessions');

        report_trainingsessions_add_graded_columns($resultset);
        if (!empty($config->csv_iso)) {
            $rawstr = mb_convert_encoding(implode(';', $resultset)."\n", 'ISO-8859-1', 'UTF-8');
        } else {
            $rawstr = implode(';', $resultset)."\n";
        }

        foreach ($targetusers as $userid => $auser) {

            $logusers = $auser->id;
            echo "Compiling for ".fullname($auser).'<br/>';
            $globalresults = new StdClass;
            $globalresults->elapsed = 0;
            if (isset($aggregate[$userid])) {
                foreach ($aggregate[$userid] as $classname => $classarray) {
                    foreach ($classarray as $modid => $modulestat) {
                        // echo "$classname elapsed : $modulestat->elapsed <br/>";
                        // echo "$classname events : $modulestat->events <br/>";
                        $globalresults->elapsed += $modulestat->elapsed;
                    }
                }
            }

            $globalresults->weekelapsed = 0;
            if (isset($weekaggregate[$userid])) {
                foreach ($weekaggregate[$userid] as $classarray) {
                    foreach ($classarray as $modid => $modulestat) {
                        $globalresults->weekelapsed += $modulestat->elapsed;
                    }
                }
            }

            report_trainingsessions_print_globalheader_raw($auser->id, $course->id, $globalresults, $rawstr, $from, $to);
        }

        $fs = get_file_storage();

        // Prepare file record object.

        $fileinfo = array(
            'contextid' => $context->id, // ID of context (course context)
            'component' => 'report_trainingsessions',     // usually = table name
            'filearea' => 'rawreports',     // usually = table name
            'itemid' => $COURSE->id,               // usually = ID of row in table
            'filepath' => '/',           // any path beginning and ending in /
            'filename' => "raw_{$timestamp}.csv"); // any filename

        // Create file containing text
        $fs->delete_area_files($context->id, 'report_trainingsessions', 'rawreports', $COURSE->id);
        $fs->create_file_from_string($fileinfo, $rawstr);
    
        $strupload = get_string('uploadresult', 'report_trainingsessions');
        $fileurl = moodle_url::make_pluginfile_url($context->id, 'report_trainingsessions', 'rawreports', $fileinfo['itemid'], '/', 'raw_'.$timestamp.'.csv');
        echo '<p><br/>'.$strupload.': <a href="'.$fileurl.'"><img src="'.$OUTPUT->pix_url('f/spreadsheet').'" height="40" width="30" /></a></p>';

    } else {
        echo $OUTPUT->box_start();
        echo $OUTPUT->notification(get_string('toobig', 'report_trainingsessions'));
        echo $OUTPUT->box_end();
    }
}

// Print batch list

$maxtaskid = 0;
if (!empty($CFG->trainingreporttasks)) {
    $tasks = unserialize($CFG->trainingreporttasks);
    if (!empty($tasks)) {
        foreach($tasks as $tid => $t) {
            $maxtaskid = max($maxtaskid, $tid);
        }
    }
}
$maxtaskid++;

$currentcontext = array(
    'groupname' => $groupname,
    'startyear' => $startyear,
    'startmonth' => $startmonth,
    'startday' => $startday,
    'endyear' => $endyear,
    'endmonth' => $endmonth,
    'endday' => $endday,
);

$form = new Task_Form(new moodle_url('/report/trainingsessions/courseraw.task_receiver.php'), $currentcontext);

// quick written controller for deletion
if ($delete = optional_param('delete', '', PARAM_INT)) {
    unset($tasks[$delete]);
    set_config('trainingreporttasks', serialize($tasks));
}

if (!empty($CFG->trainingreporttasks)) {
    echo $OUTPUT->heading(get_string('scheduledbatches', 'report_trainingsessions'));

    $taskstr = get_string('taskname', 'report_trainingsessions');
    $dirstr = get_string('outputdir', 'report_trainingsessions');
    $datestr = get_string('batchdate', 'report_trainingsessions');
    $coursestr = get_string('course');
    $replaystr = get_string('replay', 'report_trainingsessions');
    $reportlayoutstr = get_string('reportlayout', 'report_trainingsessions');
    $reportformatstr = get_string('reportformat', 'report_trainingsessions');
    $groupstr = get_string('group');
    $table = new html_table();
    $table->head = array("<b>$taskstr</b>", "<b>$coursestr</b>", "<b>$datestr</b>", "<b>$dirstr</b>", "<b>$reportlayoutstr</b>", "<b>$reportformatstr</b>", "<b>$replaystr</b>", '');
    $table->align = array('left', 'left', 'left', 'left', 'center', 'center', 'center', 'center');
    $table->width = '100%';
    $table->size = array('30%', '15%', '10%', '10%', '10%', '10%', '10%', '5%');

    if (!empty($tasks)) {
        foreach ($tasks as $task) {
            if ($group = groups_get_group($task->groupid)) {
                $groupname = $group->name;
            } else {
                $groupname = get_string('course');
            }
            if ($task->startday != -1) {
                $task->from = mktime (0, 0, 0, $task->startmonth, $task->startday, $task->startyear);
            } else {
                $task->from = $DB->get_field('course', 'startdate', array('id' => $task->courseid));
            }
            if ($task->endday != -1) {
                $task->to = mktime (0, 0, 0, $task->endmonth, $task->endday, $task->endyear);
            } else {
                $task->to = time();
            }

            if (@$task->reportscope == 'allcourses') {
                $scope = "$groupname@*";
            } else {
                $courseshort = $DB->get_field('course', 'shortname', array('id' => $task->courseid));
                $scope = "$groupname@$courseshort";
            }

            switch($task->reportlayout) {
                case 'onefulluserpersheet':
                    $layoutimg = 'usersheets';
                    break;
                case 'oneuserperrow' :
                    $layoutimg = 'userlist';
                    break;
                default:
                    $layoutimg = 'sessions';
            }
            $layout = html_writer::tag('img', null, array('src' => $OUTPUT->pix_url($layoutimg, 'report_trainingsessions'), 'title' => get_string($layoutimg, 'report_trainingsessions')));

            if (empty($task->reportformat)) {
                $task->reportformat = 'csv';
            }
            $ICONS = array('pdf' => 'pdf', 'csv' => 'writer', 'xls' => 'spreadsheet');
            $format = html_writer::tag('img', null, array('src' => $OUTPUT->pix_url('f/'.$ICONS[$task->reportformat].'-32'), 'title' => get_string($task->reportformat, 'report_trainingsessions')));

            $deleteurl = new moodle_url('/report/trainingsessions/index.php', array('id' => $id, 'view' => 'courseraw', 'delete' => $task->id));
            $deleteimg = html_writer::tag('img', null, array('src' => $OUTPUT->pix_url('/t/delete'), 'title' => get_string('delete')));

            $commands = '<a href="'.$deleteurl.'">'.$deleteimg.'</a>';

            $params = array('id' => $id, 'from' => $task->from, 'to' => $task->to, 'outputdir' => urlencode($task->outputdir), 'reportlayout' => $task->reportlayout, 'reportscope' => @$task->reportscope, 'runmode' => 'url');
            if ($task->groupid) {
                $params['groupid'] = $task->groupid;
            }
            $batchurl = new moodle_url('/report/trainingsessions/group'.$task->reportformat.'report_batch.php', $params);
            $commands .= '&nbsp;'.html_writer::tag('a', get_string('interactive', 'report_trainingsessions'), array('href' => $batchurl, 'target' => '_blank', 'title' => get_string('interactivetitle', 'report_trainingsessions')));

            switch($task->replay) {
                case TASK_REPLAY:
                    $replayimg = html_writer::tag('img', '', array('src' => $OUTPUT->pix_url('replay', 'report_trainingsessions')));
                    break;
                case TASK_SHIFT:
                    $replayimg = html_writer::tag('img', '', array('src' => $OUTPUT->pix_url('periodshift', 'report_trainingsessions')));
                    break;
                case TASK_SHIFT_TO:
                    $replayimg = html_writer::tag('img', '', array('src' => $OUTPUT->pix_url('endshift', 'report_trainingsessions')));
                    break;
                default:
            }

            $table->data[] = array($task->taskname, $scope, userdate($task->batchdate), $task->outputdir, $layout, $format, ($task->replay) ? format_time($task->replaydelay * 60).' s<br/>'.$replayimg : '-' , $commands);
        }
    }

    echo html_writer::table($table);
}

if (!empty($targetusers)) {
    $formdata = new StdClass;
    $formdata->id = $id;
    $formdata->view = $view;
    $formdata->startday = $startday;
    $formdata->startmonth = $startmonth;
    $formdata->startyear = $startyear;
    $formdata->endday = $endday;
    $formdata->endmonth = $endmonth;
    $formdata->endyear = $endyear;
    $formdata->taskid = $maxtaskid;
    $formdata->groupid = $selform->groupid;
    $form->set_data($formdata);
    $form->display();
} else {
    echo $OUTPUT->box(get_string('nothing', 'report_trainingsessions'), 'report-trainingsession userinfobox');
}

echo $OUTPUT->heading(get_string('reports', 'report_trainingsessions'));

$reportsfileurl = new moodle_url('/report/trainingsessions/filearea.php', array('id' => $id, 'view' => $view));
echo html_writer::tag('a', get_string('reportfilemanager', 'report_trainingsessions'), array('href' => $reportsfileurl));