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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/task_form.php');

$id = required_param('id', PARAM_INT); // Origin course id.
$context = context_course::instance($id);
$url = new moodle_url('/report/trainingsessions/courseraw.task_receiver.php', array('id' => $id));
$PAGE->set_url($url);
$PAGE->set_context($context);

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('coursemisconf');
}

// Security.

require_login($course);

$form = new Task_Form(new moodle_url('/report/trainingsessions/index.php'));

$PAGE->set_pagelayout('admin');
$PAGE->set_heading('pluginname', 'report_trainingsessions');

if ($tdata = $form->get_data()) {

    $task = new StdClass;
    $task->id = $tdata->taskid;
    $task->courseid = $id;
    $task->taskname = $tdata->taskname;
    $task->outputdir = preg_replace('#/$#', '', $tdata->outputdir); // Removes trailing slash if given.
    $task->batchdate = $tdata->batchdate;
    $task->reportlayout = $tdata->reportlayout;
    $task->reportscope = $tdata->reportscope;
    $task->reportformat = $tdata->reportformat;

    $startday = $tdata->startday;
    $startmonth = $tdata->startmonth;
    $startyear = $tdata->startyear;
    $task->timefrom = mktime(0, 0, 0, $startmonth, $startday, $startyear);

    $endday = $tdata->endday;
    $endmonth = $tdata->endmonth;
    $endyear = $tdata->endyear;
    $task->timeto = mktime(0, 0, 0, $endmonth, $endday, $endyear);
    $task->timeto += DAYSECS - 1;

    $task->replay = 0 + @$tdata->replay;
    $task->replaydelay = $tdata->replaydelay;
    $task->groupid = $tdata->groupid;

    if (!empty($task->id)) {
        $oldrec = $DB->get_record('report_trainingsessions_btc', ['id' => $task->id]);
        $task->id = $oldrec->id;
        $DB->update_record('report_trainingsessions_btc', $task);
    } else {
        $DB->insert_record('report_trainingsessions_btc', $task);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('taskrecorded', 'report_trainingsessions'), 'success');
    $params = array('id' => $id, 'view' => 'courseraw', 'groupid' => $tdata->groupid);
    echo $OUTPUT->continue_button(new moodle_url('/report/trainingsessions/index.php', $params));
    echo $OUTPUT->footer();
    exit;
}

