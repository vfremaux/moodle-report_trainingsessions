<?php

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

        $optionsstr = get_string('group').': '.$this->_customdata['groupname'];
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

        $layoutoptions = array(
            'onefulluserpersheet' => get_string('onefulluserpersheet', 'report_trainingsessions'),
            'oneuserperrow' => get_string('oneuserperrow', 'report_trainingsessions')
        );

        $mform->addElement('select', 'reportlayout', get_string('reportlayout', 'report_trainingsessions'), $layoutoptions);
        $mform->addElement('text', 'outputdir', get_string('outputdir', 'report_trainingsessions'), array('size' => 80));
        $mform->setType('outputdir', PARAM_PATH);
        $mform->addElement('date_time_selector', 'batchdate', get_string('batchdate', 'report_trainingsessions'));
        $mform->addElement('checkbox', 'replay', get_string('replay', 'report_trainingsessions'));

        $mform->addElement('text', 'replaydelay', get_string('replaydelay', 'report_trainingsessions'), array('size' => 10));
        $mform->setType('replaydelay', PARAM_INT);
        $mform->setDefault('replaydelay', 1440);
        $mform->disabledIf('replaydelay', 'replay');

        $this->add_action_buttons();
    }

    function validation($data, $files = null) {
        global $CFG;
        
        if (preg_match('#^'.$CFG->dataroot.'#', $data['outputdir'])){
            $errors['outputdir'] = get_string('errornoabsolutepath', 'report_trainingsessions');
        }

        if (preg_match('#^/#', $data['outputdir'])){
            $errors['outputdir'] = get_string('errornoabsolutepath', 'report_trainingsessions');
        }

    }
}
