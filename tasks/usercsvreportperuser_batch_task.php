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
 * It will produce a group Excel worksheet report that is pushed immediately to output
 * for downloading by a batch agent. No file is stored into the system.
 * groupid must be provided.
 * This script should be sheduled in a CURL call stack or a multi_CURL parallel call.
 *
 * @package    report_trainingsessions
 * @category   report
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');
ob_start();
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/csvrenderers.php');
require_once($CFG->libdir.'/excellib.class.php');

$id = required_param('id', PARAM_INT); // The course id.
$userid = required_param('userid', PARAM_INT); // User id.
$rt = \report\trainingsessions\trainingsessions::instance();
$renderer = new \report\trainingsessions\CsvRenderer($rt);

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    // Do NOT print_error here as we are a document writer.
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);

$input = $rt->batch_input($course);

// Security.
$rt->back_office_access($course, $userid);

$PAGE->set_context($context);

$coursestructure = $rt->get_course_structure($course->id, $items);

// TODO : secure groupid access depending on proper capabilities.

// Generate XLS.

$filename = "ts_user_{$userid}_report_".$input->filenametimesession.".csv";

$auser = $DB->get_record('user', array('id' => $userid));

if ($auser) {
    // Sending HTTP headers.

    $logusers = $auser->id;
    $logs = use_stats_extract_logs($input->from, $input->to, $auser->id, $course->id);
    $aggregate = use_stats_aggregate_logs($logs, $input->from, $input->to);

    $csvbuffer = '';
    $renderer->print_userinfo($csvbuffer, $auser);
    $renderer->print_header($csvbuffer);
    $renderer->print_course_structure($csvbuffer, $coursestructure, $aggregate);

    // Sending HTTP headers.
    ob_end_clean();

    // Output CSV-specific headers.
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Cache-Control: no-cache, must-revalidate");
    header("Content-Type: application/csv");
    header("Content-Disposition: inline; filename=\"$filename\";");
    header("Content-Transfer-Encoding: text");
    header("Content-Length: ".strlen($csvbuffer));
    echo $csvbuffer;
}
