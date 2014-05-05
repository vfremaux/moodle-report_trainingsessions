<?php


/**
* This script handles the report generation in batch task for a single group. 
* It may produce a group csv report.
* groupid must be provided. 
* This script should be sheduled in a redirect bouncing process for maintaining
* memory level available for huge batches. 
*/

	include '../../../config.php';
	ob_start();
    include_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
    include_once $CFG->dirroot.'/course/report/trainingsessions/locallib.php';
    include_once $CFG->dirroot.'/course/report/trainingsessions/xlsrenderers.php';
    require_once($CFG->libdir.'/excellib.class.php');

    $id = required_param('id', PARAM_INT) ; // the course id
    $startday = optional_param('startday', -1, PARAM_INT) ; // from (-1 is from course start)
    $startmonth = optional_param('startmonth', -1, PARAM_INT) ; // from (-1 is from course start)
    $startyear = optional_param('startyear', -1, PARAM_INT) ; // from (-1 is from course start)
    $endday = optional_param('endday', -1, PARAM_INT) ; // to (-1 is till now)
    $endmonth = optional_param('endmonth', -1, PARAM_INT) ; // to (-1 is till now)
    $endyear = optional_param('endyear', -1, PARAM_INT) ; // to (-1 is till now)
    $fromstart = optional_param('fromstart', 0, PARAM_INT) ; // force reset to course startdate
    $from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
    $to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
    $groupid = required_param('groupid', PARAM_INT) ; // group id
    $timesession = required_param('timesession', PARAM_INT) ; // time of the generation batch
    $readabletimesession = date('Ymd_H_i_s', $timesession);
    $sessionday = date('Ymd', $timesession);

    ini_set('memory_limit', '512M');

    if (!$course = get_record('course', 'id', $id)){
    	die ('Invalid course ID');
    }
    $context = get_context_instance(CONTEXT_COURSE, $course->id);

    $coursestructure = reports_get_course_structure($course->id, $items);

// TODO : secure groupid access depending on proper capabilities

// calculate start time

    if ($from == -1){ // maybe we get it from parameters
        if ($startday == -1 || $fromstart){
            $from = $course->startdate;
        } else {
            if ($startmonth != -1 && $startyear != -1){
                $from = mktime(0, 0, 8, $startmonth, $startday, $startyear);
            } else { 
                print_error('Bad start date');
            }
        }
    }

    if ($to == -1){ // maybe we get it from parameters
        if ($endday == -1){
            $to = time();
        } else {
            if ($endmonth != -1 && $endyear != -1)
                $to = mktime(0,0,8,$endmonth, $endday, $endyear);
            else 
                print_error('Bad end date');
        }
    }
    
// compute target group

	if ($groupid){
		$group = get_record('groups', 'id', $groupid);
    	$targetusers = groups_get_members($groupid);
	} else {
    	$targetusers = get_users_by_capability($context, 'moodle/course:view', 'u.id, firstname, lastname, email, institution, idnumber', 'lastname');
	}


	// filters teachers out
    foreach($targetusers as $uid => $user){
    	if (has_capability('moodle/legacy:teacher', $context, $user->id) || has_capability('moodle/legacy:editingteacher', $context, $user->id)){
    		unset($targetusers[$uid]);
    	}
    }

// print result

    if (!empty($targetusers)){
        /// generate XLS

        if ($groupid){    
            $filename = "trainingsessions_group_{$groupid}_report_".date('d-M-Y', time()).".xls";
        } else {
            $filename = "trainingsessions_course_{$course->id}_report_".date('d-M-Y', time()).".xls";
        }
        $workbook = new MoodleExcelWorkbook("-");
        // Sending HTTP headers
        ob_end_clean();
        $workbook->send($filename);

        $xls_formats = training_reports_xls_formats($workbook);
        $startrow = 15;
    
        foreach($targetusers as $auser){

            $row = $startrow;
            $worksheet = training_reports_init_worksheet($auser->id, $row, $xls_formats, $workbook);
    
            $logusers = $auser->id;
            $logs = use_stats_extract_logs($from, time(), $auser->id, $course->id);
            $aggregate = use_stats_aggregate_logs($logs, 'module');
            
            $overall = training_reports_print_xls($worksheet, $coursestructure, $aggregate, $done, $row, $xls_formats);
            $data->items = $items;
            $data->done = $done;
            $data->from = $from;
            $data->elapsed = $overall->elapsed;
            $data->events = $overall->events;
            training_reports_print_header_xls($worksheet, $auser->id, $course->id, $data, $xls_formats);    

	        $worksheet = training_reports_init_worksheet($auser->id, $startrow, $xls_formats, $workbook, 'sessions');
	        training_reports_print_sessions_xls($worksheet, 15, @$aggregate['sessions'], $xls_formats);
	        training_reports_print_header_xls($worksheet, $auser->id, $course->id, $data, $xls_formats);
        }
        $workbook->close();
    }
    
    // echo '200';
?>