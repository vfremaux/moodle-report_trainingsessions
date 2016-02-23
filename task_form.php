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

defined('MOODLE_INTERNAL') || die();

/**
 * @package    report_trainingsessions
 * @category   report
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once $CFG->libdir.'/formslib.php';

class Task_Form extends moodleform {

    function definition() {

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'view');
        $mform->setType('view', PARAM_TEXT);

        $mform->addElement('hidden', 'startday');
        $mform->setType('startday', PARAM_INT);

        $mform->addElement('hidden', 'startmonth');
        $mform->setType('startmonth', PARAM_INT);

        $mform->addElement('hidden', 'startyear');
        $mform->setType('startyear', PARAM_INT);

        $mform->addElement('hidden', 'endday');
        $mform->setType('endday', PARAM_INT);

        $mform->addElement('hidden', 'endmonth');
        $mform->setType('endmonth', PARAM_INT);

        $mform->addElement('hidden', 'endyear');
        $mform->setType('endyear', PARAM_INT);

        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('hidden', 'taskid');
        $mform->setType('taskid', PARAM_INT);

        $mform->addElement('header', 'headernewtask', get_string('newtask', 'report_trainingsessions'));

        $optionsstr = get_string('group').': '.$this->_customdata['groupname'].' ';
        if ($this->_customdata['startyear'] == -1) {
            $optionsstr .= '<br/>'.get_string('range', 'report_trainingsessions').': '.get_string('coursestart', 'report_trainingsessions');
        } else {
            $optionsstr .= '<br/>'.get_string('range', 'report_trainingsessions').': '.$this->_customdata['startyear'].'-'.$this->_customdata['startmonth'].'-'.$this->_customdata['startday'];
        }
        if ($this->_customdata['endyear'] == -1) {
            $optionsstr .= ' => '.get_string('now', 'report_trainingsessions');
        } else {
            $optionsstr .= ' => '.$this->_customdata['endyear'].'-'.$this->_customdata['endmonth'].'-'.$this->_customdata['endday'];
        }

        $mform->addElement('hidden', 'taskname', get_string('task', 'report_trainingsessions', $optionsstr));
        $mform->setType('taskname', PARAM_TEXT);

        $mform->addElement('static', 'tasknamestatic', get_string('taskname', 'report_trainingsessions'), get_string('task', 'report_trainingsessions', $optionsstr));

        // Which data to export and how to build the report.
        $layoutoptions = array(
            'onefulluserpersheet' => get_string('onefulluserpersheet', 'report_trainingsessions'),
            'oneuserperrow' => get_string('oneuserperrow', 'report_trainingsessions'),
            'sessionsonly' => get_string('sessionsonly', 'report_trainingsessions')
        );

        $mform->addElement('select', 'reportlayout', get_string('reportlayout', 'report_trainingsessions'), $layoutoptions);

        // Which data to export and how to build the report.
        $scopeoptions = array(
            'currentcourse' => get_string('currentcourse', 'report_trainingsessions'),
            'allcourses' => get_string('allcourses', 'report_trainingsessions'),
        );

        $mform->addElement('select', 'reportscope', get_string('reportscope', 'report_trainingsessions'), $scopeoptions);

        // What file format (file renderer) to use.
        $formatoptions = array(
            'xls' => get_string('xls', 'report_trainingsessions'),
            'csv' => get_string('csv', 'report_trainingsessions'),
            'pdf' => get_string('pdf', 'report_trainingsessions')
        );
        $mform->addHelpButton('reportscope', 'reportscope', 'report_trainingsessions');

        $mform->addElement('select', 'reportformat', get_string('reportformat', 'report_trainingsessions'), $formatoptions);

        // In which directory to store results
        $mform->addElement('text', 'outputdir', get_string('outputdir', 'report_trainingsessions'), array('size' => 80));
        $mform->setType('outputdir', PARAM_PATH);
        $mform->addHelpButton('outputdir', 'outputdir', 'report_trainingsessions');

        // When to perform the report
        $mform->addElement('date_time_selector', 'batchdate', get_string('batchdate', 'report_trainingsessions'));
        $mform->addHelpButton('batchdate', 'batchdate', 'report_trainingsessions');

        // Do the report needs to be rerun later ?
        $replayoptions = array(TASK_SINGLE => get_string('singleexec', 'report_trainingsessions'), TASK_REPLAY => get_string('replay', 'report_trainingsessions'), TASK_SHIFT => get_string('periodshift', 'report_trainingsessions'), TASK_SHIFT_TO => get_string('periodshiftto', 'report_trainingsessions'));
        $mform->addElement('select', 'replay', get_string('replay', 'report_trainingsessions'), $replayoptions);

        // If rerun, in what delay ?
        $mform->addElement('text', 'replaydelay', get_string('replaydelay', 'report_trainingsessions'), array('size' => 10));
        $mform->setType('replaydelay', PARAM_INT);
        $mform->setDefault('replaydelay', 1440);
        $mform->disabledIf('replaydelay', 'replay');
        $mform->addHelpButton('replaydelay', 'replaydelay', 'report_trainingsessions');

        $this->add_action_buttons();
    }

    function validation($data, $files = null) {
        global $CFG;

        if (preg_match('#^'.$CFG->dataroot.'#', $data['outputdir'])) {
            $errors['outputdir'] = get_string('errornoabsolutepath', 'report_trainingsessions');
        }

        if (preg_match('#^/#', $data['outputdir'])) {
            $errors['outputdir'] = get_string('errornoabsolutepath', 'report_trainingsessions');
        }
    }
}
