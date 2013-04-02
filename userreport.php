<?php

/**
* direct log construction implementation
*
*/
	ob_start();

    include_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
    include_once $CFG->dirroot.'/course/report/trainingsessions/locallib.php';

// require login and make page start

    $startday = optional_param('startday', -1, PARAM_INT) ; // from (-1 is from course start)
    $startmonth = optional_param('startmonth', -1, PARAM_INT) ; // from (-1 is from course start)
    $startyear = optional_param('startyear', -1, PARAM_INT) ; // from (-1 is from course start)
    $fromstart = optional_param('fromstart', 0, PARAM_INT) ; // force reset to course startdate
    $from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
    $userid = optional_param('userid', $USER->id, PARAM_INT) ; // admits special values : -1 current group, -2 course users
    $output = optional_param('output', 'html', PARAM_ALPHA) ; // 'html' or 'xls'    

	$canseeothers = has_capability('coursereport/trainingsessions:viewother', $context);

// calculate start time

    if ($from == -1){ // maybe we get it from parameters
        if ($startday == -1 || $fromstart){
            $from = $course->startdate;
        } else {
            if ($startmonth != -1 && $startyear != -1){
                $from = mktime(0,0,8,$startmonth, $startday, $startyear);
            } else {
                print_error('Bad start date');
            }
        }
    }

// get data

	if (!$canseeothers){
		// restrict to view yourself only
		$userid = $USER->id;
	}

    $logs = use_stats_extract_logs($from, time(), $userid, $course->id);
    $aggregate = use_stats_aggregate_logs($logs, 'module');
    
// get course structure

    $coursestructure = reports_get_course_structure($course->id, $items);
    
// print result

    if ($output == 'html'){
        // time period form

        echo "<link rel=\"stylesheet\" href=\"reports.css\" type=\"text/css\" />";

        include "selector_form.html";
        echo '<br/>';
        
        $str = '';
        $dataobject = training_reports_print_html($str, $coursestructure, $aggregate, $done);
        $dataobject->items = $items;
        $dataobject->done = $done;
		$dataobject->sessions = count($aggregate['sessions']);
		
        if ($dataobject->done > $items) $dataobject->done = $items;
        
        // fix global course times
        $dataobject->activityelapsed = $dataobject->elapsed;
        $dataobject->elapsed += @$aggregate['course'][0]->elapsed;
		$dataobject->course->elapsed = 0 + @$aggregate['course'][0]->elapsed;
		$dataobject->course->hits = 0 + @$aggregate['course'][0]->hits;
		if (array_key_exists('upload', $aggregate)){
	        $dataobject->elapsed += @$aggregate['upload'][0]->elapsed;
			$dataobject->upload->elapsed = 0 + @$aggregate['upload'][0]->elapsed;
			$dataobject->upload->hits = 0 + @$aggregate['upload'][0]->hits;
		}

        training_reports_print_header_html($userid, $course->id, $dataobject);
                
        training_reports_print_session_list($str, @$aggregate['sessions']);

        echo $str;

        $options['id'] = $course->id;
        $options['userid'] = $userid;
        $options['from'] = $from; // alternate way
        $options['output'] = 'xls'; // ask for XLS
        $options['asxls'] = 'xls'; // force XLS for index.php
        echo '<br/><center>';
        print_single_button($CFG->wwwroot.'/course/report/trainingsessions/index.php', $options, get_string('generateXLS', 'report_trainingsessions'), 'get');
        echo '</center>';
        echo '<br/>';

    } else {
        // $CFG->trace = 'x_temp/xlsreport.log';
        // debug_open_trace();
    	require_once($CFG->libdir.'/excellib.class.php');
        
        $filename = 'training_sessions_report_'.date('d-M-Y', time()).'.xls';
        $workbook = new MoodleExcelWorkbook("-");
        // Sending HTTP headers
        ob_end_clean();
        
        $workbook->send($filename);
        
        // preparing some formats
        $xls_formats = training_reports_xls_formats($workbook);
        $startrow = 15;
        $worksheet = training_reports_init_worksheet($userid, $startrow, $xls_formats, $workbook);
        $overall = training_reports_print_xls($worksheet, $coursestructure, $aggregate, $done, $startrow, $xls_formats);
        $data->items = $items;
        $data->done = $done;
        $data->from = $from;
        $data->elapsed = $overall->elapsed;
        $data->events = $overall->events;
        training_reports_print_header_xls($worksheet, $userid, $course->id, $data, $xls_formats);

        $worksheet = training_reports_init_worksheet($userid, $startrow, $xls_formats, $workbook, 'sessions');
        training_reports_print_sessions_xls($worksheet, 15, @$aggregate['sessions'], $xls_formats);
        training_reports_print_header_xls($worksheet, $userid, $course->id, $data, $xls_formats);

        $workbook->close();

        // debug_close_trace();

    }

?>