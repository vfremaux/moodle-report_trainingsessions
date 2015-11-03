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
$startday = optional_param('startday', -1, PARAM_INT) ; // from (-1 is from course start)
$startmonth = optional_param('startmonth', -1, PARAM_INT) ; // from (-1 is from course start)
$startyear = optional_param('startyear', -1, PARAM_INT) ; // from (-1 is from course start)
$endday = optional_param('endday', -1, PARAM_INT) ; // to (-1 is till now)
$endmonth = optional_param('endmonth', -1, PARAM_INT) ; // to (-1 is till now)
$endyear = optional_param('endyear', -1, PARAM_INT) ; // to (-1 is till now)

// calculate start time

$selform = new SelectorForm($id, 'courseraw');
if ($data = $selform->get_data()) {
} else {
    $data = new StdClass;
    $data->from = optional_param('from', -1, PARAM_NUMBER);
    $data->to = optional_param('to', -1, PARAM_INT);
    $data->groupid = optional_param('groupid', $USER->id, PARAM_INT);
    $data->fromstart = optional_param('fromstart', 0, PARAM_BOOL);
    $data->tonow = optional_param('tonow', 0, PARAM_BOOL);
    $data->output = optional_param('output', 'html', PARAM_ALPHA);
}

$context = context_course::instance($id);
$config = get_config('report_trainingsessions');

// calculate start time

if ($data->from == -1 || @$data->fromstart) {
    // maybe we get it from parameters
    $data->from = $course->startdate;
}

if ($data->to == -1 || @$data->tonow) {
    // maybe we get it from parameters
    $data->to = time();
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

if (!empty($data->groupid)) {
    $targetusers = groups_get_members($data->groupid);
    $groupname = $DB->get_field('groups', 'name', array('id' => $data->groupid));
} else {
    $targetusers = get_enrolled_users($context);

    if (count($targetusers) > 100 || !has_capability('moodle/site:accessallgroups', $context)) {

        // In that case we need force groupid to some value.
        $data->groupid = groups_get_course_group($COURSE);
        $groupname = $DB->get_field('groups', 'name', array('id' => $group->id));
        $targetusers = groups_get_members($data->groupid);

        if (count($targetusers) > 100) {
            $OUTPUT->notification(get_string('errorcoursetoolarge', 'report_trainingsessions'));
        }
    } else {
        // We can compile for all course.
        $data->goupid = 0;
        $groupname = '';
    }
}

// Filter out non compiling users.
trainingsessions_filter_unwanted_users($targetusers);

// Print result.

if (!empty($targetusers)) {

    if (count($targetusers) < 100) {
        // This is a quick immediate compilation for small groups.
        echo get_string('quickgroupcompile', 'report_trainingsessions', count($targetusers));

        $logs = use_stats_extract_logs($data->from, $data->to, array_keys($targetusers), $COURSE->id);
        $aggregate = use_stats_aggregate_logs_per_user($logs, 'module');

        $weeklogs = use_stats_extract_logs($data->to - DAYSECS * 7, $data->to, array_keys($targetusers), $COURSE->id);
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

        trainingsessions_add_graded_columns($resultset);
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

            trainingsessions_print_globalheader_raw($auser->id, $course->id, $globalresults, $rawstr, $data->from, $data->to);
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
    $form = new Task_Form(new moodle_url('/report/trainingsessions/index.php'), $currentcontext);

    // quick written controller for deletion
    if ($delete = optional_param('delete', '', PARAM_INT)) {
        unset($tasks[$delete]);
        set_config('trainingreporttasks', serialize($tasks));
    }

    if ($tdata = $form->get_data()) {

        $task = new StdClass;
        $task->id = $tdata->taskid;
        $task->courseid = $id;
        $task->taskname = $tdata->taskname;
        $task->outputdir = preg_replace('#/$#', '', $tdata->outputdir); //removes trailing slash if given
        $task->batchdate = $tdata->batchdate;
        $task->reportlayout = $tdata->reportlayout;
        $task->startday = $tdata->startday;
        $task->startmonth = $tdata->startmonth;
        $task->startyear = $tdata->startyear;
        $task->endday = $tdata->endday;
        $task->endmonth = $tdata->endmonth;
        $task->endyear = $tdata->endyear;
        $task->replay = 0 + @$tdata->replay;
        $task->replaydelay = $tdata->replaydelay;
        $task->groupid = $tdata->groupid;
        $maxtaskid++;

        if (!isset($tasks)) {
            $tasks = array();
        }
        $tasks[$task->id] = $task;
        set_config('trainingreporttasks', serialize($tasks));
        redirect(new moodle_url('/report/trainingsessions/index.php', array('id' => $id, 'view' => 'courseraw')));
    }

    if (!empty($CFG->trainingreporttasks)) {
        echo $OUTPUT->heading(get_string('scheduledbatches', 'report_trainingsessions'));

        $taskstr = get_string('taskname', 'report_trainingsessions');
        $dirstr = get_string('outputdir', 'report_trainingsessions');
        $datestr = get_string('batchdate', 'report_trainingsessions');
        $coursestr = get_string('course');
        $replaystr = get_string('replay', 'report_trainingsessions');
        $reportlayoutstr = get_string('reportlayout', 'report_trainingsessions');
        $groupstr = get_string('group');
        $table = new html_table();
        $table->head = array("<b>$taskstr</b>", "<b>$coursestr</b>", "<b>$datestr</b>", "<b>$dirstr</b>", "<b>$reportlayoutstr</b>", "<b>$replaystr</b>", '');
        $table->align = array('left', 'left', 'left', 'left', 'center', 'center', 'center');
        $table->width = '100%';
        $table->size = array('30%', '15%', '15%', '15%', '10%', '10%', '5%');

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
                $courseshort = $DB->get_field('course', 'shortname', array('id' => $task->courseid));
                $layoutimg = ($task->reportlayout == 'onefulluserpersheet') ? 'usersheets' : 'userlist';
                $layout = html_writer::tag('img', null, array('src' => $OUTPUT->pix_url($layoutimg, 'report_trainingsessions'), 'title' => get_string($layoutimg, 'report_trainingsessions')));
                $deleteurl = new moodle_url('report/trainingsessions/index.php', array('id' => $id, 'view' => 'courseraw', 'delete' => $task->id));
                $deleteimg = html_writer::tag('img', null, array('src' => $OUTPUT->pix_url('/t/delete'), 'title' => get_string('delete')));
                $commands = '<a href="'.$deleteurl.'">'.$deleteimg.'</a>';
                $params = array('id' => $id, 'from' => $task->from, 'to' => $task->to, 'outputdir' => urlencode($task->outputdir), 'reportlayout' => $task->reportlayout, 'runmode' => 'url');
                if ($task->groupid) {
                    $params['groupid'] = $task->groupid;
                }
                $batchurl = new moodle_url('/report/trainingsessions/groupxlsreport_batch.php', $params);
                $commands .= '&nbsp;'.html_writer::tag('a', get_string('interactive', 'report_trainingsessions'), array('href' => $batchurl, 'title' => get_string('interactivetitle', 'report_trainingsessions')));
                $table->data[] = array($task->taskname, "$groupname@$courseshort", userdate($task->batchdate), $task->outputdir, $layout, ($task->replay) ? $task->replaydelay.' s' : '-' , $commands);
            }
        }

        echo html_writer::table($table);
    }

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
    $formdata->groupid = $data->groupid;
    $form->set_data($formdata);
    $form->display();
} else {
    print_string('nothing', 'report_trainingsessions');
}

echo $OUTPUT->heading(get_string('reports', 'report_trainingsessions'));

$reportsfileurl = new moodle_url('/report/trainingsessions/filearea.php', array('id' => $id, 'view' => $view));
echo html_writer::tag('a', get_string('reportfilemanager', 'report_trainingsessions'), array('href' => $reportsfileurl));