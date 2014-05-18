<?php

/**
* direct log construction implementation
*
*/
	ob_start();

    include_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
    include_once $CFG->dirroot.'/report/trainingsessions/locallib.php';
	include_once 'selector_form.php';

    $id = required_param('id', PARAM_INT) ; // the course id

// calculate start time
    $selform = new SelectorForm($id, 'allcourses');

    if ($data = $selform->get_data()){
	} else {
		$data = new StdClass;
		$data->from = optional_param('from', -1, PARAM_NUMBER);
		$data->userid = optional_param('userid', $USER->id, PARAM_INT);
		$data->fromstart = optional_param('fromstart', 0, PARAM_BOOL);
		$data->output = optional_param('output', 'html', PARAM_ALPHA);
	}

	$context = context_course::instance($id);
	$canseeothers = has_capability('report/trainingsessions:viewother', $context);

	if (!$canseeothers){
		// restrict to view yourself only
		$userid = $USER->id;
	} else {
		$userid = $data->userid;
	}

// calculate start time

    if ($data->from == -1 || @$data->fromstart){ // maybe we get it from parameters
        $from = $DB->get_field('user', 'firstaccess', array('id' => $userid));
    }

	if ($data->output == 'html'){
		echo $OUTPUT->box_start('block');
	    $selform->set_data($data);
	    $selform->display();
		echo $OUTPUT->box_end();
	}

// get log data

    $logs = use_stats_extract_logs($data->from, time(), $userid, null);
    $aggregate = use_stats_aggregate_logs($logs, 'module');
    
    if (empty($aggregate['sessions'])) $aggregate['sessions'] = array();
    
// print result

    if ($data->output == 'html'){
        // time period form

    	require_once('htmlrenderers.php');

        echo "<link rel=\"stylesheet\" href=\"reports.css\" type=\"text/css\" />";

        echo '<br/>';
        
        $str = '';
        $dataobject = training_reports_print_allcourses_html($str, $aggregate);
        $dataobject->course = new StdClass;
		$dataobject->sessions = count(@$aggregate['sessions']);
		
        // fix global course times
        $dataobject->activityelapsed = @$aggregate['activities']->elapsed;
        // $dataobject->elapsed += @$aggregate['course'][0]->elapsed;
		$dataobject->course->elapsed = 0 + @$aggregate['course'][0]->elapsed;
		$dataobject->course->hits = 0 + @$aggregate['course'][0]->hits;
		if (array_key_exists('upload', $aggregate)){
			$dataobject->elapsed += @$aggregate['upload'][0]->elapsed;
	        $dataobject->upload = new StdClass;
			$dataobject->upload->elapsed = 0 + @$aggregate['upload'][0]->elapsed;
			$dataobject->upload->hits = 0 + @$aggregate['upload'][0]->hits;
		}

        training_reports_print_header_html($userid, $course->id, $dataobject, false, false, false);
                
		echo $OUTPUT->heading(get_string('incourses', 'report_trainingsessions'));    
        echo $str;

        training_reports_print_session_list($str2, @$aggregate['sessions'], 0);
        echo $str2;

        $url = $CFG->wwwroot.'/report/trainingsessions/index.php?id='.$course->id.'&amp;view=allcourses&amp;userid='.$userid.'&amp;from='.$data->from.'&amp;output=xls';
        echo '<br/><center>';
        // echo count($targetusers).' found in this selection';
        echo $OUTPUT->single_button($url, get_string('generateXLS', 'report_trainingsessions'), 'get');
        echo '</center>';
        echo '<br/>';

    } else {

    	require_once('xlsrenderers.php');

        // $CFG->trace = 'x_temp/xlsreport.log';
        // debug_open_trace();
    	require_once($CFG->libdir.'/excellib.class.php');
        
        $filename = 'allcourses_sessions_report_'.date('d-M-Y', time()).'.xls';
        $workbook = new MoodleExcelWorkbook("-");
        // Sending HTTP headers
        ob_end_clean();
        
        $workbook->send($filename);
        
        // preparing some formats
        $xls_formats = training_reports_xls_formats($workbook);
        $startrow = 15;
        $worksheet = training_reports_init_worksheet($userid, $startrow, $xls_formats, $workbook, 'allcourses');
        $overall = training_reports_print_allcourses_xls($worksheet, $aggregate, $startrow, $xls_formats);
        $data->elapsed = $overall->elapsed;
        $data->events = $overall->events;
        training_reports_print_header_xls($worksheet, $userid, $course->id, $data, $xls_formats);

        $worksheet = training_reports_init_worksheet($userid, $startrow, $xls_formats, $workbook, 'sessions');
        training_reports_print_sessions_xls($worksheet, 15, @$aggregate['sessions'], 0, $xls_formats);
        training_reports_print_header_xls($worksheet, $userid, $course->id, $data, $xls_formats);

        $workbook->close();
    }
