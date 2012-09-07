<?php

/**
* direct log construction implementation
*
*/
	ob_start();
	
    include_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
    include_once $CFG->dirroot.'/report/trainingsessions/locallib.php';

// require login and make page start

    $id = required_param('id', PARAM_INT) ; // the course id

// calculate start time

	include_once 'selector_form.php';
    $selform = new SelectorForm($id, 'course');
    if ($data = $selform->get_data()){
	} else {
		$data->from = -1;
		$data->groupid = 0;
		$data->fromstart = 0;
		$data->output = 'html';
	}
	
	$context = context_course::instance($id);

// calculate start time

    if ($data->from == -1 || @$data->fromstart){ // maybe we get it from parameters
        $data->from = $course->startdate;
    }

	if ($data->output == 'html'){
	    $selform->set_data($data);
	    $selform->display();
	}
    
// compute target group

    if ($data->groupid){
        $targetusers = get_enrolled_users($context, '', $data->groupid);
    } else {
    	$targetusers = get_enrolled_users($context);
        // $targetusers = get_users_by_capability($context, 'moodle/course:view', 'u.id, firstname, lastname, email, institution', 'lastname');
    }

// get course structure

    $coursestructure = reports_get_course_structure($course->id, $items);

// print result

    if (!$asxls){

        echo "<link rel=\"stylesheet\" href=\"reports.css\" type=\"text/css\" />";

        if (!empty($targetusers)){
            foreach($targetusers as $auser){
        
                $logusers = $auser->id;
                $logs = use_stats_extract_logs($data->from, time(), $auser->id, $course->id);
                $aggregate = use_stats_aggregate_logs($logs, 'module');

                $data->items = $items;
                $data->done = 0;
                if (!empty($aggregate)){
                    foreach(array_keys($aggregate) as $module){
                    	// exclude from calculation some pseudo-modules that are not part of 
                    	// a course structure.
                    	if (preg_match('/course|user|upload|sessions/', $module)) continue;
                        $data->done += count($aggregate[$module]);
                    }
                	$data->sessions = 0 + count(@$aggregate['sessions']);
                } else {
                	$data->sessions = 0;
                }
                if ($data->done > $items) $data->done = $items;

                $data->linktousersheet = 1;
                training_reports_print_header_html($auser->id, $course->id, $data, true);
    
            }
        }

        $options['id'] = $course->id;
        $options['groupid'] = $data->groupid;
        $options['from'] = $data->from; // alternate way
        $options['output'] = 'xls'; // ask for XLS
        $options['asxls'] = 'xls'; // force XLS for index.php
        $options['view'] = 'course'; // force course view

        $url = $CFG->wwwroot.'/report/trainingsessions/index.php?id='.$course->id.'&amp;view=course&amp;groupid='.$data->groupid.'&amp;from='.$data->from.'&amp;output=xls&amp;asxls=1';
        echo '<br/><center>';
        // echo count($targetusers).' found in this selection';
        echo $OUTPUT->single_button($url, get_string('generateXLS', 'report_trainingsessions'), 'get');
        echo '</center>';
        echo '<br/>';
        
    } else {

        /// generate XLS
        require_once $CFG->libdir.'/excellib.class.php';

        if ($data->groupid){    
            $filename = 'training_group_'.$data->groupid.'_report_'.date('d-M-Y', time()).'.xls';
        } else {
            $filename = 'training_course_'.$id.'_report_'.date('d-M-Y', time()).'.xls';
        }

        $workbook = new MoodleExcelWorkbook("-");
        if (!$workbook){
        	die("Null workbook");
        }
        // Sending HTTP headers
        ob_end_clean();
        $workbook->send($filename);

        $xls_formats = training_reports_xls_formats($workbook);
        $startrow = 15;
    
        foreach($targetusers as $auser){

            $row = $startrow;
            $worksheet = training_reports_init_worksheet($auser->id, $row, $xls_formats, $workbook);
    
            $logusers = $auser->id;
            $logs = use_stats_extract_logs($data->from, time(), $auser->id, $course->id);
            $aggregate = use_stats_aggregate_logs($logs, 'module');
            
            $overall = training_reports_print_xls($worksheet, $coursestructure, $aggregate, $done, $row, $xls_formats);
            $data->items = $items;
            $data->done = $done;
            $data->elapsed = $overall->elapsed;
            $data->events = $overall->events;
            training_reports_print_header_xls($worksheet, $auser->id, $course->id, $data, $xls_formats);    

	        $worksheet = training_reports_init_worksheet($auser->id, $startrow, $xls_formats, $workbook, 'sessions');
	        training_reports_print_sessions_xls($worksheet, 15, @$aggregate['sessions'], $xls_formats);
	        training_reports_print_header_xls($worksheet, $auser->id, $course->id, $data, $xls_formats);
        }
        $workbook->close();
    }

?>