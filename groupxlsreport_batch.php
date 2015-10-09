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
require('../../config.php');
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');

$maxbatchduration = 4 * HOURSECS;

$id = required_param('id', PARAM_INT) ; // the course id
$from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$groupid = optional_param('groupid', '', PARAM_INT) ; // compiling for given group or all groups
$outputdir = optional_param('outputdir', 'autoreports', PARAM_TEXT) ; // where to put the file
$reportlayout = optional_param('reportlayout', 'onefulluserpersheet', PARAM_TEXT) ; // where to put the file

if ($reportlayout == 'onefulluserpersheet') {
    $reporttype = 'report';
    $uri = new moodle_url('/report/trainingsessions/groupxlsreportperuser_batch_task.php');
} else {
    $reporttype = 'summary';
    $uri = new moodle_url('/report/trainingsessions/groupxlsreportsummary_batch_task.php');
}

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('invalidcourse');
}
$context = context_course::instance($course->id);

// Security
trainingsessions_back_office_access($course);

// calculate start time. Defaults ranges to all course range.

if ($from == -1) { // maybe we get it from parameters
    $from = $course->startdate;
}

if ($to == -1) { // maybe we get it from parameters
    $to = time();
}

// compute target group

if (!$groups = groups_get_all_groups($id)) {
    $group = new StdClass;
    $group->id = 0;
    $group->name = get_string('course');
    $groups[] = $group;
}

$timesession = time();
$sessionday = date('Ymd', $timesession);

$testmax = 5;
$i = 0;

if (!is_dir($CFG->dataroot.'/'.$course->id."/{$outputdir}")) {
    mkdir($CFG->dataroot.'/'.$course->id."/{$outputdir}", 0777, true);
}

if (!is_dir($CFG->dataroot.'/'.$course->id."/{$outputdir}/$sessionday")) {
    mkdir($CFG->dataroot.'/'.$course->id."/{$outputdir}/$sessionday");
}

$fs = get_file_storage();

foreach ($groups as $group) {

    $filerec = new StdClass;
    $filerec->contextid = $context->id;
    $filerec->component = 'report_trainingsessions';
    $filerec->filearea = 'reports';
    $filerec->itemid = $course->id;
    $filerec->filepath = "/{$outputdir}/{$sessionday}/";
    $filerec->filename = "trainingsessions_group_{$group->name}_{$reporttype}_".date('d-M-Y', time()).".xls";

    // for unit test only
    // if ($i > $testmax) continue;
    $i++;

    $targetusers = groups_get_members($group->id);
    // filters teachers out
    trainingsessions_filter_unwanted_users($targetusers);

    if (!empty($targetusers)) {

        $current = time();
        if ($current > $timesession + $maxbatchduration) {
            die("Could not finish batch. Too long");
        }

        mtrace('Compile_users for group: '.$group->name."<br/>\n");

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
            debugging("Request for <a href=\"{$uri}?{$rq}\">Group {$group->id}</a> failed with curl error $curlerrno");
        }

        // check HTTP error code
        $info =  curl_getinfo($ch);
        if (!empty($info['http_code']) && ($info['http_code'] != 200) && ($info['http_code'] != 303)) {
            debugging("Request for <a href=\"{$uri}?{$rq}\">Group {$group->id}</a> failed with HTTP code ".$info['http_code']);
        } else {
            // feed xls result in file storage.
            $oldfile = $fs->get_file($filerec->contextid, $filerec->component, $filerec->filearea, $filerec->itemid, $filerec->filepath, $filerec->filename);
            if ($oldfile) {
                // clean old file before.
                $oldfile->delete();
            }
            $newfile = $fs->create_file_from_string($filerec, $raw);

            $createdurl = moodle_url::make_pluginfile_url($filerec->contextid, $filerec->component, $filerec->filearea, $filerec->itemid, $filerec->filepath, $filerec->filename);
            mtrace('Result : <a href="'.$createdurl.'" >'.$filerec->filename."</a><br/>\n");
        }

        curl_close($ch);
    } else {
        mtrace('no more compilable users in this group: '.$group->name);
    }

}

