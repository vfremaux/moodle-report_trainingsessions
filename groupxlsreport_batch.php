<?php

/**
* This script handles the report generation in batch task for a single group. 
* It may produce a group csv report.
* groupid must be provided. 
* This script should be sheduled in a redirect bouncing process for maintaining
* memory level available for huge batches. 
*/

	include '../../../config.php';
    include_once $CFG->dirroot.'/blocks/use_stats/locallib.php';
    include_once $CFG->dirroot.'/course/report/trainingsessions/locallib.php';
    
	$maxbatchduration = 4 * HOURSECS;

    $id = required_param('id', PARAM_INT) ; // the course id
    $from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
    $to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
    $groupid = optional_param('groupid', '', PARAM_INT) ; // compiling for given group or all groups
    $outputdir = optional_param('outputdir', 'autoreports', PARAM_TEXT) ; // where to put the file
    $reportlayout = optional_param('reportlayout', 'onefulluserpersheet', PARAM_TEXT) ; // where to put the file

	if ($reportlayout == 'onefulluserpersheet'){
		$reporttype = 'report';
		$uri = $CFG->wwwroot.'/course/report/trainingsessions/groupxlsreportperuser_batch_task.php';
	} else {
		$reporttype = 'summary';
		$uri = $CFG->wwwroot.'/course/report/trainingsessions/groupxlsreportsummary_batch_task.php';
	}
    
    ini_set('memory_limit', '512M');
    
    if (!$course = get_record('course', 'id', $id)){
    	die ('Invalid course ID');
    }
    $context = context_course::instance($course->id);

// TODO : secure groupid access depending on proper capabilities

// calculate start time. Defaults ranges to all course range.

    if ($from == -1){ // maybe we get it from parameters
    	$from = $course->startdate;
    }

    if ($to == -1){ // maybe we get it from parameters
    	$to = time();
    }
    
// compute target group

	if (!$groups = groups_get_all_groups($id)){
		$group = new StdClass;
		$group->id = 0;
		$group->name = get_string('course');
		$groups[] = $group;
	}
	
	$timesession = time();
    $sessionday = date('Ymd', $timesession);
	
	$testmax = 5;
	$i = 0;

	if (!is_dir($CFG->dataroot.'/'.$course->id."/{$outputdir}")){
		mkdir($CFG->dataroot.'/'.$course->id."/{$outputdir}", 0777, true);
	}

	if (!is_dir($CFG->dataroot.'/'.$course->id."/{$outputdir}/$sessionday")){
		mkdir($CFG->dataroot.'/'.$course->id."/{$outputdir}/$sessionday");
	}
	
	foreach($groups as $group){

        $filename = $CFG->dataroot.'/'.$course->id."/{$outputdir}/{$sessionday}/trainingsessions_group_{$group->name}_{$reporttype}_".date('d-M-Y', time()).".xls";
		
		// for unit test only
		// if ($i > $testmax) continue;
		$i++;

		if ($group->id){
		    $targetusers = groups_get_members($group->id);
		} else {
        	$targetusers = get_users_by_capability($context, 'moodle/course:view', 'u.id, firstname, lastname, email, institution, idnumber', 'lastname');
		}

		// filters teachers out
	    foreach($targetusers as $uid => $user){
	    	if (has_capability('moodle/legacy:teacher', $context, $user->id) || has_capability('moodle/legacy:editingteacher', $context, $user->id)){
	    		unset($targetusers[$uid]);
	    	}
	    }

	    if (!empty($targetusers)){
	    	
	    	$current = time();
	    	if ($current > $timesession + $maxbatchduration){
	    		die("Could not finish batch. Too long");
	    	}
	    	
			mtrace('compile_users for group: '.$group->name."<br/>\n");
			
			$rqfields = array();   
		    $rqfields[] = 'id='.$id;
		    $rqfields[] = 'from='.$from;
		    $rqfields[] = 'to='.$to;
		    $rqfields[] = 'groupid='.$group->id;
			$rqfields[] = 'timesession='.$timesession;
		    
		    $rq = implode('&', $rqfields);

		    $ch = curl_init($uri.'?'.$rq);
		    debug_trace("Firing url : {$uri}?{$rq}<br/>\n");
		
		    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    curl_setopt($ch, CURLOPT_POST, false);
		    curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle Report Batch');
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $rq);
		    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		
		    $raw = curl_exec($ch);
		
		    // check for curl errors
		    $curlerrno = curl_errno($ch);
		    if ($curlerrno != 0) {
		        debugging("Request for $uri failed with curl error $curlerrno");
		    } 
		
		    // check HTTP error code
		    $info =  curl_getinfo($ch);
		    if (!empty($info['http_code']) and ($info['http_code'] != 200)) {
		        debugging("Request for $uri failed with HTTP code ".$info['http_code']);
		    } else {
		    	// feed xls result.
		    	$XLS = fopen($filename, 'wb');
		    	fputs($XLS, $raw);
		    	fclose($XLS);
		    }
		
		    curl_close($ch);			
			
		} else {
			mtrace('no more compilable users in this group: '.$group->name);
		}

    }
    
?>