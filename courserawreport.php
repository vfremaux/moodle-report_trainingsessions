<?php

if (!defined('MOODLE_INTERNAL')) die ('You cannot access directly to this script');

/**
* direct log construction implementation
*
*/

    include_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
    include_once $CFG->dirroot.'/course/report/trainingsessions/locallib.php';

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
    $groupid = optional_param('groupid', false, PARAM_INT) ; // admits special values : -1 current group, -2 course users
    $output = optional_param('output', 'html', PARAM_ALPHA) ; // 'html' or 'xls'

	$offset = optional_param('offset', 0, PARAM_INT);    
    $page = 20;
    
    ini_set('memory_limit', '2048M');

// TODO : secure groupid access depending on proper capabilities

// calculate start time

    if ($from == -1){ // maybe we get it from parameters
        if ($startday == -1 || $fromstart){
            $from = $course->startdate;
        } else {
            if ($startmonth != -1 && $startyear != -1)
                $from = mktime(0, 0, 8, $startmonth, $startday, $startyear);
            else 
                print_error('Bad start date');
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
    
// Pre print the group selector
    // time and group period form
    include_once "courseraw_selector_form.html";

// compute target group

    if ($groupid){
        $targetusers = groups_get_members($groupid);
    } else {        
        // $allusers = get_users_by_capability($context, 'moodle/course:view', 'u.id, firstname', 'lastname');
        $targetusers = get_users_by_capability($context, 'moodle/course:view', 'u.id, firstname, lastname, email, institution', 'lastname');
        
        if (count($targetusers) > 100){
        	notify('Course is too large. Choosing a group');
        	$groupid = $defaultgroup; // defined in courseraw_selector_form.html
			// DO NOT COMPILE 
			if ($groupid == 0){
				notify('Course is too large and no groups in. Cannot compile.');
				print_footer($course);
			}
        	$targetusers = groups_get_members($groupid);
        }
    }

	// fitlers teachers out
    foreach($targetusers as $uid => $user){
    	if (has_capability('moodle/legacy:teacher', $context, $user->id) || has_capability('moodle/legacy:editingteacher', $context, $user->id)){
    		unset($targetusers[$uid]);
    	}
    }

// print result

    if (!empty($targetusers)){

		echo 'compiling for '.count($targetusers).' users<br/>';

		perf_punchin('count_time');
        $logs = use_stats_extract_logs($from, $to, array_keys($targetusers), $COURSE->id);
        $aggregate = use_stats_aggregate_logs_per_user($logs, 'module');
        
        $weeklogs = use_stats_extract_logs($to - DAYSECS * 7, time(), array_keys($targetusers), $COURSE->id);
        $weekaggregate = use_stats_aggregate_logs_per_user($weeklogs, 'module');        	
		perf_punchout('count_time', 'compile_users');

    	$timestamp = time();
        $rawfile = fopen($CFG->dataroot.'/'.$COURSE->id."/raw_{$timestamp}.csv", 'wb');
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

		fputs($rawfile, mb_convert_encoding(implode(';', $resultset)."\n", 'ISO-8859-1', 'UTF-8'));
		
        foreach($targetusers as $userid => $auser){
    
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
		    
            trainingsessions_print_globalheader_raw($auser->id, $course->id, $globalresults, $rawfile, $from, $to);
        }
		perf_punchout('compile_users');
        fclose($rawfile);

		$strupload = get_string('uploadresult', 'report_trainingsessions');
		echo "<a href=\"{$CFG->wwwroot}/file.php?file=/{$COURSE->id}/raw_{$timestamp}.csv\">$strupload</a>";
    } else {
    	print_string('nothing', 'report_trainingsessions');
    }
    
?>