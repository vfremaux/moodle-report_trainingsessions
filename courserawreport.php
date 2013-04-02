<?php

if (!defined('MOODLE_INTERNAL')) die ('You cannot access directly to this script');

/**
* direct log construction implementation
*
*/

    include_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
    include_once $CFG->dirroot.'/report/trainingsessions/locallib.php';
	include_once 'selector_form.php';

	$offset = optional_param('offset', 0, PARAM_INT);    
    $page = 20;
    
    ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities

    $id = required_param('id', PARAM_INT) ; // the course id

// calculate start time

    $selform = new SelectorForm($id, 'courseraw');
    if ($data = $selform->get_data()){
	} else {
		$data->from = optional_param('from', -1, PARAM_NUMBER);
		$data->to = optional_param('to', time(), PARAM_INT);
		$data->groupid = optional_param('groupid', $USER->id, PARAM_INT);
		$data->fromstart = optional_param('fromstart', 0, PARAM_BOOL);
		$data->output = optional_param('output', 'html', PARAM_ALPHA);
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
        $targetusers = groups_get_members($data->groupid);
    } else {        
        $targetusers = get_enrolled_users($context);
        
        if (count($targetusers) > 100 || !has_capability('moodle/site:accessallgroups', $context)){
        	
        	// in that case we need force groupid to some value
        	
        	if (count($targetusers) > 100) $OUTPUT->notification(get_string('errorcoursetoolarge', 'report_trainingsessions'));
        	if ($allgroups = groups_get_all_groups($COURSE->id, $USER->id, 0, 'd.id,d.name')){
        		$allgroupids = array_keys($allgroups);
	        	$data->groupid = $allgroupids[0];
	        } else {
				// DO NOT COMPILE 
				echo $OUTPUT->notification('Course is too large and no groups in. Cannot compile.');
				echo $OUTPUT->footer($course);
				die;
			}
        	$targetusers = groups_get_members($data->groupid);
        }
    }
// filter out non compiling users

	$compiledusers = array();
	foreach($targetusers as $u){
    	if (has_capability('report/trainingsessions:iscompiled', $context, $u->id, false)){
    		$compiledusers[$u->id] = $u;
    	}
	}

// print result

    if (!empty($compiledusers)){
    	
		echo 'compiling for '.count($compiledusers).' users<br/>';

        $logs = use_stats_extract_logs($data->from, $data->to, array_keys($compiledusers), $COURSE->id);
        $aggregate = use_stats_aggregate_logs_per_user($logs, 'module');
        
        $weeklogs = use_stats_extract_logs($data->to - DAYSECS * 7, time(), array_keys($compiledusers), $COURSE->id);
        $weekaggregate = use_stats_aggregate_logs_per_user($weeklogs, 'module');        	

    	$timestamp = time();
        $rawstr = '';
		$resultset[] = get_string('group'); // groupname
		$resultset[] = get_string('idnumber'); // userid
		$resultset[] = get_string('lastname'); // user name 
		$resultset[] = get_string('firstname'); // user name 
		$resultset[] = get_string('firstenrolldate', 'report_trainingsessions'); // enrol start date
		$resultset[] = get_string('firstaccess'); // fist trace
		$resultset[] = get_string('lastaccess'); // last trace
		$resultset[] = get_string('startdate', 'report_trainingsessions'); // compile start date
		$resultset[] = get_string('todate', 'report_trainingsessions'); // compile end date
		$resultset[] = get_string('weekstartdate', 'report_trainingsessions'); // last week start date 
	    $resultset[] = get_string('timeelapsed', 'report_trainingsessions');
	    $resultset[] = get_string('timeelapsedcurweek', 'report_trainingsessions');

		$rawstr = mb_convert_encoding(implode(';', $resultset)."\n", 'ISO-8859-1', 'UTF-8');
		
        foreach($compiledusers as $userid => $auser){
    
            $logusers = $auser->id;
		    echo "Compiling for ".fullname($auser).'<br/>';
	        $globalresults->elapsed = 0;
	        if (isset($aggregate[$userid])){
		        foreach($aggregate[$userid] as $classname => $classarray){
		        	foreach($classarray as $modid => $modulestat){
			    		// echo "$classname elapsed : $modulestat->elapsed <br/>";
			    		// echo "$classname events : $modulestat->events <br/>";
		        		$globalresults->elapsed += $modulestat->elapsed;
		        	}
		        }
		    }

	        $globalresults->weekelapsed = 0;
	        if (isset($weekaggregate[$userid])){
		        foreach($weekaggregate[$userid] as $classarray){
		        	foreach($classarray as $modid => $modulestat){
		        		$globalresults->weekelapsed += $modulestat->elapsed;
		        	}
		        }
		    }
		    
            trainingsessions_print_globalheader_raw($auser->id, $course->id, $globalresults, $rawstr, $data->from, $data->to);
        }

		$fs = get_file_storage();
		 
		// Prepare file record object
		$fileinfo = array(
		    'contextid' => $context->id, // ID of context (course context)
		    'component' => 'report_trainingsessions',     // usually = table name
		    'filearea' => 'rawreports',     // usually = table name
		    'itemid' => $COURSE->id,               // usually = ID of row in table
		    'filepath' => '/',           // any path beginning and ending in /
		    'filename' => "raw_{$timestamp}.csv"); // any filename
		 
		// Create file containing text 'hello world'
		$fs->delete_area_files($context->id, 'report_trainingsessions', 'rawreports', $COURSE->id);
		$fs->create_file_from_string($fileinfo, $rawstr);

		$strupload = get_string('uploadresult', 'report_trainingsessions');
		echo "<p><br/><a href=\"{$CFG->wwwroot}/pluginfile.php/{$context->id}/report_trainingsessions/rawreports/raw_{$timestamp}.csv\">$strupload</a></p>";
    } else {
    	print_string('nothing', 'report_trainingsessions');
    }
    
?>