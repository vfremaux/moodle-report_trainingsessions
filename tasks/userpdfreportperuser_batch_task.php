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
 * This script handles the report generation in batch task for a single group. 
 * It will produce a group Excel worksheet report that is pushed immediately to output
 * for downloading by a batch agent. No file is stored into the system.
 * groupid must be provided.
 * This script should be sheduled in a CURL call stack or a multi_CURL parallel call.
 */
require('../../../config.php');

ob_start();
require_once($CFG->dirroot.'/blocks/use_stats/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');
require_once($CFG->dirroot.'/report/trainingsessions/renderers/pdfrenderers.php');

$id = required_param('id', PARAM_INT) ; // the course id
$userid = required_param('userid', PARAM_INT) ; // group id
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
$filename = optional_param('outputname', '', PARAM_FILE);

ini_set('memory_limit', '512M');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    die ('Invalid course ID');
}
$context = context_course::instance($course->id);

// Security.
report_trainingsessions_back_office_access($course);
$config = get_config('report_trainingsessions');

$coursestructure = report_trainingsessions_get_course_structure($course->id, $items);

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

// Get user.

$user = $DB->get_record('user', array('id' => $userid));

// Print result.

if (!empty($user)) {

    // generate PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    $pdf->SetTitle(get_string('sessionreportdoctitle', 'report_trainingsessions'));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetFontSize(9);
    $pdf->setCellPaddings(2,2,2,2);
    $pdf->SetAutoPageBreak(false, 0);
    
    // Sending HTTP headers

    // Define cells params 
    $table = new html_table();

    $head1bgcolor = $config->head1bgcolor;
    $head1txtcolor = $config->head1textcolor;
    $head2bgcolor = $config->head2bgcolor;
    $head2txtcolor = $config->head2textcolor;
    $head3bgcolor = $config->head3bgcolor;
    $head3txtcolor = $config->head3textcolor;

    if (!empty($config->showhits)) {
        $table->pdfhead1 = array('', get_string('firstaccess'), get_string('elapsed', 'report_trainingsessions'), get_string('hits', 'report_trainingsessions'));
        $table->pdfsize1 = array('50%', '20%', '20%', '10%');
        $table->pdfalign1 = array('L', 'L', 'R', 'R');
    } else {
        $table->pdfhead1 = array('', get_string('firstaccess'), get_string('elapsed', 'report_trainingsessions'));
        $table->pdfsize1 = array('50%', '25%', '25%');
        $table->pdfalign1 = array('L', 'L', 'R');
    }
    $table->pdfbgcolor1 = array($head1bgcolor, $head1bgcolor, $head1bgcolor);
    $table->pdfcolor1 = array($head1txtcolor, $head1txtcolor, $head1txtcolor);

    $table->pdfalign2 = array('L', 'L', 'L');
    $table->pdfsize2 = array('50%', '25%', '25%');
    $table->pdfbgcolor2 = array('#808080', $head2bgcolor, $head2bgcolor);
    $table->pdfcolor2 = array($head2txtcolor, $head2txtcolor, $head2txtcolor);

    if (!empty($config->showhits)) {
        $table->pdfbgcolor = array('#808080', $head2bgcolor, '#ffffff', '#ffffff');
        $table->pdfcolor = array($head2txtcolor, $head2txtcolor, '#000000', '#000000');
    } else {
        $table->pdfbgcolor = array('#808080', $head2bgcolor, '#ffffff');
        $table->pdfcolor = array($head2txtcolor, $head2txtcolor, '#000000');
    }

    $table->pdfbgcolor3 = array($head3bgcolor, $head3bgcolor, $head3bgcolor);
    $table->pdfcolor3 = array($head3txtcolor, $head3txtcolor, $head3txtcolor);
    $table->pdfalign3 = array('L', 'L', 'R', 'R');

    $table->pdfprintinfo = array(1,1,1,1);

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

    $logs = use_stats_extract_logs($from, $to, $user->id, $course->id);
    $aggregate = use_stats_aggregate_logs($logs, 'module', 0, $from, $to);

    $grantotal = report_trainingsessions_calculate_course_structure($coursestructure, $aggregate, $done, $items);
    $grantotal->activityelapsed = 0 + @$aggregate['activities'][$id]->elapsed;
    $grantotal->otherelapsed = 0 + @$aggregate['other'][$id]->elapsed;
    $grantotal->courseelapsed = 0 + @$aggregate['course'][$id]->elapsed;
    $grantotal->elapsed = 0 + $grantotal->activityelapsed + $grantotal->otherelapsed + $grantotal->courseelapsed;

    $grantotal->activityevents = 0 + @$aggregate['activities'][$id]->events;
    $grantotal->otherevents = 0 + @$aggregate['other'][$id]->events;
    $grantotal->courseevents = 0 + @$aggregate['course'][$id]->events;
    $grantotal->events = 0 + $grantotal->activityevents + $grantotal->otherevents + $grantotal->courseevents;

    $y = report_trainingsessions_print_userinfo($pdf, $y, $user, $course, $from, $to, $grantotal, $config->recipient);
    $y = report_trainingsessions_print_overheadline($pdf, $y, $table);
    report_trainingsessions_print_course_structure($pdf, $y, $coursestructure, $aggregate, $table);
    $dataline = array();

    $dataline[] = "$done / $items ".get_string('done', 'report_trainingsessions');
    $dataline[] = report_trainingsessions_format_time($grantotal->elapsed, 'xlsd');
    if (!empty($config->showhits)) {
        $dataline->events = $grantotal->events;
    }
    // Ensure cells are sized as data columns.
    if (!empty($config->showhits)) {
        $table->pdfsize2 = array('50%', '25%', '25%');
    } else {
        $table->pdfsize2 = array('75%', '25%');
    }

    $y += 5; // Small adjustment.
    $y = report_trainingsessions_print_sumline($pdf, $y, $dataline, $table);

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