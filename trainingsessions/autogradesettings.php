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
 * This sets the default form data without having to go to the grade settings page
 *
 * @package    report_trainingsessions
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @version    moodle 3.2.3
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once('../../config.php');
require_once($CFG->dirroot.'/report/trainingsessions/gradesettings_form.php');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourse');
}

$from = $course->startdate;
$to = $course->enddate;

$context = context_course::instance($course->id);

//Inserting main course record
if(!$DB->record_exists('report_trainingsessions', array('courseid'=>$course->id, 'moduleid'=>0))) {
    $rec = new StdClass();
    $rec->courseid = $course->id;
    $rec->moduleid = 0;
    $rec->sortorder = 0;
    $rec->label = 'Total';
    $rec->grade = 0;
    $rec->ranges = '';
    $rec->displayed = 1;
    $DB->insert_record('report_trainingsessions', $rec);
}

$grades = $DB->get_records('grade_items', array('courseid'=>$course->id));
foreach($grades as $ix=>$gr) {
    if(!$gr->itemmodule) continue;
    $mod = $DB->get_record('modules', array('name'=>$gr->itemmodule));
    $cm = $DB->get_record('course_modules', array('course'=>$course->id, 'module'=>$mod->id, 'instance'=>$gr->iteminstance));
    if($DB->record_exists('report_trainingsessions', array('courseid'=>$course->id, 'moduleid'=>$cm->id))) continue;
    $rec = new stdClass();
    $rec->courseid = $course->id;
    $rec->moduleid = $cm->id;
    $coursemodinfo = get_fast_modinfo($course->id);
    $cminfo = $coursemodinfo->get_cm($cm->id);
    $rec->label = $cminfo->get_formatted_name();
    $rec->sortorder = $ix;
    $rec->grade = 0;
    $rec->ranges = '';
    $DB->insert_record('report_trainingsessions', $rec);
}
