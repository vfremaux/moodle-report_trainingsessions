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
 * @package    report_trainingsessions
 * @category   report
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This script handles the session report generation in batch task for a single user.
 * It will produce a single PDF report that is pushed immediately to output
 * for downloading by a batch agent. No file is stored into the system.
 * userid must be provided.
 * This script should be sheduled in a CURL call stack or a multi_CURL parallel call.
 */

require('../../../config.php');

ob_start();
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/pdfrenderers.php');

$id = required_param('id', PARAM_INT) ; // the course id (context for user targets)
$userid = required_param('userid', PARAM_INT) ; // user id
$startday = optional_param('startday', -1, PARAM_INT) ; // from (-1 is from course start)
$startmonth = optional_param('startmonth', -1, PARAM_INT) ; // from (-1 is from course start)
$startyear = optional_param('startyear', -1, PARAM_INT) ; // from (-1 is from course start)
$endday = optional_param('endday', -1, PARAM_INT) ; // to (-1 is till now)
$endmonth = optional_param('endmonth', -1, PARAM_INT) ; // to (-1 is till now)
$endyear = optional_param('endyear', -1, PARAM_INT) ; // to (-1 is till now)
$fromstart = optional_param('fromstart', 0, PARAM_INT) ; // force reset to course startdate
$from = optional_param('from', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$to = optional_param('to', -1, PARAM_INT) ; // alternate way of saying from when for XML generation
$timesession = optional_param('timesession', time(), PARAM_INT) ; // time of the generation batch
$readabletimesession = date('Ymd_H_i_s', $timesession);
$sessionday = date('Ymd', $timesession);
$reportscope = required_param('scope', PARAM_TEXT); // Scope for reported data
$filename = optional_param('outputname', '', PARAM_FILE);

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);
report_trainingsessions_back_office_access($course);
$config = get_config('report_trainingsessions');

// TODO : secure groupid access depending on proper capabilities

// calculate start time

if ($from == -1) {
    // maybe we get it from parameters
    if ($startday == -1 || $fromstart) {
        $from = $course->startdate;
    } else {
        if ($startmonth != -1 && $startyear != -1) {
            $from = mktime(0, 0, 8, $startmonth, $startday, $startyear);
        } else {
            print_error('Bad start date');
        }
    }
}

if ($to == -1) {
    // maybe we get it from parameters
    if ($endday == -1) {
        $to = time();
    } else {
        if ($endmonth != -1 && $endyear != -1) {
            $to = mktime(0,0,8,$endmonth, $endday, $endyear);
        } else {
            print_error('Bad end date');
        }
    }
}

$user = $DB->get_record('user', array('id' => $userid));

// Print result.
if (!empty($user)) {

    // generate PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    $pdf->SetTitle(get_string('sessionreportdoctitle', 'report_trainingsessions'));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->AddPage();

    // Define variables
    // Portrait
    $x = 20;
    $y = $config->pdfabsoluteverticaloffset;
    $lineincr = 8;
    $dblelineincr = $lineincr * 2;
    $smalllineincr = 4;

    // Set alpha to no-transparency
    // $pdf->SetAlpha(1);

    // Add images and lines.
    report_trainingsessions_draw_frame($pdf);

    // Add images and lines.
    report_trainingsessions_print_header($pdf);

    // Add images and lines.
    report_trainingsessions_print_footer($pdf);

    if ($reportscope == 'allcourses') {
        $y = report_trainingsessions_print_usersessions($pdf, $userid, $y, $from, $to, 0);
    } else {
        $y = report_trainingsessions_print_usersessions($pdf, $userid, $y, $from, $to, $course);
    }
    report_trainingsessions_print_signboxes($pdf, $y + $lineincr, array('student' => true, 'authority' => true));

    // Sending HTTP headers
    ob_end_clean();
    $loadmode = (empty($filename)) ? 'S' : 'D';
    $result = $pdf->Output($filename, $loadmode);
    if ($loadmode == 'S') {
        echo $result;
    }
}
exit(0);
// echo '200';