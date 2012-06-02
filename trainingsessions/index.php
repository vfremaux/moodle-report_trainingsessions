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
 * Course overview report
 *
 * @package    report
 * @subpackage courseoverview
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/lib/statslib.php');
require_once($CFG->libdir.'/adminlib.php');

$report     = optional_param('report', STATS_REPORT_ACTIVE_COURSES, PARAM_INT);
$time       = optional_param('time', 0, PARAM_INT);
$id         = required_param('id', PARAM_INT); // course id.
$ashtml     = optional_param('ashtml', false, PARAM_BOOL);
$asxls      = optional_param('asxls', false, PARAM_BOOL);
$view       = optional_param('view', 'user', PARAM_ALPHA);
    
if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourse');
}

$PAGE->set_url('/report/outline/index.php', array('id'=>$id));
$PAGE->set_pagelayout('report');

require_login($course);
$context = context_course::instance($course->id);
require_capability('report/trainingsessions:view', $context);

$strreports = get_string('reports');
$strcourseoverview = get_string('trainingsessions', 'report_trainingsessions');

add_to_log($course->id, "course", "trainingreports view", "/course/report/trainingsessions/index.php?id=$course->id", $course->id);

if (!$asxls){
	echo $OUTPUT->header();
    
    $OUTPUT->container_start();

    /// Print tabs with options for user
    $rows[0][] = new tabobject('user', "index.php?id={$course->id}&amp;view=user", get_string('user', 'report_trainingsessions'));
    $rows[0][] = new tabobject('course', "index.php?id={$course->id}&amp;view=course", get_string('course', 'report_trainingsessions'));
    
    print_tabs($rows, $view);

    $OUTPUT->container_end();
}

@ini_set('max_execution_time','3000');
raise_memory_limit('250M');

if (file_exists($CFG->dirroot."/report/trainingsessions/{$view}report.php")){
    include_once $CFG->dirroot."/report/trainingsessions/{$view}report.php";
} else {
    print_error('errorbadviewid', 'report_trainingsessions');
}

if (!$asxls) echo $OUTPUT->footer();
