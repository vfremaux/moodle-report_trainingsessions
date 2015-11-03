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
 * The standard renderer of the component
 *
 * @package    report_trainingsessions
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class report_trainingsessions_renderer extends plugin_renderer_base {

    function tabs($course, $view, $from, $to) {

        $context = context_course::instance($course->id);

        // Print tabs with options for user.
        $userurl = new moodle_url('/report/trainingsessions/index.php', array('id' => $course->id, 'view' => 'user', 'from' => $from, 'to' => $to));
        $rows[0][] = new tabobject('user', $userurl, get_string('user', 'report_trainingsessions'));
        if (has_capability('report/trainingsessions:viewother', $context)) {
            $courseurl = new moodle_url('/report/trainingsessions/index.php', array('id' => $course->id, 'view' => 'course', 'from' => $from, 'to' => $to));
            $rows[0][] = new tabobject('course', $courseurl, get_string('course', 'report_trainingsessions'));
            $courserawurl = new moodle_url('/report/trainingsessions/index.php', array('id' => $course->id, 'view' => 'courseraw', 'from' => $from, 'to' => $to));
            $rows[0][] = new tabobject('courseraw', $courserawurl, get_string('courseraw', 'report_trainingsessions'));
        }
        $allcoursesurl = new moodle_url('/report/trainingsessions/index.php', array('id' => $course->id, 'view' => 'allcourses', 'from' => $from, 'to' => $to));
        $rows[0][] = new tabobject('allcourses', $allcoursesurl, get_string('allcourses', 'report_trainingsessions'));

        $gradesettingsurl = new moodle_url('/report/trainingsessions/gradessettings.php', array('id' => $course->id, 'from' => $from, 'to' => $to));
        $rows[0][] = new tabobject('gradesettings', $gradesettingsurl, get_string('gradesettings', 'report_trainingsessions'));

        $str = print_tabs($rows, $view, null, null, true);

        return $str;
    }
}