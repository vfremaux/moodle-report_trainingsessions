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
 * @package    report_trainingsessions
 * @category   report
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../../config.php');
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/csvrenderers.php');
require_once($CFG->dirroot.'/report/learningtimecheck/lib.php');

$id = required_param('id', PARAM_INT); // The course id.
$groupid = required_param('groupid', PARAM_INT); // Group id.

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    // Do NOT print_error here as we are a document writer.
    die('Invalid course ID');
}
$context = context_course::instance($course->id);

$input = report_trainingsessions_batch_input($course);

// Security.
report_trainingsessions_back_office_access($course);

// Compute target group.

$group = $DB->get_record('groups', array('id' => $groupid));

if ($groupid) {
    $targetusers = groups_get_members($groupid);
    $filename = "trainingsessions_group_{$groupid}_report_".$input->filenametimesession.".csv";
} else {
    $targetusers = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
    $filename = "trainingsessions_course_{$course->id}_report_".$input->filenametimesession.".csv";
}

// Filter out non compiling users.
report_trainingsessions_filter_unwanted_users($targetusers, $course);

// Print result.

$csvbuffer = '';
if (!empty($targetusers)) {
    // generate CSV.

    $cols = report_trainingsessions_get_workingdays_cols();
    report_trainingsessions_print_row($cols, $csvbuffer);

    foreach ($targetusers as $auser) {

        $events = report_learningtimecheck_get_user_workdays($auser->id);

        if ($events) {
            foreach ($events as $e) {
                // Workdays events are given at noon.
                $start = $e->timestart - 12 * HOURSECS + 1;
                $end = $e->timestart + 12 * HOURSECS - 1;

                $logs = use_stats_extract_logs($start, $end, $auser->id);
                $aggregate = use_stats_aggregate_logs($logs, $start, $end);

                $totaltime = 0;
                if (!empty($aggregate['sessions'])) {
                    foreach ($aggregate['sessions'] as $s) {
                        $totaltime += 0 + @$s->elapsed;
                    }
                }

                $traversedcourses = array();
                if (!empty($aggregate['course'])) {
                    foreach (array_keys($aggregate['course']) as $courseid) {
                        $traversedcourses[] = $DB->get_field('course', 'shortname', array('id' => $courseid));
                    }
                }

                $cols = array();
                $cols[0] = $auser->id;
                $cols[1] = $auser->username;
                $cols[2] = fullname($auser);
                $cols[3] = strftime(get_string('strfdate', 'report_trainingsessions'), $e->timestart);
                $cols[4] = date('W', $e->timestart);
                $cols[5] = count($aggregate['sessions']);
                $cols[6] = $totaltime;
                $cols[7] = report_trainingsessions_format_time($totaltime, $mode = 'htmld');
                $cols[8] = strftime(get_string('strftime', 'report_trainingsessions'), @$aggregate['sessions'][0]->sessionstart);
                $cols[9] = implode(", ", $traversedcourses);

                report_trainingsessions_print_row($cols, $csvbuffer);
            }
        } else {
            $csvbuffer .= '# '.fullname($auser)." has no events \n\n";
        }
        $csvbuffer .= "#\n"; // Blank comment separator
    }

}
// Sending HTTP headers.
ob_end_clean();
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment filename=\"$filename\";");
header("Content-Transfer-Encoding: binary");
echo $csvbuffer;

// echo '200';