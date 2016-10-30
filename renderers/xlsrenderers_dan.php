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
 * Rasters to output to XLS format.
 *
 * @package    coursereport_trainingsessions
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * a raster for xls printing of a report structure header
 * with all the relevant data about a user.
 */
function training_reports_print_header_xls(&$worksheet, $userid, $courseid, $data, $xls_formats) {
    global $CFG;

    if (empty($CFG->trainingsessionsdateformat)) {
        set_config(trainingsessionsdateformat, '%Y-%m-%d %H:%M');
    }

    $user = get_record('user', 'id', $userid);

    if ($courseid) {
        $course = get_record('course', 'id', $courseid);
    }

    $row = 0;

    $worksheet->set_row(0, 40, $xls_formats['t']);
    $worksheet->write_string($row, 0, get_string('sessionreports', 'report_trainingsessions'), $xls_formats['t']);
    $worksheet->merge_cells($row, 0, 0, 12);
    $row++;
    $worksheet->write_string($row, 0, get_string('user').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, fullname($user));
    $row++;
    $worksheet->write_string($row, 0, get_string('email').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, $user->email);
    $row++;
    $worksheet->write_string($row, 0, get_string('institution').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, $user->institution);
    $row++;
    $worksheet->write_string($row, 0, get_string('idnumber').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, $user->idnumber);
    $row++;
    $worksheet->write_string($row, 0, get_string('city').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, $user->city);
    $row++;
    if ($courseid) {
        $worksheet->write_string($row, 0, get_string('course', 'report_trainingsessions').' :', $xls_formats['b']);
        $worksheet->write_string($row, 1, $course->fullname);
        $row++;
    }
    $worksheet->write_string($row, 0, get_string('from').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, strftime($CFG->trainingsessionsdateformat, $data->from));
    $row++;
    $worksheet->write_string($row, 0, get_string('to').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, strftime($CFG->trainingsessionsdateformat, $data->to));
//    $worksheet->write_string($row, 1, strftime($CFG->trainingsessionsdateformat, time()));
    $row++;

    // print group status
    if ($courseid) {
        $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

        $worksheet->write_string($row, 0, get_string('groups').' :', $xls_formats['b']);
        $str = '';
        if (!empty($usergroups)) {
            foreach ($usergroups as $group) {
                $str = $group->name;
                if ($group->id == get_current_group($courseid)) {
                    $str = "[$str]";
                }
                $groupnames[] = $str;
            }
            $str = implode(', ', $groupnames);
        }

        $worksheet->write_string($row, 1, $str);
        $row++;
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        $worksheet->write_string($row, 0, get_string('roles').' :', $xls_formats['b']);
        $worksheet->write_string($row, 1, strip_tags(get_user_roles_in_context($userid, $context)));
        $row++;
    }

    if (isset($data->done)) {
        // print completion bar
        $completed = $data->done / $data->items;
        $remaining = 1 - $completed;
        $completedpc = ceil($completed * 100);
        $remainingpc = 100 - $completedpc;

        $worksheet->write_string($row, 0, get_string('done', 'report_trainingsessions'), $xls_formats['b']);
        $worksheet->write_string($row, 1, $data->done. ' ' . get_string('over', 'report_trainingsessions'). ' '. $data->items. ' ('.$completedpc.' %)');
        $row++;
    }
    $worksheet->write_string($row, 0, get_string('elapsed', 'report_trainingsessions').' :', $xls_formats['b']);
    $worksheet->write_number($row, 1, training_reports_format_time(0 + @$data->totaltime, 'xls'), $xls_formats['zt']);
    $row++;
    $worksheet->write_string($row, 0, get_string('equlearningtime', 'report_trainingsessions').' :', $xls_formats['b']);
    $worksheet->write_number($row, 1, training_reports_format_time(0 + @$data->elapsed, 'xls'), $xls_formats['zt']);
    $row++;
    $worksheet->write_string($row, 0, get_string('activitytime', 'report_trainingsessions').' :', $xls_formats['b']);
    $worksheet->write_number($row, 1, training_reports_format_time(0 + @$data->activityelapsed, 'xls'), $xls_formats['zt']);
    $row++;
    $worksheet->write_string($row, 0, get_string('hits', 'report_trainingsessions').' :', $xls_formats['b']);
    $worksheet->write_number($row, 1, $data->events);

    return $row;
}

/**
 * a raster for xls printing of a report structure.
 *
 */
function training_reports_print_xls(&$worksheet, &$structure, &$aggregate, &$done, &$row, &$xls_formats, $level = 1) {
    global $CFG;

    if (empty($structure)) {
        $str = get_string('nostructure', 'report_trainingsessions');
        $worksheet->write_string($row, 1, $str);
        return;
    }

    if (isset($CFG->block_use_stats_ignoremodules)) {
        $ignoremodulelist = explode(',', $CFG->block_use_stats_ignoremodules);
    } else {
        $ignoremodulelist = array();
    }

    // makes a blank dataobject.
    if (!isset($dataobject)) {
        $dataobject->elapsed = 0;
        $dataobject->events = 0;
    }

    if (is_array($structure)) {
        foreach ($structure as $element) {
            if (isset($element->instance) && empty($element->instance->visible)) {
                continue; // non visible items should not be displayed
            }
            $res = training_reports_print_xls($worksheet, $element, $aggregate, $done, $row, $xls_formats, $level);
            $dataobject->elapsed += $res->elapsed;
            $dataobject->events += $res->events;
        }
    } else {
        $txtformat = (isset($xls_formats['a'.$level])) ? $xls_formats['a'.$level] : $xls_formats['z'] ;
        $numformat = (isset($xls_formats['a'.$level])) ? $xls_formats['a'.$level] : $xls_formats['z'] ;
        $timeformat = (isset($xls_formats['zt'.$level])) ? $xls_formats['zt'.$level] : $xls_formats['zt'] ;
        $lineformat = (isset($xls_formats['_'.$level])) ? $xls_formats['_'.$level] : $xls_formats['z'] ;

        if (!isset($element->instance) || !empty($element->instance->visible)) {
            // non visible items should not be displayed
            if (!empty($structure->name)) {
                // write element title
                $indent = str_pad('', 3 * $level, ' ');
                $str = $indent.shorten_text($structure->name, 85);
                $worksheet->set_row($row, 18, $lineformat);
                $worksheet->write_string($row, 0, $str, $txtformat);
                $worksheet->write_blank($row, 1, $txtformat);

                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $done++;
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }

                $thisrow = $row; // saves the current row for post writing aggregates
                $row++;
                if (!empty($structure->subs)) {
                    // debug_trace("with subs");
                    $res = training_reports_print_xls($worksheet, $structure->subs, $aggregate, $done, $row, $xls_formats, $level + 1);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }

                $elapsedtime = training_reports_format_time($dataobject->elapsed, 'xls');
                if ( ($dataobject->events>0) || ($elapsedtime>0) ){
                    $worksheet->write_number($thisrow, 2, $elapsedtime, $timeformat);
                    $worksheet->write_number($thisrow, 3, $dataobject->events, $numformat);
                } else {
                    $worksheet->write_blank($thisrow, 2, $txtformat);
                    $worksheet->write_blank($thisrow, 3, $txtformat);
                }

                if (!in_array($structure->type, $ignoremodulelist)) {
                    if (!empty($dataobject->timesource) && $dataobject->timesource == 'credit' && $dataobject->elapsed) {
                        $worksheet->write_string($row, 4, get_string('credittime', 'block_use_stats'), $txtformat);
                    }
                    if (!empty($dataobject->timesource) && $dataobject->timesource == 'declared' && $dataobject->elapsed) {
                        $worksheet->write_string($row, 4, get_string('declaredtime', 'block_use_stats'), $txtformat);
                    }
                } else {
                    $worksheet->write_string($row, 4, get_string('ignored', 'block_use_stats'), $txtformat);
                }

            } else {
                // It is only a structural module that should not impact on level
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $res = training_reports_print_xls($worksheet, $structure->subs, $aggregate, $done, $row, $xls_formats, $level);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }
            }
        }
    }
    return $dataobject;
}

function build_training_reports_xls_format($workbook, $size,$bold,$color,$fgcolor,$numfmt=null){

    $format =& $workbook->add_format();

    if ($size!=null){
        $format->set_size($size);
    }

    if ($color!=null){
        $format->set_color($color);
    }

    if ($fgcolor!=null){
        $format->set_fg_color($fgcolor);
    }

    if ($bold!=null){
        $format->set_bold(1);
    }

    if ($numfmt!=null){
        $format->set_num_format($numfmt);
    }

    return $format;
}

/**
 * sets up a set fo formats
 * @param object $workbook
 * @return array of usable formats keyed by a label
 *
 * Formats :
 * t : Big Title
 * tt : section caption
 * p : bolded paragraph
 * z : numeric (normal)
 * zt : time format
 * zd : date format
 */
function training_reports_xls_formats(&$workbook) {
    // size constants
    $sizettl = 20;
    $sizehd1 = 14;
    $sizehd2 = 12;
    $sizehd3 = 9;
    $sizebdy = 9;
    // color constants
    $colorttl = 1;
    $colorhd1 = null;
    $colorhd2 = null;
    $colorhd3 = null;
    $colorbdy = null;
    // fg color constants
    $fgcolorttl = 4;
    $fgcolorhd1 = 31;
    $fgcolorhd2 = null;
    $fgcolorhd3 = null;
    $fgcolorbdy = null;
    // numeric format constants
    $timefmt = '[h]:mm:ss';
    $datefmt = 'aaaa/mm/dd hh:mm';
    // weight constants
    $notbold = null;
    $bold = 1;

    // title formats
    $xls_formats['t']   =& build_training_reports_xls_format( $workbook, $sizettl, $bold,    $colorbdy, $fgcolorbdy );
    $xls_formats['tt']  =& build_training_reports_xls_format( $workbook, $sizebdy, $bold,    $colorttl, $fgcolorttl );

    // text formats
    $xls_formats['a0']  =& build_training_reports_xls_format( $workbook, $sizehd1, $bold,    $colorttl, $fgcolorttl );
    $xls_formats['a1']  =& build_training_reports_xls_format( $workbook, $sizehd1, $notbold, $colorhd1, $fgcolorhd1 );
    $xls_formats['a2']  =& build_training_reports_xls_format( $workbook, $sizehd2, $notbold, $colorhd2, $fgcolorhd2 );
    $xls_formats['a3']  =& build_training_reports_xls_format( $workbook, $sizehd3, $notbold, $colorhd3, $fgcolorhd3 );
    $xls_formats['b']   =& build_training_reports_xls_format( $workbook, $sizebdy, $bold,    $colorbdy, $fgcolorbdy );

    // number formats
    $xls_formats['z']   =& build_training_reports_xls_format( $workbook, $sizebdy, $notbold, $colorbdy, $fgcolorbdy );

    // time formats
    $xls_formats['zt1'] =& build_training_reports_xls_format( $workbook, $sizehd1, $notbold, $colorhd1, $fgcolorhd1, $timefmt );
    $xls_formats['zt2'] =& build_training_reports_xls_format( $workbook, $sizehd2, $notbold, $colorhd2, $fgcolorhd2, $timefmt );
    $xls_formats['zt3'] =& build_training_reports_xls_format( $workbook, $sizehd3, $notbold, $colorhd3, $fgcolorhd3, $timefmt );
    $xls_formats['zt']  =& build_training_reports_xls_format( $workbook, $sizebdy, $notbold, $colorbdy, $fgcolorbdy, $timefmt );

    // date formats
    $xls_formats['zd']  =& build_training_reports_xls_format( $workbook, $sizebdy, $notbold, $colorbdy, $fgcolorbdy, $datefmt );

    // line-height formats (applying heights for different line types without any of the rest of the formatting)
    $xls_formats['_tt'] =& build_training_reports_xls_format( $workbook, $sizehd1, $notbold, $colorbdy, $fgcolorbdy );
    $xls_formats['_1']  =& build_training_reports_xls_format( $workbook, $sizehd1, $notbold, $colorbdy, $fgcolorbdy );
    $xls_formats['_2']  =& build_training_reports_xls_format( $workbook, $sizehd2, $notbold, $colorbdy, $fgcolorbdy );
    $xls_formats['_3']  =& build_training_reports_xls_format( $workbook, $sizehd3, $notbold, $colorbdy, $fgcolorbdy );

    return $xls_formats;
}

/**
 * initializes a new worksheet with static formats
 * @param int $userid
 * @param int $startrow
 * @param array $xls_formats
 * @param object $workbook
 * @return the initialized worksheet.
 */
function training_reports_init_worksheet($userid, $startrow, &$xls_formats, &$workbook, $purpose = 'usertimes') {
    global $CFG;

    $user = get_record('user', 'id', $userid);

    // instantiate the worksheet
    $sheettitle = fullname($user);
    if ($purpose == 'sessions') {
        $sessionsstr=get_string('sessionstabtitle', 'report_trainingsessions');
        $sheettitle .= " ($sessionsstr)";
    }
    if ($CFG->latinexcelexport){
        $sheettitle = mb_convert_encoding( $sheettitle, 'ISO-8859-1', 'UTF-8' );
    }
    $worksheet =& $workbook->add_worksheet($sheettitle);

    if ($purpose == 'usertimes') {
        $worksheet->set_column(0,0,20);
        $worksheet->set_column(1,1,74);
        $worksheet->set_column(2,2,14);
        $worksheet->set_column(3,3,5);
    } elseif ($purpose == 'allcourses') {
        $worksheet->set_column(0,0,50);
        $worksheet->set_column(1,1,50);
        $worksheet->set_column(2,2,12);
        $worksheet->set_column(3,3,4);
    } else {
        $worksheet->set_column(0,0,30);
        $worksheet->set_column(1,1,30);
        $worksheet->set_column(2,2,20);
        $worksheet->set_column(3,3,10);
    }
    $worksheet->set_column(4,4,12);
    $worksheet->set_column(5,5,4);
    $worksheet->set_column(6,6,12);
    $worksheet->set_column(7,7,4);
    $worksheet->set_column(8,8,12);
    $worksheet->set_column(9,9,4);
    $worksheet->set_column(10,10,12);
    $worksheet->set_column(11,11,4);
    $worksheet->set_column(12,12,12);
    $worksheet->set_column(13,13,4);

    if ( $purpose == 'usertimes'){
        $worksheet->set_row($startrow - 1, 18, $xls_formats['_tt']);
        $worksheet->write_string($startrow - 1, 0, get_string('item', 'report_trainingsessions'), $xls_formats['a0']);
        $worksheet->write_blank($startrow - 1,1, $xls_formats['tt']);
        $worksheet->write_string($startrow - 1, 2, get_string('elapsed', 'report_trainingsessions'), $xls_formats['a0']);
        $worksheet->write_string($startrow - 1, 3, get_string('hits', 'report_trainingsessions'), $xls_formats['a0']);
    } else if ( $purpose == 'sessions'){
        $worksheet->set_row($startrow - 1, 14, $xls_formats['_tt']);
        $worksheet->write_string($startrow - 1, 0, get_string('sessionstart', 'report_trainingsessions'), $xls_formats['tt']);
        $worksheet->write_string($startrow - 1, 1, get_string('sessionend', 'report_trainingsessions'), $xls_formats['tt']);
        $worksheet->write_string($startrow - 1, 2, get_string('elapsed', 'report_trainingsessions'), $xls_formats['tt']);
        $worksheet->write_string($startrow - 1, 3, get_string('hits', 'report_trainingsessions'), $xls_formats['tt']);
    }
    return $worksheet;
}

/**
 * print session table in an initialied worksheet
 * @param object $worksheet
 * @param int $row
 * @param array $sessions
 * @param object $xls_formats
 */
function training_reports_print_sessions_xls(&$worksheet, $row, &$sessions, &$xls_formats, $courseid = 0) {

    $totalelapsed = 0;

    if (!empty($sessions)) {
        foreach ($sessions as $s) {

            if ($courseid && ($courseid != $s->course) ) {
                // Omit all sessions not visiting this course.
                continue;
            }

            $worksheet->write_number($row, 0, training_reports_format_time($s->sessionstart, 'xls'), $xls_formats['zd']);
            if (!empty($s->sessionend)) {
                $worksheet->write_number($row, 1, training_reports_format_time($s->sessionend, 'xls'), $xls_formats['zd']);
            }
            $worksheet->write_number($row, 2, training_reports_format_time(0 + @$s->elapsed, 'xls'), $xls_formats['zt']);
            $worksheet->write_number($row, 3, count($s->logs)-1, $xls_formats['z']);
            $totalelapsed += 0 + @$s->elapsed;
            $row++;
        }
    }
    return $totalelapsed;
}

/**
 * print logs table to a new tab in worksheet
 * @param object $worksheet
 * @param int $row
 * @param array aggregate
 * @param object $xls_formats
 * @param string $tabname
 */
function training_reports_add_logs_tab_xls(&$workbook, &$aggregate, &$xls_formats, $tabname=null) {
    // lookup loca strings
    $logstabstr = get_string('logstabtitle', 'report_trainingsessions');
    $sessionnamestr = get_string('sessiontitle', 'report_trainingsessions');

    // create the worksheet
    if (!$tabname){
        $tabname = $logstabstr;
    }
    $worksheet =& $workbook->add_worksheet( $tabname );

    // names of the fieelds to display in the table
    $logfields = array(
        'id'=>'z',
        'time'=>'zd',
        'action'=>'a',
        'module'=>'a',
        'duration'=>'zt',
    );

    // init the worksheet column properties
    $worksheet->set_column(0,0,10);
    $worksheet->set_column(1,1,35);
    $worksheet->set_column(2,2,20);
    $worksheet->set_column(3,3,20);
    $worksheet->set_column(4,4,10);
    $worksheet->set_column(5,5,10);

    // fetch course names for all courses from the database and store them away in a lookup table
    $coursenames = array();
    $courserecords=get_records('course', '', '', '', 'id,idnumber,shortname,fullname');
    foreach ($courserecords as $course){
        if ($course->fullname){
            $coursename="{$course->fullname}";
        }else if ($course->idnumber){
            $coursename="{($course->idnumber} / {$course->shortname}";
        }else{
            $coursename="{$course->shortname} ";
        }
        $coursenames[$course->id] = $coursename;
    }
    // overwrite the course name for the 'site' course
    $coursenames[1]='----';

    $row=0;
    for ($i = count($aggregate['sessions']); $i>0; $i--){
        // display a session header and followup with fome formatted blank cells to make a colourd header bar
        $session = $aggregate['sessions'][$i-1];
        $coursename = $coursenames[ $session->course ];
        $worksheet->set_row($row,18,$xls_formats['_1']);
        $worksheet->write_string($row, 0, $sessionnamestr .' ' . $i . ': ' . $coursename, $xls_formats['a1']);
        for($col=count($logfields)-1; $col>0; $col--){
            $worksheet->write_blank($row, $col, $xls_formats['a1']);
        }
        // if this is a real session then display the padded session duration in the heading bar
        if ($session->course != 1){
            $worksheet->write_number($row, 4, training_reports_format_time( $session->elapsed, 'xls' ), $xls_formats['zt1']);
        }
        // display column headings
        ++$row;
        $col=0;
        foreach($logfields as $fieldname => $fmt){
            $worksheet->write_string($row, $col, $fieldname, $xls_formats['tt']);
            ++$col;
        }
        // iterate over the logs for this session
        ++$row;
        for ($j = count($session->logs)-1; $j>0; $j--){ // -1 to skip the session termuinator
            $log=$session->logs[$j-1];
            $col=0;
            foreach($logfields as $fieldname => $fmt){
                $val=$log->$fieldname;
                switch($fmt){
                    case 'zd':
                        // note that for dates it turns out that we convert tot text ourselves as we don't appear to have a method to convert numeric dates to xls format
                        $worksheet->write_string($row, $col, userdate($val), $xls_formats['z']);
                        break;
                    case 'zt':
                        // convert integer date/time values to xls format
                        $val=training_reports_format_time($val, 'xls');
                    case 'z':
                        // note that the 'zt' case drops through to here so we are displaying a number or a time interval value
                        $worksheet->write_number($row, $col, $val, $xls_formats[$fmt]);
                        break;
                    default:
                        // by default output a bog standard string
                        $worksheet->write_string($row, $col, $val, $xls_formats['z']);
                }
                ++$col;
            }
            // add a comment next to the lines to indicate which are 'site' logs and which are 'course' logs
            $coursetypeid = ($log->course==1)? "logtypesite": "logtypecourse";
            $coursetypestr = get_string($coursetypeid, 'report_trainingsessions');
            $worksheet->write_string($row, $col, $coursetypestr, $xls_formats['z']);
            ++$row;
        }
        // add a blank line between sessions
        ++$row;
    }
}


/**
 * a raster for Excel printing of a report structure.
 *
 * @param ref $worksheet a buffer for accumulating output
 * @param object $aggregate aggregated logs to explore.
 */
function training_reports_print_allcourses_xls(&$worksheet, &$aggregate, $row, &$xls_formats) {
    global $CFG, $COURSE;

    $output = array();
    $courses = array();
    $courseids = array();
    $return->elapsed = 0;
    $return->events = 0;
    if (!empty($aggregate['coursetotal'])) {
        foreach ($aggregate['coursetotal'] as $cid => $cdata) {
            if ($cid != 0) {
                if (!in_array($cid, $courseids)) {
                    $courses[$cid] = get_record('course', 'id', $cid, '', '', '', '', 'id,idnumber,shortname,fullname,category');
                    $courseids[$cid] = '';
                }
                $output[$courses[$cid]->category][$cid] = $cdata;
                $catids[$courses[$cid]->category] = '';
            } else {
                // echo "ignoring hidden $cdata->elapsed ";
                $output[0][SITEID]->elapsed += $cdata->elapsed;
                $output[0][SITEID]->events += $cdata->events;
            }
            $return->elapsed += $cdata->elapsed;
            $return->events += $cdata->events;
        }

        $catidlist = implode(',', array_keys($catids));
        $coursecats = get_records_list('course_categories', 'id', $catidlist);
    }

    if (!empty($output)) {

        $elapsedstr = get_string('elapsed', 'report_trainingsessions');
        $hitsstr = get_string('hits', 'report_trainingsessions');
        $coursestr = get_string('course');
        $titleformat = $xls_formats['a1'];
        $titlelineformat = $xls_formats['_1'];
        $titlelineheight = 18;

        if (isset($output[0])) {
            $row++;
            $worksheet->set_row($row, $titlelineheight, $titlelineformat);
            $worksheet->write_string($row, 0, get_string('site'), $titleformat);
            $worksheet->write_blank($row, 1, $titleformat);
            $worksheet->write_blank($row, 2, $titleformat);
            $row++;
            $worksheet->write_string($row, 0, $elapsedstr, $xls_formats['b']);
            $worksheet->write_number($row, 1, training_reports_format_time($output[0][SITEID]->elapsed, 'xls'), $xls_formats['zt']);
            $row++;
            $worksheet->write_string($row, 0, $hitsstr, $xls_formats['b']);
            $worksheet->write_number($row, 1, $output[0][SITEID]->events, $xls_formats['z']);
            $row++;
        }

        foreach ($output as $catid => $catdata) {
            if ($catid == 0) {
                continue;
            }
            $row++;
            $worksheet->set_row($row, $titlelineheight, $titlelineformat);
            $worksheet->write_string($row, 0, $coursecats[$catid]->name, $titleformat);
            $worksheet->write_blank($row, 1, $titleformat);
            $worksheet->write_blank($row, 2, $titleformat);
            $row++;
            $worksheet->write_string($row, 0, $coursestr, $xls_formats['tt']);
            $worksheet->write_string($row, 1, $elapsedstr, $xls_formats['tt']);
            $worksheet->write_string($row, 2, $hitsstr, $xls_formats['tt']);
            $row++;

            foreach ($catdata as $cid => $cdata) {
                $ccontext = get_context_instance(CONTEXT_COURSE, $cid);
                if (has_capability('coursereport/trainingsessions:view', $ccontext)) {
                    $worksheet->write_string($row, 0, $courses[$cid]->fullname, $xls_formats['b']);
                    if ($cdata->events>0){
                        $worksheet->write_number($row, 1, training_reports_format_time($cdata->elapsed, 'xls'), $xls_formats['zt']);
                        $worksheet->write_number($row, 2, $cdata->events, $xls_formats['z']);
                    }
                    $row++;
                } else {
                    $worksheet->write_string($row, 0, $courses[$cid]->fullname, $xls_formats['b']);
                    $worksheet->write_string($row, 2, get_string('nopermissiontoview', 'report_trainingsessions'), $xls_formats['b']);
                }
            }
        }
    }

    return $return;
}

/**
 * prints a raw data row in the worksheet
 * @param object $worksheet
 * @param array $data
 * @param array $dataformats
 * @param int $row
 * @param array $xls_formats predefined set of formats
 */
function training_reports_print_rawline_xls(&$worksheet, $data, $dataformats, $row, &$xls_formats) {

    for ($i = 0 ; $i < count($data) ; $i++) {

        if (!array_key_exists($dataformats[$i], $xls_formats)) {
            throw new Exception('Unknown XLS format '.$dataformats[$i]);
        }

        if (preg_match('/^z/', $dataformats[$i])) {
            if ($dataformats[$i] == 'z') {
                $celldata = $data[$i];
            } else {
                $celldata =  training_reports_format_time($data[$i], 'xls');
            }
            $worksheet->write_string($row, $i, $celldata, $xls_formats[$dataformats[$i]]);
        } else {
            $worksheet->write_string($row, $i, $data[$i], $xls_formats[$dataformats[$i]]);
        }
    }
    return ++$row;
}
