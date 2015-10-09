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
 * This script handles the report generation in batch task for a single group. 
 * It may produce a group csv report.
 * groupid must be provided. 
 * This script should be sheduled in a redirect bouncing process for maintaining
 * memory level available for huge batches. 
 *
 * The global course final grade can be selected along with specified modules to get score from.
 *
 * @package    report_trainingsessions
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @version    moodle 2.x
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 */

require('../../config.php');
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');

$maxbatchduration = 4 * HOURSECS;

$id = required_param('id', PARAM_INT) ; // the course id
$from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying from when for XML generation

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);

// Security
trainingsessions_back_office_access($course);

// TODO : secure groupid access depending on proper capabilities

// calculate start time. Defaults ranges to all course range.

if ($from == -1) { // maybe we get it from parameters
    $from = $course->startdate;
}

if ($to == -1) { // maybe we get it from parameters
    $to = time();
}

// compute target group
$groups = groups_get_all_groups($id);

$timesession = time();
$sessionday = date('Ymd', $timesession);

$testmax = 5;
$i = 0;

foreach ($groups as $group) {

    $filepath = "/$sessionday/";
    $filename = "trainingsessions_group_{$group->name}_report_".date('d-M-Y', time()).".xls";

    // for unit test only
    // if ($i > $testmax) continue;
    $i++;

    $targetusers = groups_get_members($group->id);

    // Filter out non compiling users.
    $compiledusers = array();
    foreach ($targetusers as $u) {
        if (has_capability('report/trainingsessions:iscompiled', $context, $u->id, false)) {
            $compiledusers[$u->id] = $u;
        }
    }

    if (!empty($compiledusers)) {

        $current = time();
        if ($current > $timesession + $maxbatchduration){
            die("Could not finish batch. Too long");
        }

        mtrace('compile_users for group: '.$group->name."<br/>\n");

        $uri = new moodle_url('/report/trainingsessions/grouprawreport_batch_task.php');

        $rqfields = array();
        $rqfields[] = 'id='.$id;
        $rqfields[] = 'from='.$from;
        $rqfields[] = 'to='.$to;
        $rqfields[] = 'groupid='.$group->id;
        $rqfields[] = 'timesession='.$timesession;

        $rq = implode('&', $rqfields);

        $ch = curl_init($uri.'?'.$rq);
        mtrace("Firing url : {$uri}?{$rq}<br/>\n");

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
