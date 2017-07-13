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
 * Grade settings form
 *
 * @package    report_trainingsessions
 * @category   report
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');

class TrainingsessionsGradeSettingsForm extends moodleform {

    public function definition() {
        global $COURSE, $OUTPUT;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $COURSE->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'from');
        $mform->setType('from', PARAM_INT);

        $mform->addElement('hidden', 'to');
        $mform->setType('to', PARAM_INT);

        $mform->addElement('header', 'coursegradehead', get_string('coursegrade', 'report_trainingsessions'));

        $formgroup = array();
        $choices = array(
            0 => get_string('hide'),
            1 => get_string('show')
        );
        $formgroup[] = &$mform->createElement('select', 'coursegrade', '', $choices);
        $formgroup[] = &$mform->createElement('text', 'courselabel', '', array('size' => 60, 'maxlength' => 60));
        $mform->setDefault('coursegrade', true);
        $mform->setType('courselabel', PARAM_TEXT);
        $mform->setDefault('courselabel', 'Total');
        $label = get_string('enablecoursescore', 'report_trainingsessions');
        $seps = array(get_string('columnname', 'report_trainingsessions').' ');
        $mform->addGroup($formgroup, 'coursegroup', $label, $seps, false);

        $mform->addElement('header', 'modulegrades', get_string('modulegrades', 'report_trainingsessions'));

        $mform->addHelpButton('modulegrades', 'modulegrades', 'report_trainingsessions');

        /*
         * The linked modules portion goes here, but is forced in in the 'definition_after_data' function so
         * that we can get any elements added in the form and not overwrite them with what's in the database.
         */

        $mform->addElement('hidden', 'addmodule', get_string('addmodulelabel', 'report_trainingsessions'));
        $mform->setType('addmodule', PARAM_RAW);

        $this->add_action_buttons(true);
    }

    /**
     * Add the added activities portion only after the entire form has been created. That way,
     * we can act on previous added values that haven't been committed to the database.
     * Check for an 'addmodule' button. If the linked activities fields are all full, add an empty one.
     */
    public function definition_after_data() {
        global $COURSE, $DB;

        // Start process core datas (conditions, etc.).
        parent::definition_after_data();

        /*
         * This gets called more than once, and there's no way to tell which time this is, so set a
         * variable to make it as called so we only do this processing once.
         */
        if (!empty($this->def_after_data_done)) {
            return;
        }
        $this->def_after_data_done = true;
        $mform    =& $this->_form;
        $fdata = $mform->getSubmitValues();

        /*
         * Load the module grades settings
         */
        $grades = $DB->get_records('report_trainingsessions', array('courseid'=>$COURSE->id));
        $ix = 0;
        foreach ($grades as $ix => $gr) {
            if($gr->moduleid<=0) continue;
            $formgroup = array();
            $choices = array(
                0 => get_string('hide'),
                1 => get_string('show')
            );
            $formgroup[] = &$mform->createElement('hidden', 'moduleid['.$ix.']', $gr->moduleid);
            $formgroup[] = &$mform->createElement('select', 'displayed['.$ix.']', '', $choices);
            $mform->setDefault('displayed['.$ix.']', $gr->displayed);
            $formgroup[] = & $mform->createElement('text', 'scorelabel['.$ix.']', '', array('maxlength' => 60));
            $mform->setType('scorelabel['.$ix.']', PARAM_TEXT);
            $mform->setDefault('scorelabel['.$ix.']', $gr->label);
            $padding = array(' ', ' '.get_string('columnname', 'report_trainingsessions'));
            $group =& $mform->createElement('group', 'modgrade'.$ix, $gr->label, $formgroup, $padding, false);
            $mform->insertElementBefore($group, 'addmodule');
            $ix++;
        }

    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
