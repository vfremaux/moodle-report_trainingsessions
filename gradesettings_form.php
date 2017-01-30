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

    protected $linkablemodules;

    public function definition() {
        global $COURSE, $OUTPUT;

        $this->linkablemodules = report_trainingsessions_get_linkable_modules($COURSE->id);

        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $COURSE->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'from');
        $mform->setType('from', PARAM_INT);

        $mform->addElement('hidden', 'to');
        $mform->setType('to', PARAM_INT);

        $mform->addElement('header', 'coursegradehead', get_string('coursegrade', 'report_trainingsessions'));

        $formgroup = array();
        $formgroup[] = &$mform->createElement('checkbox', 'coursegrade', get_string('addcoursegrade', 'report_trainingsessions'));
        $formgroup[] = &$mform->createElement('text', 'courselabel', '', array('size' => 60, 'maxlength' => 60));
        $mform->setType('courselabel', PARAM_TEXT);
        $mform->addGroup($formgroup, 'coursegroup', get_string('enablecoursescore', 'report_trainingsessions'), array(get_string('courselabel', 'report_trainingsessions').' '), false);

        $mform->addElement('header', 'modulegrades', get_string('modulegrades', 'report_trainingsessions'));

        $mform->addHelpButton('modulegrades', 'modulegrades', 'report_trainingsessions');

        /*
         * The linked modules portion goes here, but is forced in in the 'definition_after_data' function so
         * that we can get any elements added in the form and not overwrite them with what's in the database.
         */

        $mform->addElement('submit', 'addmodule', get_string('addmodulelabel', 'report_trainingsessions'),
                           array('title' => get_string('addmoduletitle', 'report_trainingsessions')));
        $mform->registerNoSubmitButton('addmodule');

        $mform->addElement('header', 'specialgrades', get_string('specialgrades', 'report_trainingsessions'));

        $mform->addElement('html', $OUTPUT->box_start('trainingsessions-fieldset'));

        $mform->addElement('radio', 'specialgrade', '', get_string('noextragrade', 'report_trainingsessions'), TR_TIMEGRADE_DISABLED);

        $mform->addElement('radio', 'specialgrade', '', get_string('addtimegrade', 'report_trainingsessions'), TR_TIMEGRADE_GRADE);

        $options = array(TR_GRADE_MODE_BINARY => get_string('binary', 'report_trainingsessions'),
                         TR_GRADE_MODE_DISCRETE => get_string('discrete', 'report_trainingsessions'),
                         TR_GRADE_MODE_CONTINUOUS => get_string('continuous', 'report_trainingsessions'));
        $mform->addElement('select', 'timegrademode', get_string('timegrademode', 'report_trainingsessions'), $options);
        $mform->disabledIf('timegrademode', 'specialgrade', 'neq', TR_TIMEGRADE_GRADE);

        $mform->addElement('radio', 'specialgrade', '', get_string('addtimebonus', 'report_trainingsessions'), TR_TIMEGRADE_BONUS);

        $options = array(TR_GRADE_MODE_DISCRETE => get_string('discrete', 'report_trainingsessions'),
                         TR_GRADE_MODE_CONTINUOUS => get_string('continuous', 'report_trainingsessions'));
        $mform->addElement('select', 'bonusgrademode', get_string('bonusgrademode', 'report_trainingsessions'), $options);
        $mform->disabledIf('bonusgrademode', 'specialgrade', 'neq', TR_TIMEGRADE_BONUS);

        $mform->addElement('html', $OUTPUT->box_end());

        $options = array(TR_GRADE_SOURCE_COURSE => get_string('coursetotaltime', 'report_trainingsessions'),
                         TR_GRADE_SOURCE_COURSE_EXT => get_string('extelapsed', 'report_trainingsessions'),
                         TR_GRADE_SOURCE_ACTIVITIES => get_string('activitytime', 'report_trainingsessions'));
        $mform->addElement('select', 'timegradesource', get_string('timesource', 'report_trainingsessions'), $options);
        $mform->disabledIf('timegradesource', 'specialgrade', 'eq', TR_TIMEGRADE_DISABLED);

        $mform->addElement('modgrade', 'timegrade', get_string('timegrade', 'report_trainingsessions'));

        $mform->addElement('text', 'timegraderanges', get_string('timegraderanges', 'report_trainingsessions'), array('size' => 80, 'maxlength' => 254));
        $mform->addHelpButton('timegraderanges', 'timegraderanges', 'report_trainingsessions');
        $mform->setType('timegraderanges', PARAM_TEXT);

        if (report_trainingsessions_supports_feature('xls', 'calculated')) {
            // Preliminary implementation. Not finished yet.
            $mform->addElement('header', 'calculatedgradehead', get_string('calculatedcolumns', 'report_trainingsessions'));

            $formgroup = array();
            $formgroup[] = $mform->createElement('text', 'calculated1', get_string('formula', 'report_trainingsessions'), array('size' => 60, 'maxlength' => 254));
            $mform->addHelpButton('calculated1', 'calculated', 'report_trainingsessions');
            $mform->setType('calculated1', PARAM_TEXT);
            $formgroup[] = $mform->createElement('text', 'calculated1label', get_string('formulalabel', 'report_trainingsessions'), array('size' => 40, 'maxlength' => 254));
            $mform->addGroup($formgroup, 'formula1group', get_string('xlsformula', 'report_trainingsessions').' 1', array(get_string('formulalabel', 'report_trainingsessions').' '), false);

            $formgroup = array();
            $formgroup[] = $mform->createElement('text', 'calculated2', get_string('formula', 'report_trainingsessions'), array('size' => 60, 'maxlength' => 254));
            $mform->addHelpButton('calculated2', 'calculated', 'report_trainingsessions');
            $mform->setType('calculated2', PARAM_TEXT);
            $formgroup[] = $mform->createElement('text', 'calculated2label', get_string('formulalabel', 'report_trainingsessions'), array('size' => 40, 'maxlength' => 254));
            $mform->addGroup($formgroup, 'formula2group', get_string('xlsformula', 'report_trainingsessions').' 1', array(get_string('formulalabel', 'report_trainingsessions').' '), false);
        }

        $this->add_action_buttons(true);
    }

    /**
     * Add the added activities portion only after the entire form has been created. That way,
     * we can act on previous added values that haven't been committed to the database.
     * Check for an 'addmodule' button. If the linked activities fields are all full, add an empty one.
     */
    function definition_after_data() {
        global $COURSE;

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
         * Get the existing linked activities from the database, unless this form has resubmitted itself, in
         * which case they will be in the form already.
         */
        $moduleids = array();

        if (empty($fdata)) {
            if ($linkedmodules = report_trainingsessions_get_graded_modules($COURSE->id)) {
                foreach ($linkedmodules as $cidx => $cmid) {
                    if ($cmid > 0) {
                        $moduleids[$cidx] = $cmid;
                    }
                }
            }
        } else {
            if (isset($fdata['moduleid'])) {
                foreach ($fdata['moduleid'] as $cidx => $cmid) {
                    if ($cmid > 0) {
                        $moduleids[$cidx] = $cmid;
                    }
                }
            }
        }

        if (isset($fdata['linkablemodules']) && is_array($fdata['linkablemodules'])) {
            foreach ($fdata['linkablemodules'] as $linkablemodule) {
                $moduleids[] = $linkablemodule;
            }
        }
        $moduleids = array_unique($moduleids);
        $ix = 0;
        foreach ($moduleids as $cidx => $modid) {
            $formgroup = array();
            $choices = array(
                '' => get_string('disabled', 'report_trainingsessions'),
                $modid => $this->linkablemodules[$modid]
            );
            $formgroup[] = &$mform->createElement('select', 'moduleid['.$ix.']', '', $choices);
            $mform->setDefault('moduleid['.$ix.']', $modid);
            $formgroup[] = & $mform->createElement('text', 'scorelabel['.$ix.']', '', array('maxlength' => 60));
            $mform->setType('scorelabel['.$ix.']', PARAM_TEXT);
            $label = get_string('modgrade', 'report_trainingsessions', ($ix + 1));
            $padding = array(' '.get_string('columnname', 'report_trainingsessions'));
            $group =& $mform->createElement('group', 'modgrade'.$ix, $label, $formgroup, $padding, false);
            $mform->insertElementBefore($group, 'addmodule');
            $ix++;
        }

        $availablemodules = $this->linkablemodules;
        unset($availablemodules[0]);
        $linkablemodules = $mform->createElement('select', 'linkablemodules',get_string('availableactivities', 'report_trainingsessions'), $availablemodules, array('size' => 10));
        $linkablemodules->setMultiple(true);

        $formgroup[] = $linkablemodules;
        $mform->insertElementBefore($linkablemodules, 'addmodule');
    }

    public function validation($data, $files) {

        $errors = parent::validation($data, $files);

        if (($data['specialgrade'] == TR_TIMEGRADE_GRADE) &&
                ($data['timegrademode'] == TR_GRADE_MODE_CONTINUOUS) &&
                        ($data['timegrade'] < 0)) {
            // Scales cannot be used in continuous mode.
            $errors['timegrademode'] = get_string('errorcontinuousscale', 'report_trainingsessions');
            $errors['timegrade'] = get_string('errorcontinuousscale', 'report_trainingsessions');
        }

        if (($data['specialgrade'] == TR_TIMEGRADE_BONUS) &&
                ($data['bonusgrademode'] == TR_GRADE_MODE_CONTINUOUS) &&
                        ($data['timegrade'] < 0)) {
            // Scales cannot be used in continuous mode.
            $errors['bonusgrademode'] = get_string('errorcontinuousscale', 'report_trainingsessions');
            $errors['timegrade'] = get_string('errorcontinuousscale', 'report_trainingsessions');
        }

        if (($data['specialgrade'] == TR_TIMEGRADE_GRADE) &&
                        ($data['timegrademode'] < TR_GRADE_MODE_CONTINUOUS) &&
                                (empty($data['timegraderanges']))) {
            $errors['timegrademode'] = get_string('errordiscretenoranges', 'report_trainingsessions');
            $errors['timegraderanges'] = get_string('errordiscretenoranges', 'report_trainingsessions');
        }

        if (($data['specialgrade'] == TR_TIMEGRADE_BONUS) &&
                        ($data['bonusgrademode'] < TR_GRADE_MODE_CONTINUOUS) &&
                                (empty($data['timegraderanges']))) {
            $errors['bonusgrademode'] = get_string('errordiscretenoranges', 'report_trainingsessions');
            $errors['timegraderanges'] = get_string('errordiscretenoranges', 'report_trainingsessions');
        }

        return $errors;
    }
}