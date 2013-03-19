<?php

if (!defined('MOODLE_INTERNAL')) die ('You cannot use this script directly');

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
		$data = new StdClass;
		$data->from = optional_param('from', -1, PARAM_NUMBER);
		$data->userid = optional_param('userid', $USER->id, PARAM_INT);
		$data->fromstart = optional_param('fromstart', 0, PARAM_BOOL);
		$data->output = optional_param('output', 'html', PARAM_ALPHA);
	}

// calculate start time

    if ($data->from == -1 || @$data->fromstart){ // maybe we get it from parameters
        $data->from = $course->startdate;
    }

	if ($data->output == 'html'){
	    $selform->set_data($data);
	    $selform->display();
	}

// get data

    $logusers = $data->userid;
    $logs = use_stats_extract_logs($data->from, time(), $data->userid, $course->id);
    $aggregate = use_stats_aggregate_logs($logs, 'module');
    
    if (empty($aggregate['sessions'])) $aggregate['sessions'] = array();
    
// get course structure

    $coursestructure = reports_get_course_structure($course->id, $items);
    
// print result

    if ($data->output == 'html'){
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

        // fix global course times
        $dataobject->course = new StdClass;
        $dataobject->activityelapsed = @$aggregate['activities']->elapsed;
        $dataobject->elapsed += @$aggregate['course'][0]->elapsed;
		$dataobject->course->elapsed = 0 + @$aggregate['course'][0]->elapsed;
		$dataobject->course->hits = 0 + @$aggregate['course'][0]->events;
		$dataobject->sessions = (!empty($aggregate['sessions'])) ? count(@$aggregate['sessions']) - 1 : 0 ;
		if (array_key_exists('upload', $aggregate)){
	        $dataobject->elapsed += @$aggregate['upload'][0]->elapsed;
			$dataobject->upload->elapsed = 0 + @$aggregate['upload'][0]->elapsed;
			$dataobject->upload->hits = 0 + @$aggregate['upload'][0]->events;
		}


        training_reports_print_header_html($data->userid, $course->id, $dataobject);
                
        training_reports_print_session_list($str, @$aggregate['sessions']);
        
        echo $str;

        $url = $CFG->wwwroot.'/report/trainingsessions/index.php?id='.$course->id.'&amp;view=user&amp;userid='.$data->userid.'&amp;from='.$data->from.'&amp;output=xls';
        echo '<br/><center>';
        echo $OUTPUT->single_button($url, get_string('generateXLS', 'report_trainingsessions'), 'get');
        echo '</center>';
        echo '<br/>';

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
        $datarec = new StdClass;
        $datarec->items = $items;
        $datarec->done = $done;
        $datarec->from = $data->from;
        $datarec->elapsed = $overall->elapsed;
        $datarec->events = $overall->events;
        training_reports_print_header_xls($worksheet, $data->userid, $course->id, $datarec, $xls_formats);

        $worksheet = training_reports_init_worksheet($data->userid, $startrow, $xls_formats, $workbook, 'sessions');
        training_reports_print_sessions_xls($worksheet, 15, $aggregate['sessions'], $xls_formats);
        training_reports_print_header_xls($worksheet, $data->userid, $course->id, $data, $xls_formats);

        $workbook->close();

        // debug_close_trace();

    }

?>