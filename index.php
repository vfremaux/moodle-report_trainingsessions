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
 * @package    report
 * @version    moodle 2.x
 * @subpackage trainingsessions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

	require_once('../../config.php');
	require_once($CFG->libdir.'/adminlib.php');
	require_once($CFG->dirroot.'/lib/statslib.php');

	$id			= required_param('id', PARAM_INT); // course id
	$output		= optional_param('output', 'html', PARAM_ALPHA);
	$view		= optional_param('view', 'user', PARAM_ALPHA);
	$report		= optional_param('report', STATS_REPORT_ACTIVE_COURSES, PARAM_INT);
	$time		= optional_param('time', 0, PARAM_INT);

	// form bounce somewhere ? 
	$view = (empty($view)) ? 'user' : $view ;
	$output = (empty($output)) ? 'html' : $output ;
    
	if (!$course = $DB->get_record('course', array('id' => $id))) {
		print_error('invalidcourse');
	}


	require_course_login($course);
	$context = context_course::instance($course->id);
	require_capability('report/trainingsessions:view', $context);

	$PAGE->set_url('/report/trainingsessions/index.php', array('id' => $id));
	$PAGE->set_heading(get_string($view, 'report_trainingsessions'));
	$PAGE->set_title(get_string($view, 'report_trainingsessions'));
	$PAGE->navbar->add(get_string($view, 'report_trainingsessions'));

	add_to_log($course->id, 'course', 'trainingreports view', $CFG->wwwroot."/report/trainingsessions/index.php?id=$course->id", $course->id);

	$strreports = get_string('reports');
	$strcourseoverview = get_string('trainingsessions', 'report_trainingsessions');

	if ($output == 'html'){
		echo $OUTPUT->header();	    
	    $OUTPUT->container_start();

		/// Print tabs with options for user
		$rows[0][] = new tabobject('user', $CFG->wwwroot."/report/trainingsessions/index.php?id={$course->id}&amp;view=user", get_string('user', 'report_trainingsessions'));
        if (has_capability('report/trainingsessions:viewother', $context)){
	        $rows[0][] = new tabobject('course', $CFG->wwwroot."/report/trainingsessions/index.php?id={$course->id}&amp;view=course", get_string('course', 'report_trainingsessions'));
	        $rows[0][] = new tabobject('courseraw', $CFG->wwwroot."/report/trainingsessions/index.php?id={$course->id}&amp;view=courseraw", get_string('courseraw', 'report_trainingsessions'));
	    }
        $rows[0][] = new tabobject('allcourses', $CFG->wwwroot."/report/trainingsessions/index.php?id={$course->id}&amp;view=allcourses", get_string('allcourses', 'report_trainingsessions'));
		
	    print_tabs($rows, $view);
	
	    $OUTPUT->container_end();
	}

	@ini_set('max_execution_time','3000');
	raise_memory_limit('250M');

	if (file_exists($CFG->dirroot."/report/trainingsessions/{$view}report.php")){
	    include_once $CFG->dirroot."/report/trainingsessions/{$view}report.php";
	} else {
	    print_error('errorbadviewid', 'report_trainingsessions');
	}

	if ($output == 'html') echo $OUTPUT->footer();
