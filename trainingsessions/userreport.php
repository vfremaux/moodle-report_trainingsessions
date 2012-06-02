<?php

/**
* direct log construction implementation
*
*/
	ob_start();

    include_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
    include_once $CFG->dirroot.'/report/trainingsessions/locallib.php';

// require login and make page start

	/*
    $startday = optional_param('startday', -1, PARAM_INT) ; // from (-1 is from course start)
    $startmonth = optional_param('startmonth', -1, PARAM_INT) ; // from (-1 is from course start)
    $startyear = optional_param('startyear', -1, PARAM_INT) ; // from (-1 is from course start)
    */

// selector form

	include_once 'selector_form.php';
    $selform = new SelectorForm($id, 'user');
    if ($data = $selform->get_data()){
	} else {
		$data->from = -1;
		$data->userid = $USER->id;
		$data->fromstart = 0;
		$data->output = 'html';
	}

// calculate start time

    if ($data->from == -1 || @$data->fromstart){ // maybe we get it from parameters
        $data->from = $course->startdate;
    }

	if (!$asxls){
	    $selform->set_data($data);
	    $selform->display();
	}

// get data

    $logusers = $data->userid;
    $logs = use_stats_extract_logs($data->from, time(), $data->userid, $course->id);
    $aggregate = use_stats_aggregate_logs($logs, 'module');
    
// get course structure

    $coursestructure = reports_get_course_structure($course->id, $items);
    
// print result

    if (!$asxls){
        // time period form

        echo "<link rel=\"stylesheet\" href=\"reports.css\" type=\"text/css\" />";

        
        $str = '';
        $dataobject = training_reports_print_html($str, $coursestructure, $aggregate, $done);
        $dataobject->items = $items;
        $dataobject->done = $done;

        /*
        if (!empty($aggregate)){
            foreach(array_keys($aggregate) as $module){
                $dataobject->done += count($aggregate[$module]);
            }
        }
        */

        if ($dataobject->done > $items) $dataobject->done = $items;

        training_reports_print_header_html($data->userid, $course->id, $dataobject);
        
        echo $str;

        $url = $CFG->wwwroot.'/report/trainingsessions/index.php?id='.$course->id.'&amp;view=user&amp;userid='.$data->userid.'&amp;from='.$data->from.'&amp;output=xls&amp;asxls=1';
        echo '<br/><center>';
        echo $OUTPUT->single_button($url, get_string('generateXLS', 'report_trainingsessions'), 'get');
        echo '</center>';

    } else {
        // $CFG->trace = 'x_temp/xlsreport.log';
        // debug_open_trace();
        require_once $CFG->libdir.'/excellib.class.php';
        
        $filename = 'training_sessions_report_'.date('d-M-Y', time()).'.xls';
        $workbook = new MoodleExcelWorkbook("-");
        // Sending HTTP headers
        ob_end_clean();
        
        $workbook->send($filename);
        
        // preparing some formats
        $xls_formats = training_reports_xls_formats($workbook);
        $startrow = 15;
        $worksheet = training_reports_init_worksheet($data->userid, $startrow, $xls_formats, $workbook);
        $overall = training_reports_print_xls($worksheet, $coursestructure, $aggregate, $done, $startrow, $xls_formats);
        $datarec->items = $items;
        $datarec->done = $done;
        $datarec->from = $data->from;
        $datarec->elapsed = $overall->elapsed;
        $datarec->events = $overall->events;
        training_reports_print_header_xls($worksheet, $data->userid, $course->id, $datarec, $xls_formats);
        $workbook->close();

        // debug_close_trace();

    }

?>