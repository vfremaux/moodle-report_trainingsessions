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
 * the gradesettings service allows configuring the grades to be added to the trainingsession
 * report for this course.
 * Grades will be appended to the time report
 *
 * The global course final grade can be selected along with specified modules to get score from.
 *
 * @package    report_trainingsessions
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @version    moodle 2.x
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
require_once($CFG->dirroot.'/report/trainingsessions/gradesettings_form.php');

$id = required_param('id', PARAM_INT); // Course id.
$from = required_param('from', PARAM_INT);
$to = required_param('to', PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourse');
}

$context = context_course::instance($course->id);

require_course_login($course);
require_capability('report/trainingsessions:downloadreports', $context);

$params = array('id' => $id, 'from' => $from, 'to' => $to);
$url = new moodle_url('/report/trainingsessions/gradessettings.php', $params);
$PAGE->set_url($url);
$PAGE->set_heading(get_string('gradesettings', 'report_trainingsessions'));
$PAGE->set_title(get_string('gradesettings', 'report_trainingsessions'));
$PAGE->navbar->add(get_string('gradesettings', 'report_trainingsessions'));

$form = new TrainingsessionsGradeSettingsForm();

$renderer = $PAGE->get_renderer('report_trainingsessions');
$coursemodinfo = get_fast_modinfo($course->id);

if ($data = $form->get_data()) {

    // Activate or desactivate course grade.
    $rec = new StdClass();
    $rec->id = $DB->get_field('report_trainingsessions', 'id', array('courseid'=>$course->id, 'moduleid'=>0));
    $rec->label = $data->courselabel;
    if (!empty($data->coursegrade)) {
        $rec->displayed = 1;
    } else {
        $rec->displayed = 0;
    }
    $DB->update_record('report_trainingsessions', $rec);

    // Activate or desactivate module grades.
    if (property_exists($data, 'moduleid')) {
        foreach ($data->moduleid as $ix => $moduleid) {
            if ($moduleid) {
                $rec = new StdClass();
                $rec->id = $DB->get_field('report_trainingsessions', 'id', array('courseid'=>$course->id, 'moduleid'=>$moduleid));
                if(!empty($data->scorelabel[$ix])) $rec->label = $data->scorelabel[$ix];
                $rec->displayed = $data->displayed[$ix];
                $DB->update_record('report_trainingsessions', $rec);
            }
        }
    }

    $params = array('id' => $COURSE->id, 'view' => 'gradesettings', 'from' => $from, 'to' => $to);
    redirect(new moodle_url('/report/trainingsessions/gradessettings.php', $params));
}

echo $OUTPUT->header();

echo $renderer->tabs($course, 'gradesettings', $from, $to);

echo $OUTPUT->heading(get_string('scoresettings', 'report_trainingsessions'));

echo $OUTPUT->notification(get_string('scoresettingsadvice', 'report_trainingsessions'), \core\output\notification::NOTIFY_INFO);

// Prepare form feed in.
$alldata = $DB->get_records('report_trainingsessions', array('courseid' => $COURSE->id), 'sortorder');
if ($alldata) {
    $ix = 0;
    $formdata = new StdClass();
    $formdata->from = $from;
    $formdata->to = $to;
    foreach ($alldata as $datum) {
        if ($datum->moduleid == 0) {
            if($datum->displayed) {
                $formdata->coursegrade = 1;
                $formdata->courselabel = $datum->label;
            } else {
                $formdata->coursegrade = 0;
                $formdata->courselabel = $datum->label;
            }
        } else if ($datum->moduleid > 0) {
            $formdata->moduleid[$ix] = $datum->moduleid;
            $formdata->scorelabel[$ix] = $datum->label;
            $ix++;
        } else if ($datum->moduleid == TR_LINEAGGREGATORS) {
            $formdata->lineaggregators = $datum->label;
        } else if ($datum->moduleid >= TR_XLSGRADE_FORMULA1) {
            $ix = $datum->moduleid - TR_XLSGRADE_FORMULA1 + 1;
            $formulakey = 'calculated'.$ix;
            $labelkey = 'calculated'.$ix.'label';
            $formdata->$labelkey = $datum->label;
            $formdata->$formulakey = $datum->ranges;
        } else {
            // Special grades.
            $formdata->specialgrade = $datum->moduleid;
            $ranges = json_decode(@$datum->ranges);
            $ranges = (array)$ranges;
            if (!empty($ranges)) {
                $formdata->timegraderanges = implode(',', (array)$ranges['ranges']);
                $formdata->timegrademode = @$ranges['timemode'];
                $formdata->bonusgrademode = @$ranges['bonusmode'];
                $formdata->timegradesource = @$ranges['timesource'];
            }
            $formdata->timegrade = $datum->grade;
        }
    }
    $form->set_data($formdata);
} else {
    $form->from = $from;
    $form->to = $to;
    $form->set_data($form);
}

// Display form.
$form->display();

echo $OUTPUT->footer();
