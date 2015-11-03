<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Course trainingsessions report
 *
 * @package    report_trainingsessions
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
* This special report allows wrapping to course report crons
* function that otherwise would not be considered by cron task.
*
* for repetitive tasks, we will not delete the task record and push the batchdate ahead to the next date.
*/
function report_trainingsessions_cron() {
    global $CFG;

    mtrace("Starting trainingsession cron.");

    if (!$tasks = unserialize(@$CFG->trainingreporttasks)) {
        mtrace('empty task stack...');
        return;
    }

    foreach ($tasks as $taskid => $task) {
        mtrace("\tStarting registering $task->taskname...");
        if (time() < $task->batchdate && !optional_param('force', false, PARAM_BOOL)) {
            mtrace("\t\tnot yet.");
            debug_trace(time().": task $task->id not in time ($task->batchdate) to run");
            continue;
        }

        $taskarr = (array)$task;
        $rqarr = array();
        $taskarr['id'] = $taskarr['courseid']; // add the course reference of the batch

        // Setup the back office security. This ticket is used all along the batch chain
        // to allow cron or bounce processes to run.
        if (file_exists($CFG->dirroot.'/auth/ticket/lib.php')) {
            $user = new StdClass();
            $user->username = 'admin';
            include_once($CFG->dirroot.'/auth/ticket/lib.php');
            $taskarr['ticket'] = ticket_generate($user, 'batch web distribution', '');
        }

        foreach ($taskarr as $key => $value) {
            $rqarr[] = $key.'='.urlencode($value);
        }
        $rq = implode('&', $rqarr);

        /// launch tasks by firing CURL shooting
        $uri = new moodle_url('/report/trainingsessions/groupxlsreport_batch.php');

        $ch[$taskid] = curl_init($uri.'?'.$rq);
        mtrace("CURL Registered : {$uri}?{$rq}\n");
        debug_trace("Registering curl : {$uri}?{$rq}\n");

        curl_setopt($ch[$taskid], CURLOPT_TIMEOUT, 60);
        curl_setopt($ch[$taskid], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch[$taskid], CURLOPT_POST, false);
        curl_setopt($ch[$taskid], CURLOPT_USERAGENT, 'Moodle Report Batch');
        curl_setopt($ch[$taskid], CURLOPT_POSTFIELDS, $rq);
        curl_setopt($ch[$taskid], CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
        curl_setopt($ch[$taskid], CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch[$taskid], CURLOPT_SSL_VERIFYHOST, 0);
    }

    if (!empty($ch)) {

        $mh = curl_multi_init();

        foreach($ch as $task) {
            //add the handles
            curl_multi_add_handle($mh, $task);
        }

        $active = null;

        //execute the handles
        mtrace('Starting processing... ');
        do {
            mtrace('Exec... ');
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            mtrace('Check active... ');
            if (curl_multi_select($mh) == -1) {
                usleep(1);
            }

            do {
                mtrace('Continue queue... ');
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
        mtrace('Processing done...');

        // process hanlde results once done
        while ($result = curl_multi_info_read($mh)) {
            $httpurl = curl_getinfo($result['handle'], CURLINFO_EFFECTIVE_URL);
            $httpresult = curl_getinfo($result['handle'], CURLINFO_HTTP_CODE);
            if ($result['result'] != CURLE_OK) {
                mtrace('Error on '.$httpurl);
                mtrace('   Curl Error: '.curl_error($result['handle']));
            } elseif ($httpresult != 200) {
                mtrace('Remote Error on '.$httpurl);
                mtrace('   HTTP Error: '.$httpresult);
            }
        }

        //close the handles
        foreach ($ch as $taskid => $task) {

            if ($tasks[$taskid]->replay) {
                // replaydelay in seconds
                $tasks[$taskid]->batchdate = $tasks[$taskid]->batchdate + $tasks[$taskid]->replaydelay * 60;
                mtrace('Bouncing task '.$taskid.' to '.userdate($tasks[$taskid]->batchdate));
            } else {
                unset($tasks[$taskid]);
                mtrace('Removing task '.$taskid);
            }

            debug_trace('closing task handle '.$taskid);
            curl_multi_remove_handle($mh, $task);
        }

        curl_multi_close($mh);

        // update in config
        set_config('trainingreporttasks', serialize($tasks));

        mtrace("\tdone.");
    } else {
        mtrace("\tno tasks to process.");
    }
}
