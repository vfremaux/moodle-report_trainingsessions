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
 * This script handles the session report generation in batch task for a single user.
 * It will produce a single PDF report that is pushed immediately to output
 * for downloading by a batch agent. No file is stored into the system.
 * userid must be provided.
 * This script should be sheduled in a CURL call stack or a multi_CURL parallel call.
 */

require('../../../config.php');

require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/csvrenderers.php');

$id = required_param('id', PARAM_INT); // The course id.
$userid = required_param('userid', PARAM_INT); // User id.
$rt = \report\trainingsessions\trainingsessions::instance();
$renderer = new \report\trainingsessions\CsvRenderer($rt);

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);

// Security
$rt->back_office_access($course, $userid);

$PAGE->set_context($context);

$input = $rt->batch_input($course);

$user = $DB->get_record('user', array('id' => $userid));

// Print result.
if (!empty($user)) {

    $csvbuffer = '';
    $renderer->print_session_header($csvbuffer);
    $y = $renderer->print_usersessions($csvbuffer, $userid, $course, $input->from, $input->to, $id);

}

$filename = "ts_usersessions_{$course->id}_report_".$input->filenametimesession.".csv";

// Sending HTTP headers.
ob_end_clean();
header("Pragma: no-cache");
header("Expires: 0");
header("Cache-Control: no-cache, must-revalidate");
header("Content-Type: application/csv");
header("Content-Disposition: inline; filename=\"$filename\";");
header("Content-Transfer-Encoding: text");
header("Content-Length: ".strlen($csvbuffer));
echo $csvbuffer;
