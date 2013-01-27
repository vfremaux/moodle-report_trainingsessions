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
		global $USER;
		
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
        
        if ($this->mode == 'user' || $this->mode == 'allcourses'){
        
	        if (has_capability('report/trainingsessions:viewother', $context)){
		        $users = get_enrolled_users($context);
		        $useroptions = array();
		        foreach($users as $user){
		        	if (has_capability('report/trainingsessions:iscompiled', $context, $user->id)){
			           $useroptions[$user->id] = fullname($user);
			       }
		        }
		        $group[] = & $mform->createElement('select', 'userid', get_string('user'), $useroptions);
		
				$mform->addGroup($group, 'selectarr', get_string('from').':', array('&nbsp; &nbsp;'.get_string('user').':&nbsp; &nbsp;'), false);
			}
		} else {
			$groups = groups_get_all_groups($this->courseid);

			$groupoptions = array();
			if (has_capability('moodle/site:accessallgroups', $context, $USER->id)){
				$groupoptions[0] = get_string('allgroups');
			}
			foreach($groups as $g){
				$groupoptions[$g->id] = $g->name;
			}
	        $group[] = & $mform->createElement('select', 'groupid', get_string('group'), $groupoptions);
	
			$mform->addGroup($group, 'selectarr', get_string('from').':', array('&nbsp; &nbsp;'.get_string('group').':&nbsp; &nbsp;'), false);
	
			if ($this->mode == 'courseraw'){
				$mform->addElement('date_selector', 'to', get_string('to'), $dateparms);
			} 
		
		}		
		$updatefromstr = ($this->mode == 'user') ? get_string('updatefromcoursestart', 'report_trainingsessions') : get_string('updatefromaccountstart', 'report_trainingsessions') ;
		$mform->addElement('checkbox', 'fromstart', $updatefromstr);
		$mform->addElement('submit', 'go_btn', get_string('update')); 
	}
}