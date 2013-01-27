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
 * This file contains functions used by the trainingsessions report
 *
 * @package    report
 * @subpackage trainingsessions
 * @copyright  2012 Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_trainingsessions_extend_navigation_course($navigation, $course, $context) {
    global $CFG, $OUTPUT;
    if (has_capability('report/trainingsessions:view', $context)) {
        $url = new moodle_url('/report/trainingsessions/index.php', array('id' => $course->id));
        $navigation->add(get_string('pluginname', 'report_trainingsessions'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

function report_trainingsessions_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = array(
        '*'                          => get_string('page-x', 'pagetype'),
        'report-*'                   => get_string('page-report-x', 'pagetype'),
        'report-trainingsessions-*'     => get_string('page-report-trainingsessions-x',  'report_trainingsessions'),
        'report-trainingsessions-index' => get_string('page-report-trainingsessions-index',  'report_trainingsessions'),
    );
    return $array;
}

/**
 * Is current user allowed to access this report
 *
 * @private defined in lib.php for performance reasons
 *
 * @param stdClass $user
 * @param stdClass $course
 * @return bool
 */
function report_trainingsessions_can_access_user_report($user, $course) {
    global $USER;

    $coursecontext = context_course::instance($course->id);
    $personalcontext = context_user::instance($user->id);

    if (has_capability('report/trainingsessions:view', $coursecontext)) {
        return true;
    } else if ($user->id == $USER->id) {
        if ($course->showreports and (is_viewing($coursecontext, $USER) or is_enrolled($coursecontext, $USER))) {
            return true;
        }
    }

    return false;
}

/**
* Called by the storage subsystem to give back a raw report
*
*/
function report_trainingsessions_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload){
    require_course_login($course);

    if ($filearea !== 'rawreports') {
        send_file_not_found();
    }

    $fs = get_file_storage();

    $filename = array_pop($args);
    $filepath = $args ? '/'.implode('/', $args).'/' : '/';

    if (!$file = $fs->get_file($context->id, 'report_trainingsessions', 'rawreports', $course->id, $filepath, $filename) or $file->is_directory()) {
        send_file_not_found();
    }

    $forcedownload = true;

    session_get_instance()->write_close();
    send_stored_file($file, 60*60, 0, $forcedownload);
}
