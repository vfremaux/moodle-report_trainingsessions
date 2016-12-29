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

defined('MOODLE_INTERNAL') || die;

/**
 * @package    report_trainingsessions
 * @category   report
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function training_reports_print_allcourses_html(&$str, &$aggregate) {
    global $CFG, $COURSE;

    $output = array();
    $courses = array();
    $courseids = array();
    $return = new StdClass;
    $return->elapsed = 0;
    $return->events = 0;

    if (!empty($aggregate['coursetotal'])) {
        foreach ($aggregate['coursetotal'] as $cid => $cdata) {
            if ($cid != 0) {
                if (!in_array($cid, $courseids)) {
                    $courses[$cid] = get_record('course', 'id', $cid, '', '', '', '', 'id,idnumber,shortname,fullname,category');
                    $courseids[$cid] = '';
                }
                @$output[$courses[$cid]->category][$cid] = $cdata;
                @$catids[$courses[$cid]->category] = '';
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

    if (!empty($output)){

        $elapsedstr = get_string('elapsed', 'report_trainingsessions');
        $hitsstr = get_string('hits', 'report_trainingsessions');
        $coursestr = get_string('course');

        if (isset($output[0])){
            $str .= '<h2>'.get_string('siteglobals', 'report_trainingsessions').'</h2>';
            $str .= '<div class="attribute"><span class="attribute-name">'.$elapsedstr.'</span>';
            $str .= ' : ';
            $str .= '<span class="attribute-time">'.format_duration($output[0][SITEID]->elapsed).'</span></div>';
            $str .= '<div class="attribute"><span class="attribute-name">'.$hitsstr.'</span>';
            $str .= ' : ';
            $str .= '<span class="attribute-value">'.(0 + @$output[0][SITEID]->events).'</span></div>';
        }

        foreach ($output as $catid => $catdata) {
            if ($catid == 0) {
                continue;
            }
            $str .= '<h2>'.$coursecats[$catid]->name.'</h2>';
            $str .= '<table class="generaltable" width="100%">';
            $str .= '<tr class="header"><td class="header c0" width="70%"><b>'.$coursestr.'</b></td><td class="header c1" width="15%"><b>'.$elapsedstr.'</b></td><td class="header c2" width="15%"><b>'.$hitsstr.'</b></td></tr>';
            foreach ($catdata as $cid => $cdata) {
                $ccontext = get_context_instance(CONTEXT_COURSE, $cid);
                if (has_capability('coursereport/trainingsessions:view', $ccontext)) {
                    $str .= '<tr valign="top"><td>'.$courses[$cid]->fullname.'</td><td class="right">';
                    $str .= format_duration($cdata->elapsed).'<br/>';
                    $str .= '</td><td class="right">';
                    $str .= $cdata->events;
                    $str .= '</td></tr>';
                } else {
                    $str .= '<tr valign="top"><td>'.$courses[$cid]->fullname.'</td><td colspan="2">';
                    $str .= get_string('nopermissiontoview', 'report_trainingsessions');
                    $str .= '</td></tr>';
                }
            }
            $str .= '</table>';
        }
    } else {
        $str .= print_box(get_string('nodata', 'report_trainingsessions'), 'generalbox', '', true);
    }

    return $return;
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function training_reports_print_html(&$str, $structure, &$aggregate, &$done, $level = 1) {
    global $CFG, $COURSE;
    static $titled = false;

    if (isset($CFG->block_use_stats_ignoremodules)) {
        $ignoremodulelist = explode(',', $CFG->block_use_stats_ignoremodules);
    } else {
        $ignoremodulelist = array();
    }

    if (empty($structure)) {
        $str .= get_string('nostructure', 'report_trainingsessions');
        return;
    }

    if (!$titled) {
        $titled = true;
        $str .= print_heading(get_string('instructure', 'report_trainingsessions'), '', 2, 'main', true);

        // effective printing of available sessions
        $str .= '<table width="100%" id="structure-table">';
        $str .= '<tr valign="top">';
        $str .= '<td class="rangedate userreport-col0"><b>'.get_string('structureitem', 'report_trainingsessions').'</b></td>';
        $str .= '<td class="rangedate userreport-col1"><b>'.get_string('firstaccess', 'report_trainingsessions').'</b></td>';
        $str .= '<td class="rangedate userreport-col2"><b>'.get_string('lastaccess', 'report_trainingsessions').'</b></td>';
        $str .= '<td class="rangedate userreport-col3"><b>'.get_string('duration', 'report_trainingsessions').'</b></td>';
        $str .= '</tr>';
        $str .= '</table>';
    }

    $indent = str_repeat('&nbsp;&nbsp;', $level+0);
    $suboutput = '';

    // initiates a blank dataobject
    if (!isset($dataobject)) {
        $dataobject = new StdClass;
        $dataobject->elapsed = 0;
        $dataobject->events = 0;
    }

    if (is_array($structure)) {
        // if an array of elements produce sucessively each output and collect aggregates. this is essentially for recursing in subs.
        foreach ($structure as $element) {
            if (isset($element->instance) && empty($element->instance->visible)) {
                // non visible items should not be displayed
                continue;
            }
            $res = training_reports_print_html($str, $element, $aggregate, $done, $indent, $level);
            $dataobject->elapsed += $res->elapsed;
            $dataobject->events += (0 + @$res->events);
        }
    } else {
        $nodestr = '';
        if (!isset($structure->instance) || !empty($structure->instance->visible)) {
            // non visible items should not be displayed
            // name is not empty. It is a significant module (non structural)
            if (!empty($structure->name)) {
                $optionaldepth = (empty($structure->depth)) ? '' : 'depth'.$structure->depth;
                $nodestr .= '<table class="sessionreport level'.$level.'">';
                $nodestr .= '<tr class="sessionlevel'.$level.'" valign="top">';
                $nodestr .= '<td class="sessionitem item userreport-col0 level'.$level.' '.$optionaldepth.'">';
/*                if (debugging()) {
                    $nodestr .= '['.$structure->type.'] ';
                }
*/
                $nodestr .= shorten_text($structure->name, 85);
                $nodestr .= '</td>';
                $nodestr .= '<td class="sessionitem userreport-col1 rangedate">';
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])){
                    $nodestr .= userdate($aggregate[$structure->type][$structure->id]->firstaccess);
                }
                $nodestr .= '</td>';
                $nodestr .= '<td class="sessionitem userreport-col2 rangedate">';
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $nodestr .= userdate($aggregate[$structure->type][$structure->id]->lastaccess);
                }
                $nodestr .= '</td>';
                $nodestr .= '<td class="reportvalue userreport-col3 rangedate">';
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $done++;
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $res = training_reports_print_html($suboutput, $structure->subs, $aggregate, $done, $level + 1);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }

                if (!in_array($structure->type, $ignoremodulelist)){
                    if (!empty($dataobject->timesource) && $dataobject->timesource == 'credit' && $dataobject->elapsed) {
                        $nodestr .= get_string('credittime', 'block_use_stats');
                    }
                    if (!empty($dataobject->timesource) && $dataobject->timesource == 'declared' && $dataobject->elapsed) {
                        $nodestr .= get_string('declaredtime', 'block_use_stats');
                    }
                    $nodestr .= training_reports_format_time($dataobject->elapsed, 'html');
                    $nodestr .= ' ('.(0 + @$dataobject->events).')';
                } else {
                    $nodestr .= get_string('ignored', 'block_use_stats');
                }

                // plug here specific details
                $nodestr .= '</td>';
                $nodestr .= '</tr>';
                $nodestr .= '</table>';
            } else {
                // It is only a structural module that should not impact on level
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $res = training_reports_print_html($suboutput, $structure->subs, $aggregate, $done, $level);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }
            }

            if (!empty($structure->subs)) {
                $str .= "<table class=\"trainingreport\">";
                $str .= "<tr valign=\"top\">";
                $str .= "<td colspan=\"2\">";
                $str .= $suboutput;
                $str .= '</td>';
                $str .= '</tr>';
                $str .= "</table>\n";
            }
            $str .= $nodestr;
        }
    }
    return $dataobject;
}

/**
 * a raster for html printing of a report structure header
 * with all the relevant data about a user.
 *
 */
function training_reports_print_header_html($userid, $courseid, $data, $short = false, $withcompletion = true, $withnooutofstructure = false) {
    global $CFG;

    $user = get_record('user', 'id', $userid);
    $course = get_record('course', 'id', $courseid);

    $str = print_user($user, $course, false, true);

    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

    $str .= "<div class=\"userinfobox\">";

    // print group status
    if (!empty($usergroups)) {
        $str .= '<div class="attribute"><span class="attribute-name">'.get_string('groups').'</span>';
        $str .= ' : ';
        foreach ($usergroups as $group) {
            $strbuf = $group->name;
            if ($group->id == get_current_group($courseid)) {
                $strbuf = "<b>$str</b>";
            }
            $groupnames[] = $strbuf;
        }
        $str .= '<span class="attribute-value">'.implode(', ', $groupnames).'</span></div>';
    }

    // print IDNumber
    $str .= '<div class="attribute"><span class="attribute-name">'.get_string('idnumber').'</span>';
    $str .= ' : ';
    $str .= '<span class="attribute-value">'.$user->idnumber.'</span></div>';
    // print Institution
    $str .= '<div class="attribute"><span class="attribute-name">'.get_string('institution').'</span>';
    $str .= ' : ';
    $str .= '<span class="attribute-value">'.$user->institution.'</span></div>';
    // print Department
    $str .= '<div class="attribute"><span class="attribute-name">'.get_string('department').'</span>';
    $str .= ' : ';
    $str .= '<span class="attribute-value">'.$user->department.'</span></div>';

    // print Roles
    $context = get_context_instance(CONTEXT_COURSE, $courseid);
    $str .= '<div class="attribute"><span class="attribute-name">'.get_string('roles').'</span>';
    $str .= ' : ';
    $str .= '<span class="attribute-value">'.get_user_roles_in_context($userid, $context).'</span></div>';

    if (!empty($data->linktousersheet)) {
        $str .= "<a href=\"{$CFG->wwwroot}/course/report/trainingsessions/index.php?view=user&amp;id={$courseid}&amp;userid=$userid\">".get_string('seedetails', 'report_trainingsessions').'</a>';
    }

    if ($withcompletion) {
        // print completion bar
        $str .= '<div class="attribute"><span class="attribute-name">'.get_string('completion', 'report_trainingsessions').'</span>';
        $str .= ' : ';
        $str .= '<div class="attribute-value">'.training_reports_print_completionbar($data->items, $data->done, 500);
        $str .= '</div></div>';
    }

    // Start printing the overall times

    if (!$short){

        $str .= '<div class="attribute"><span class="attribute-name">'.get_string('totalsessions', 'report_trainingsessions').':</span> ';
        $str .= '<span class="attribute-time">'.training_reports_format_time(0 + @$data->totaltime, 'html').'</span>';
        $str .= helpbutton('totalsessions', get_string('totalsessions', 'report_trainingsessions'), 'report_trainingsessions', true, false, '', true);
        $str .= '</div>';
        $str .= '<div class="attribute"><span class="attribute-name">'.get_string('equlearningtime', 'report_trainingsessions').':</span> ';
        // NOTE: SADGE changed the following line as the activity time is already counted in the data->elapsed time so here it was being counted twice
        $str .= '<span class="attribute-time">'.training_reports_format_time(0 + @$data->elapsed /*+ @$data->activityelapsed*/, 'html').'</span>';
        $str .= ' <span class="attribute-hits">('.(0 + @$data->events + @$data->activityevents).')</span>';
        $str .= helpbutton('equlearningtime', get_string('equlearningtime', 'report_trainingsessions'), 'report_trainingsessions', true, false, '', true);
        $str .= '</div>';
        $str .= '<div class="attribute"><span class="attribute-name">'.get_string('activitytime', 'report_trainingsessions').':</span> ';
        $str .= '<span class="attribute-time">'.training_reports_format_time(0 + @$data->activityelapsed, 'html').'</span>';
        $str .= helpbutton('activitytime', get_string('activitytime', 'report_trainingsessions'), 'report_trainingsessions', true, false, '', true);
        $str .= '</div>';

        // plug here specific details
    }

    $str .= '<div class="attribute"><span class="attribute-name">'.get_string('workingsessions', 'report_trainingsessions').'</span> ';
    $str .= '<span class="attribute-value">'.(0 + @$data->sessions).'</span></div>';
    if (@$data->sessions == 0){
        $str .= helpbutton('checklistadvice', get_string('checklistadvice', 'report_trainingsessions'), 'report_trainingsessions');
    }
    $str .= '</div>';

    // add printing for global course time (out of activities)
    if (!$short){
        $str .= print_heading(get_string('outofstructure', 'report_trainingsessions'), '', 2, 'main', true);
        $str .= "<table cellspacing=\"0\" cellpadding=\"0\" width=\"100%\" class=\"sessionreport\">";
        if (!$withnooutofstructure){
            $str .= "<tr class=\"sessionlevel2\" valign=\"top\">";
            $str .= "<td class=\"sessionitem\">";
            $str .= get_string('courseglobals', 'report_trainingsessions');
            $str .= '</td>';
            $str .= "<td class=\"sessionvalue rangedate\" align=\"right\">";
            $str .= training_reports_format_time($data->course->elapsed).' ('.$data->course->hits.')';
            $str .= '</td>';
            $str .= '</tr>';
        }
        if (isset($data->upload)){
            $str .= "<tr class=\"sessionlevel2\" valign=\"top\">";
            $str .= "<td class=\"sessionitem\">";
            $str .= get_string('uploadglobals', 'report_trainingsessions');
            $str .= '</td>';
            $str .= "<td class=\"sessionvalue rangedate\" align=\"right\">";
            $str .= training_reports_format_time($data->upload->elapsed).' ('.$data->upload->hits.')';
            $str .= '</td>';
            $str .= '</tr>';
        }
        $str .= '</table>';
    }

    return $str;
}

/**
* prints a report over each connection session
*
*/
function training_reports_print_session_list($sessions, $courseid = 0){

    if (empty($sessions)){
        $str .= print_box(get_string('nosessions', 'report_trainingsessions'), 'generalbox', '', true);
        return $str;
    }

    if ($courseid) {
        $str = print_heading(get_string('coursesessions', 'report_trainingsessions'), 'center', 2, '', true);
    } else {
        $str = print_heading(get_string('sessions', 'report_trainingsessions'), 'center', 2, '', true);
    }

    // effective printing of available sessions
    $str .= '<table width="100%" id="session-table" class="generaltable">';
    $str .= '<tr valign="top" class="header">';
    $str .= '<td width="35%" class="header c0">'.get_string('sessionstart', 'report_trainingsessions').'</td>';
    $str .= '<td width="35%" class="header c1">'.get_string('sessionend', 'report_trainingsessions').'</td>';
    $str .= '<td width="15%" class="header c2">'.get_string('unpaddedduration', 'report_trainingsessions').'</td>';
    $str .= '<td width="15%" class="header c3">'.get_string('paddedduration', 'report_trainingsessions').'</td>';
    $str .= '</tr>';

    $totalelapsed = 0;

    foreach($sessions as $s){

        if ($courseid && ($s->course!=$courseid) ) continue; // omit all sessions not visiting this course
        $sessionenddate = userdate(@$s->sessionend);
        if ($s->closed==false){
            $sessionenddate = get_string('after', 'report_trainingsessions').' '.$sessionenddate;
        }
        $str .= '<tr valign="top">';
        $str .= '<td class="cell c0">'.userdate($s->sessionstart).'</td>';
        $str .= '<td class="cell c1">'.$sessionenddate.'</td>';
        $str .= '<td class="cell c2 right">'.format_duration($s->rawelapsed).'</td>';
        $str .= '<td class="cell c3 right">'.format_duration($s->elapsed).'</td>';
        $str .= '</tr>';
        $totalelapsed += $s->elapsed;
    }
    $str .= '<tr valign="top">';
    $str .= '<td><br/><b>'.get_string('totalsessions', 'report_trainingsessions').'</b></td>';
    $str .= '<td></td>';
    $str .= '<td></td>';
    $str .= '<td class="right"><br/>'.format_duration($totalelapsed).'</td>';
    $str .= '</tr>';

    $str .= '</table>';

    return $str;

}

function training_reports_print_total_site_html($dataobject){
    $str = '';

    $elapsedstr = get_string('elapsed', 'report_trainingsessions');
    $hitsstr = get_string('hits', 'report_trainingsessions');
    $str .= '<div class="attribute"><span class="attribute-name">'.$elapsedstr.'</span>';
    $str .= ' : ';
    $str .= '<span class="attribute-time">'.training_reports_format_time(0 + $dataobject->elapsed, 'html');
    $str .= ' ';
    $str .= helpbutton('totalsitetime', get_string('totalsitetime', 'report_trainingsessions'), 'report_trainingsessions', true, false, '', true);
    $str .= '</span></div>';

    $str .= '<div class="attribute"><span class="attribute-name">'.$hitsstr.'</span>';
    $str .= ' : ';
    $str .= '<span class="attribute-value">'.(0 + @$dataobject->events).'</span></div>';

    return $str;
}

function reports_print_pager($maxsize, $offset, $pagesize, $url, $contextparms){

    if (is_array($contextparms)){
        $parmsarr = array();
        foreach($contextparms as $key => $value){
            $parmsarr[] = "$key=".urlencode($value);
        }
        $contextparmsstr = implode('&', $parmsarr);
    } else {
        $contextparmsstr = $contextparms;
    }

    if (!empty($contextparmsstr)){
        if (strstr($url, '?') === false){
            $url = $url.'?';
        } else {
            $url = $url.'&';
        }
    }

    $str = '';
    for($i = 0; $i < $maxsize / $pagesize ; $i++){
        if ($offset == $pagesize * $i){
            $str .= ' <b>'.($i + 1).'</b> ';
        } else {
            $useroffset = $i * $pagesize;
            $str .= " <a href=\"{$url}{$contextparmsstr}&useroffset=$useroffset\">".($i + 1).'</a> ';
        }
    }
    return $str;
}

function training_reports_print_completionbar($items, $done, $width){

    global $CFG;
    $str = '';

    if (!empty($items)){
        $completed = $done / $items;
    } else {
        $completed = 0;
    }
    $remaining = 1 - $completed;
    $remainingitems = $items - $done;
    $completedpc = ceil($completed * 100)."% $done/$items";
    $remainingpc = floor(100 * $remaining)."% $remainingitems/$items";
    $completedwidth = floor($width * $completed);
    $remainingwidth = floor($width * $remaining);

    $str .= '<p class="completionbar">';
    $str .= '<b>'.get_string('done', 'report_trainingsessions').'</b>';

    $str .= "<img src=\"{$CFG->wwwroot}/course/report/trainingsessions/pix/green.gif\" style=\"width:{$completedwidth}px\" class=\"donebar\" align=\"top\" title=\"{$completedpc}\" />";
    $str .= "<img src=\"{$CFG->wwwroot}/course/report/trainingsessions/pix/blue.gif\" style=\"width:{$remainingwidth}px\" class=\"remainingbar\" align=\"top\"  title=\"{$remainingpc}\" />";

    return $str;
}
