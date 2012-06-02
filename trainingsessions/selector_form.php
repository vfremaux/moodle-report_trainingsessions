<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot . '/lib/formslib.php');

class SelectorForm extends moodleform{
	
	var $courseid;
	var $mode;
	
	public function __construct($courseid, $mode = 'user'){
		$this->courseid = $courseid;
		$this->mode = $mode;
		parent::__construct();
	}
	
	public function definition(){
		
        $mform = $this->_form;
        
        $mform->addElement('hidden', 'id', $this->courseid);
        $mform->addElement('hidden', 'view', $this->mode);
        $mform->addElement('hidden', 'output', 'html');
        
        $dateparms = array(
		    'startyear' => 2008, 
		    'stopyear'  => 2020,
		    'timezone'  => 99,
		    'applydst'  => true, 
		    'optional'  => false
		);
		$group[] = & $mform->createElement('date_selector', 'from', get_string('from'), $dateparms);

        $context = context_course::instance($this->courseid);
        
        if ($this->mode == 'user'){
        
	        $users = get_users_by_capability($context, 'moodle/course:update', 'u.id, firstname, lastname', 'lastname');
	        $useroptions = array();
	        foreach($users as $user){
	           $useroptions[$user->id] = fullname($user);
	        }
	        $group[] = & $mform->createElement('select', 'userid', get_string('user'), $useroptions);
	
			$mform->addGroup($group, 'selectarr', get_string('from').':', array('&nbsp; &nbsp;'.get_string('user').':&nbsp; &nbsp;'), false);
		} else {
			$groups = groups_get_all_groups($this->courseid);

			$groupoptions = array();
			foreach($groups as $g){
				$groupoptions[$g->id] = $g->name;
			}
	        $group[] = & $mform->createElement('select', 'groupid', get_string('group'), $groupoptions);
	
			$mform->addGroup($group, 'selectarr', get_string('from').':', array('&nbsp; &nbsp;'.get_string('group').':&nbsp; &nbsp;'), false);
			
		}		
		$mform->addElement('checkbox', 'fromstart', get_string('updatefromcoursestart', 'report_trainingsessions'));
		$mform->addElement('submit', 'go_btn', get_string('update')); 
	}
}