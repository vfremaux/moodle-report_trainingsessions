<?php

/**
* This special report allows wrapping to course report crons
* function that otherwise would not be considered by cron task.
*
* for repetitive tasks, we will not delete the task record and push the batchdate ahead to the next date.
*/
function report_trainingsessions_cron(){
	global $CFG;
	
	mtrace("Starting trainingsession cron.");
	
	if (!$tasks = unserialize(@$CFG->trainingreporttasks)){
		mtrace('empty task stack...');
		return;
	}
	
	foreach($tasks as $taskid => $task){
		mtrace("\tStarting generating $task->taskname...");
		if (time() < $task->batchdate){
			mtrace("\t\tnot yet.");
	    	debug_trace(time().": task $task->id not in time ($task->batchdate) to run");
			continue;
		}
		
		$taskarr = (array)$task;
		$rqarr = array();
		$taskarr['id'] = $taskarr['courseid']; // add the course reference of the batch
		foreach($taskarr as $key => $value){
			$rqarr[] = $key.'='.urlencode($value);
		}
		$rq = implode('&', $rqarr);
		
		/// launch tasks by firing CURL shooting
		$uri = $CFG->wwwroot.'/course/report/trainingsessions/groupxlsreport_batch.php';

	    $ch = curl_init($uri.'?'.$rq);
	    debug_trace("Firing curl : {$uri}?{$rq}\n");
		
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
	        debug_trace("Request for $uri failed with curl error $curlerrno");
	    } 
	
	    // check HTTP error code
	    $info =  curl_getinfo($ch);
	    if (!empty($info['http_code']) and ($info['http_code'] != 200)) {
	        debug_trace("Request for $uri failed with HTTP code ".$info['http_code']);
	    } else {
	        debug_trace('Success');
	    }
	
	    curl_close($ch);			
		
		if ($task->replay){
			// replaydelay in seconds
			$tasks[$taskid]->batchdate = $tasks[$taskid]->batchdate + $task->replaydelay * 60;
	        debug_trace('Bouncing task '.$task->id.' to '.userdate($tasks[$taskid]->batchdate));
		} else {
			unset($tasks[$taskid]);
	        debug_trace('Removing task '.$task->id);
		}
		// update in config
		set_config('trainingreporttasks', serialize($tasks));
	}	
	
	mtrace("\tdone.");
}
