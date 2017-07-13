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
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function report_trainingsessions_print_allcourses_html(&$str, &$aggregate) {
    global $CFG, $COURSE, $OUTPUT, $DB;

    $config = get_config('report_trainingsessions');
    if (!empty($config->showseconds)) {
        $durationformat = 'htmlds';
    } else {
        $durationformat = 'htmld';
    }

    $output = array();
    $courses = array();
    $courseids = array();
    $return = new StdClass;
    $return->elapsed = 0;
    $return->events = 0;
    $catids = array();

    if (!empty($aggregate['coursetotal'])) {
        foreach ($aggregate['coursetotal'] as $cid => $cdata) {
            if ($cid != 0) {
                if (!in_array($cid, $courseids)) {
                    $fields = 'id, idnumber, shortname, fullname, category';
                    $courses[$cid] = $DB->get_record('course', array('id' => $cid), $fields);
                    $courseids[$cid] = '';
                }
                @$output[$courses[$cid]->category][$cid] = $cdata;
                // If courses have been deleted, this may lead to a category '0'.
                $catids[0 + @$courses[$cid]->category] = '';
            } else {
                if (!isset($output[0][SITEID])) {
                    $output[0][SITEID] = new StdClass();
                }
                $output[0][SITEID]->elapsed = @$output[0][SITEID]->elapsed + $cdata->elapsed;
                $output[0][SITEID]->events = @$output[0][SITEID]->events + $cdata->events;
            }
            $return->elapsed += $cdata->elapsed;
            $return->events += $cdata->events;
        }

        $coursecats = $DB->get_records_list('course_categories', 'id', array_keys($catids));
    }

    if (!empty($output)) {
        $elapsedstr = get_string('elapsed', 'report_trainingsessions');
        $hitsstr = get_string('hits', 'report_trainingsessions');
        $coursestr = get_string('course');

        if (isset($output[0])) {
            $str .= '<h2>'.get_string('site').'</h2>';
            $str .= $elapsedstr.' : '.report_trainingsessions_format_time($output[0][SITEID]->elapsed, $durationformat).'<br/>';
            $str .= $hitsstr.' : '.$output[0][SITEID]->events;
        }

        foreach ($output as $catid => $catdata) {
            if ($catid == 0) {
                continue;
            }
            $str .= '<h2>'.strip_tags(format_string($coursecats[$catid]->name)).'</h2>';
            $str .= '<table class="generaltable" style="width:100%">';
            $str .= '<tr class="header">';
            $str .= '<td class="header c0" style="width:70%"><b>'.$coursestr.'</b></td>';
            $str .= '<td class="header c1" style="width:15%"><b>'.$elapsedstr.'</b></td>';
            $str .= '<td class="header c2" style="width:15%"><b>'.$hitsstr.'</b></td>';
            $str .= '</tr>';
            foreach ($catdata as $cid => $cdata) {
                $ccontext = context_course::instance($cid);
                if (has_capability('report/trainingsessions:view', $ccontext)) {
                    $str .= '<tr valign="top">';
                    $str .= '<td>'.format_string($courses[$cid]->fullname).'</td>';
                    $str .= '<td>';
                    $str .= report_trainingsessions_format_time($cdata->elapsed, $durationformat).'<br/>';
                    $str .= '</td>';
                    $str .= '<td>'.$cdata->events.'</td>';
                    $str .= '</tr>';
                } else {
                    $str .= '<tr valign="top">';
                    $str .= '<td>'.format_string($courses[$cid]->fullname).'</td>';
                    $str .= '<td colspan="2">';
                    $str .= get_string('nopermissiontoview', 'report_trainingsessions');
                    $str .= '</td>';
                    $str .= '</tr>';
                }
            }
            $str .= '</table>';
        }
    } else {
        $str .= $OUTPUT->box(get_string('nodata', 'report_trainingsessions'));
    }

    return $return;
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function report_trainingsessions_print_html(&$str, $structure, &$aggregate, &$done, $indent = '', $level = 0) {
    global $OUTPUT;
    static $titled = false;

    $usconfig = get_config('use_stats');

    $config = get_config('report_trainingsessions');

    if (!empty($config->showseconds)) {
        $durationformat = 'htmlds';
    } else {
        $durationformat = 'htmld';
    }

    if (isset($usconfig->ignoremodules)) {
        $ignoremodulelist = explode(',', $usconfig->ignoremodules);
    } else {
        $ignoremodulelist = array();
    }

    if (empty($structure)) {
        $str .= get_string('nostructure', 'report_trainingsessions');
        return new StdClass;
    }

    if (!$titled) {
        $titled = true;
        $str .= $OUTPUT->heading(get_string('instructure', 'report_trainingsessions'));

        // Effective printing of available sessions.
        $str .= '<table style="width:100%" id="structure-table">';
        $str .= '<tr valign="top">';
        $str .= '<th class="userreport-col0" style="width:40%;"><b>'.get_string('structureitem', 'report_trainingsessions').'</b></th>';
        $str .= '<th class="userreport-col1" align="center" style="width:20%;"><b>'.get_string('firstaccess', 'report_trainingsessions').'</b></th>';
        $str .= '<th class="userreport-col2" align="center" style="width:20%;"><b>'.get_string('lastaccess', 'report_trainingsessions').'</b></th>';
        $label = get_string('duration', 'report_trainingsessions');
        if(is_siteadmin()) {
            $label .= ' ('.get_string('hits', 'report_trainingsessions').')';
        }
        $str .= '<th class="userreport-col3" align="right" style="width:20%; padding-right:2px;"><b>'.$label.'</b></th>';
        $str .= '</tr>';
    }

    $indent = str_repeat('&nbsp;&nbsp;', $level);
    $suboutput = '';

    // Initiates a blank dataobject.
    if (!isset($dataobject)) {
        $dataobject = new StdClass;
        $dataobject->elapsed = 0;
        $dataobject->events = 0;
    }

    if (is_array($structure)) {
        // If an array of elements produce sucessively each output and collect aggregates.
        foreach ($structure as $element) {
            if (isset($element->instance) && empty($element->instance->visible)) {
                // Non visible items should not be displayed.
                continue;
            }
            $level++;
            $res = report_trainingsessions_print_html($str, $element, $aggregate, $done, $indent, $level);
            $level--;
            $dataobject->elapsed += $res->elapsed;
            $dataobject->events += (0 + @$res->events);
        }
    } else {
        $nodestr = '';
        if (!isset($structure->instance) || !empty($structure->instance->visible)) {
            // Non visible items should not be displayed.
            // Name is not empty. It is a significant module (non structural).
            if (!empty($structure->name)) {
                $nodestr .= '<tr class="sessionlevel'.$level.' userreport-col0">';
                $nodestr .= '<td class="sessionitem item" style="width:40%;">';
                $nodestr .= $indent;
                if (debugging()) {
                    $nodestr .= '['.$structure->type.'] ';
                }
                $nodestr .= shorten_text(strip_tags(format_string($structure->name)), 85);
                $nodestr .= '</td>';
                $nodestr .= '<td class="sessionitem rangedate userreport-col1" align="center" style="background-color: #d0d0d0; font-size:0.9em; border:1px solid #a0a0a0; width:20%;">';
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $nodestr .= date('Y/m/d H:i', 0 + (@$aggregate[$structure->type][$structure->id]->firstaccess));
                }
                $nodestr .= '</td>';
                $nodestr .= '<td class="sessionitem rangedate userreport-col2" align="center" style="background-color: #d0d0d0; font-size:0.9em; border:1px solid #a0a0a0; width:20%;">';
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $nodestr .= date('Y/m/d H:i', 0 + (@$aggregate[$structure->type][$structure->id]->lastaccess));
                }
                $nodestr .= '</td>';
                $nodestr .= '<td class="reportvalue rangedate userreport-col3" align="right" style="font-size:0.9em; border:1px solid #a0a0a0; width:20%; padding-right:2px;">';
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $done++;
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $res = report_trainingsessions_print_html($suboutput, $structure->subs, $aggregate, $done, $indent, $level + 1);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }

                if (!in_array($structure->type, $ignoremodulelist)) {
                    if (!empty($dataobject->timesource) && $dataobject->timesource == 'credit' && $dataobject->elapsed) {
                        $nodestr .= get_string('credittime', 'block_use_stats');
                    }
                    if (!empty($dataobject->timesource) && $dataobject->timesource == 'declared' && $dataobject->elapsed) {
                        $nodestr .= get_string('declaredtime', 'block_use_stats');
                    }
                    $nodestr .= report_trainingsessions_format_time($dataobject->elapsed, $durationformat);
                    if (is_siteadmin()) {
                        $nodestr .= ' ('.(0 + @$dataobject->events).' sessions)';
                    }
                } else {
                    $nodestr .= get_string('ignored', 'block_use_stats');
                }
                // Plug here specific details.
                $nodestr .= '</td>';
                $nodestr .= '</tr>';
            } else {
                // It is only a structural module that should not impact on level.
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $res = report_trainingsessions_print_html($suboutput, $structure->subs, $aggregate, $done, $indent, $level);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }
            }

            $str .= $nodestr;
            if (!empty($structure->subs)) $str .= $suboutput;
        }
    }
    if($level<1) {
        $str .= '</table>';
    }
    return $dataobject;
}

/**
 * a raster for html printing of a report structure header
 * with all the relevant data about a user.
 */
function report_trainingsessions_print_header_html($userid, $courseid, $data, $short = false, $withcompletion = true,
                                                   $withnooutofstructure = false) {
    global $DB, $OUTPUT;

    $config = get_config('report_trainingsessions');

    if (!empty($config->showseconds)) {
        $durationformat = 'htmlds';
    } else {
        $durationformat = 'htmld';
    }

    // Ask config for enabled info.
    $cols = report_trainingsessions_get_summary_cols();


    $user = $DB->get_record('user', array('id' => $userid));
    $course = $DB->get_record('course', array('id' => $courseid));

    $str = '<div class="center">';
    $str .= '<div class="report-trainingsession userinfobox" style="width:95%;text-align:left;padding:10px;background-color:white;border:1px #d0d0d0 solid;border-radius:10px;font-size:1.2em;margin-bottom:10px;">';

    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');
    $str .= '<h1>';
    $picture = $OUTPUT->user_picture($user, array('size' => 100, 'courseid' => $course->id));
    $matches = array();
    if(preg_match('/<img.*\/>/', $picture, $matches)) {
        $str .= $matches[0];
    }
    $str .= '&nbsp;&nbsp;&nbsp;'.fullname($user).'</h1>';

    // Print group status.
    if (!empty($usergroups)) {
        $str .= '<b>'.get_string('groups');
        $str .= ' : </b>';
        foreach ($usergroups as $group) {
            $strbuf = $group->name;
            if ($group->id == groups_get_course_group($course)) {
                $strbuf = "<b>$strbuf</b>";
            }
            $groupnames[] = format_string($strbuf);
        }
        $str .= implode(', ', $groupnames);
    }

    // Print IDNumber.
    if (preg_match('/\d{7}/', $user->username)) {
        $str .= '<div class="attribute"><b>'.get_string('idnumber');
        $str .= ' : </b>';
        $str .= '<span class="attribute-value">'.$user->username.'</span></div>';
    }

    // Print Institution.
    if (in_array('institution', $cols)) {
        $str .= '<div class="attribute"><b>'.get_string('institution').'</b>';
        $str .= ' : ';
        $str .= '<span class="attribute-value">'.$user->institution.'</span></div>';
    }

    // Print Department.
    if (in_array('department', $cols)) {
        $str .= '<div class="attribute"><b>'.get_string('department').'</b>';
        $str .= ' : ';
        $str .= '<span class="attribute-value">'.$user->department.'</span></div>';
    }

    /*// Print roles list.
    $context = context_course::instance($courseid);
    $roles = role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL);
    $str .= '<br/><b>'.get_string('roles').' : </b>';
    $userroles = get_user_roles($context, $userid);
    $uroles = array();

    foreach ($userroles as $rid => $r) {
        $uroles[] = $roles[$r->roleid]->localname;
    }
    $str .= implode (",", $uroles);
    $str .= '<br/>';

    if (!empty($data->linktousersheet)) {
        $params = array('view' => 'user',
                        'id' => $courseid,
                        'userid' => $userid,
                        'from' => $data->from,
                        'to' => $data->to);
        $detailurl = new moodle_url('/report/trainingsessions/index.php', $params);
        //$str .= '<br/><a href="'.$detailurl.'">'.get_string('seedetails', 'report_trainingsessions').'</a>';
    }*/

    if ($withcompletion) {
        // Print completion bar.
        $str .= report_trainingsessions_print_completionbar(0 + @$data->items, 0 + @$data->done, 500);
    }

    // Start printing the overall times.
    $str .= '<div id="report-trainingsessions-totalisers">';

    if (in_array('activitytime', $cols)) {
        $str .= '<br/><b>';
        $str .= get_string('activitytime', 'report_trainingsessions');
        $str .= ' : </b>'.report_trainingsessions_format_time(0 + @$data->activitytime, $durationformat);
        if (is_siteadmin()) {
            $str .= ' ('.(0 + @$data->activityevents).' sessions)';
        }
    }

    if (!$short) {
        if (in_array('othertime', $cols)) {
            $str .= '<br/><b>';
            $str .= get_string('othertime', 'report_trainingsessions');
            $str .= ' : </b>'.report_trainingsessions_format_time(0 + @$data->othertime, $durationformat);
            if (is_siteadmin()) {
                $str .= ' ('.(0 + @$data->otherevents).' sessions)';
            }
        }

        if (in_array('coursetime', $cols)) {
            $str .= '<br/><b>';
            $str .= get_string('coursetime', 'report_trainingsessions');
            $str .= ' : </b>'.report_trainingsessions_format_time(0 + @$data->coursetime, $durationformat);
            if (is_siteadmin()) {
                $str .= ' ('.(0 + @$data->courseevents).' sessions)';
            }
        }

        if (in_array('elapsed', $cols)) {
            $str .= '<hr><br/><span class="trainingsessions-main-total"><b>';
            $str .= get_string('coursetotaltime', 'report_trainingsessions');
            $str .= ' : </b>'.report_trainingsessions_format_time(0 + @$data->elapsed, $durationformat);
            if (is_siteadmin()) {
                $str .= ' ('.(0 + @$data->hits).' sessions)';
            }
            $str .= '</span>';
        }

        if (in_array('extelapsed', $cols)) {
            $str .= '<br/><b>';
            $str .= get_string('extelapsed', 'report_trainingsessions');
            $str .= ' : </b>'.report_trainingsessions_format_time(0 + @$data->extelapsed, $durationformat);
            if (is_siteadmin()) {
                $str .= ' ('.(0 + @$data->exthits).' sessions)';
            }
        }

        if (in_array('extother', $cols)) {
            $str .= '<br/><b>';
            $str .= get_string('extother', 'report_trainingsessions');
            $str .= ' : </b>'.report_trainingsessions_format_time(0 + @$data->extother, $durationformat);
        }

        if (in_array('elapsedlastweek', $cols)) {
            $str .= '<hr><br/><b>';
            $str .= get_string('elapsedlastweek', 'report_trainingsessions');
            $str .= ' : </b>'.report_trainingsessions_format_time(0 + @$data->elapsedlastweek, $durationformat);
            if (is_siteadmin()) {
                $str .= ' ('.(0 + @$data->hitslastweek).' sessions)';
            }
        }

        if (in_array('extelapsedlastweek', $cols)) {
            $str .= '<br/><b>';
            $str .= get_string('extelapsedlastweek', 'report_trainingsessions');
            $str .= ' : </b>'.report_trainingsessions_format_time(0 + @$data->extelapsedlastweek, $durationformat);
            if (is_siteadmin()) {
                $str .= ' ('.(0 + @$data->exthitslastweek).' sessions)';
            }
        }

        if (in_array('extotherlastweek', $cols)) {
            $str .= '<br/><b>';
            $str .= get_string('extotherlastweek', 'report_trainingsessions');
            $str .= ' : </b>'.report_trainingsessions_format_time(0 + @$data->extotherlastweek, $durationformat);
        }

    }
    $str .= '<br/>';

    if (in_array('workingsessions', $cols)) {
        $str .= '<b>'.get_string('workingsessions', 'report_trainingsessions');
        $str .= ' : </b>'.(0 + @$data->sessions);
    }

    $str .= '</div></div>';

    // Add printing for global course time (out of activities).
    if (!$short) {
        if (!$withnooutofstructure) {
            $str .= $OUTPUT->heading(get_string('outofstructure', 'report_trainingsessions'));
            $str .= '<table cellspacing="0" cellpadding="0" style="width:100%" class="sessionreport">';
            $str .= '<tr class="sessionlevel2" valign="top">';
            $str .= '<td class="sessionitem" style="width:50%;">';
            $str .= get_string('courseglobals', 'report_trainingsessions');
            $str .= '</td>';
            $str .= '<td class="sessionvalue report-trainingsessions session-duration" style="width:50%; text-align:right;">';
            $str .= report_trainingsessions_format_time(0 + @$data->elapsed - @$data->activitytime, $durationformat);
            if (is_siteadmin()) {
                $str .= ' ('.(0 + @$data->courseevents + @$data->otherevents).' sessions)';
            }
            $str .= '</td>';
            $str .= '</tr>';
        }
        if (isset($data->upload)) {
            $str .= '<tr class="sessionlevel2" valign="top">';
            $str .= '<td class="sessionitem">';
            $str .= get_string('uploadglobals', 'report_trainingsessions');
            $str .= '</td>';
            $str .= '<td class="sessionvalue report-trainingsessions session-duration">';
            $str .= report_trainingsessions_format_time(0 + @$data->upload->elapsed, $durationformat);
            if (is_siteadmin()) {
                $str .= ' ('.(0 + @$data->upload->events).' sessions)';
            }
            $str .= '</td>';
            $str .= '</tr>';
        }
        $str .= '</table>';
    }

    $str .= '</div>';

    return $str;
}

/**
 * prints a report over each connection session
 *
 */
function report_trainingsessions_print_session_list(&$str, $sessions, $courseid = 0, $userid = 0) {
    global $OUTPUT, $CFG;

    $config = get_config('report_trainingsessions');

    if (!empty($config->showseconds)) {
        $durationformat = 'htmlds';
    } else {
        $durationformat = 'htmld';
    }

    if ($courseid) {
        // Filter sessions that are not in the required course.
        foreach ($sessions as $sessid => $session) {
            if (!empty($session->courses)) {
                if (!array_key_exists($courseid, $session->courses)) {
                    // Omit all sessions not visiting this course.
                    unset($sessions[$sessid]);
                }
            } else {
                unset($sessions[$sessid]);
            }
        }
    }

    $config = get_config('report_trainingsessions');
    if (!empty($config->enablelearningtimecheckcoupling)) {
        if (file_exists($CFG->dirroot.'/report/learningtimecheck/lib.php')) {
            require_once($CFG->dirroot.'/report/learningtimecheck/lib.php');
            $ltcconfig = get_config('report_learningtimecheck');
        }
    }

    $sessionsstr = ($courseid) ? get_string('coursesessions', 'report_trainingsessions') : get_string('sessions', 'report_trainingsessions');
    $str .= $OUTPUT->heading($sessionsstr, 2);
    if (empty($sessions)) {
        $str .= $OUTPUT->box(get_string('nosessions', 'report_trainingsessions'));
        return;
    }

    $str .= '<br/><p>'.get_string('elapsedadvice', 'report_trainingsessions').'</p>';

    // Effective printing of available sessions.
    $str .= '<table style="width:70%" id="session-table">';
    $str .= '<tr valign="top">';
    $str .= '<td style="width:33%"><b>'.get_string('sessionstart', 'report_trainingsessions').'</b></td>';
    $str .= '<td style="width:33%"><b>'.get_string('sessionend', 'report_trainingsessions').'</b></td>';
    $label = get_string('duration', 'report_trainingsessions');
    $str .= '<td style="width:33%" class="report-trainingsessions session-duration"><b>'.$label.'</b></td>';
    $str .= '</tr>';

    $totalelapsed = 0;
    $induration = 0;
    $outduration = 0;
    $truesessions = 0;

    foreach ($sessions as $session) {

        if (empty($session->courses)) {
            // This is not a true working session.
            continue;
        }

        if (!isset($session->sessionend) && empty($session->elapsed)) {
            // This is a "not true" session reliquate. Ignore it.
            continue;
        }

        // Fix all incoming sessions. possibly cropped by threshold effect.
        $session->sessionend = $session->sessionstart + $session->elapsed;

        $daysessions = report_trainingsessions_splice_session($session);

        $truesessions++;

        foreach ($daysessions as $s) {

            if (!isset($s->sessionstart)) {
                continue;
            }

            $startstyle = '';
            $endstyle = '';
            $checkstyle = '';
            if (!empty($config->enablelearningtimecheckcoupling)) {

                if (!empty($ltcconfig->checkworkingdays) || !empty($ltcconfig->checkworkinghours)) {

                    // Always mark in html rendering.
                    // Start check :
                    $fakecheck = new StdClass();
                    $fakecheck->usertimestamp = $s->sessionstart;
                    $fakecheck->userid = $userid;

                    $outtime = false;
                    if (!empty($ltcconfig->checkworkingdays) && !report_learningtimecheck_is_valid($fakecheck)) {
                        $startstyle = 'style="color:#A0A0A0"';
                        $endstyle = 'style="color:#A0A0A0"';
                        $checkstyle = 'style="color:#A0A0A0"';
                        $outtime = true;
                        if ($outtime) {
                            $outduration += $s->elapsed;
                        }
                        if (!$outtime) {
                            $induration += $s->elapsed;
                        }
                    } else {
                        if (!empty($ltcconfig->checkworkinghours)) {
                            if (!$startcheck = report_learningtimecheck_check_time($fakecheck, $ltcconfig)) {
                                $startstyle = 'style="color:#ff0000"';
                            }

                            // End check :
                            $fakecheck = new StdClass();
                            $fakecheck->userid = $userid;
                            $fakecheck->usertimestamp = $s->sessionend;
                            if (!$endcheck = report_learningtimecheck_check_time($fakecheck, $ltcconfig)) {
                                $endstyle = 'style="color:#ff0000"';
                            }

                            if (!$startcheck && !$endcheck) {
                                $startstyle = 'style="color:#ff0000"';
                                $startstyle = 'style="color:#ff0000"';
                                $checkstyle = 'style="color:#ff0000"';
                                $outtime = true;
                            }
                            if ($outtime) {
                                $outduration += $s->elapsed;
                            }
                            if (!$outtime) {
                                $induration += $s->elapsed;
                            }
                        }
                    }
                }
            }

            $sessionenddate = (isset($s->sessionend)) ? userdate(@$s->sessionend) : '';
            $str .= '<tr valign="top">';
            $str .= '<td '.$startstyle.'>'.userdate($s->sessionstart).'</td>';
            $str .= '<td '.$endstyle.'>'.$sessionenddate.'</td>';
            $elps = report_trainingsessions_format_time(@$s->elapsed, $durationformat);
            $str .= '<td class="report-trainingsessions session-duration" '.$checkstyle.'>'.$elps.'</td>';
            $str .= '</tr>';
            $totalelapsed += @$s->elapsed;
        }
    }

    if (!empty($config->printsessiontotal)) {
        $str .= '<tr valign="top">';
        $str .= '<td><br/><b>'.get_string('totalsessions', 'report_trainingsessions').'</b></td>';
        $str .= '<td><br/>'.$truesessions.' '.get_string('contiguoussessions', 'report_trainingsessions').'</td>';
        $str .= '<td><br/>'.report_trainingsessions_format_time($totalelapsed, $durationformat).'</td>';
        $str .= '</tr>';

        if (!empty($config->enablelearningtimecheckcoupling) &&
                (!empty($ltcconfig->checkworkingdays) ||
                        !empty($ltcconfig->checkworkinghours))) {
            $str .= '<tr valign="top">';
            $str .= '<td><br/><b>'.get_string('in', 'report_trainingsessions').'</b></td>';
            $str .= '<td></td>';
            $str .= '<td><br/>'.report_trainingsessions_format_time($induration, $durationformat).'</td>';
            $str .= '</tr>';

            $str .= '<tr valign="top">';
            $str .= '<td><br/><b>'.get_string('out', 'report_trainingsessions').'</b></td>';
            $str .= '<td></td>';
            $str .= '<td style="color:#ff0000"><br/>'.report_trainingsessions_format_time($outduration, $durationformat).'</td>';
            $str .= '</tr>';
        }
    }

    $str .= '</table>';
}

function report_trainingsessions_print_total_site_html($dataobject) {
    global $OUTPUT;

    $config = get_config('report_trainingsessions');

    if (!empty($config->showseconds)) {
        $durationformat = 'htmlds';
    } else {
        $durationformat = 'htmld';
    }

    $str = '';

    $elapsedstr = get_string('elapsed', 'report_trainingsessions');
    $hitsstr = get_string('hits', 'report_trainingsessions');
    $str .= '<br/>';
    $str .= '<b>'.$elapsedstr.' : </b>';
    $str .= report_trainingsessions_format_time(0 + $dataobject->elapsed, $durationformat);
    $str .= '<br/>';
    $str .= '<b>'.$hitsstr.' : </b>';
    $str .= 0 + @$dataobject->events;

    return $str;
}

function reports_print_pager($maxsize, $offset, $pagesize, $url, $contextparms) {

    if (is_array($contextparms)) {
        $parmsarr = array();
        foreach ($contextparms as $key => $value) {
            $parmsarr[] = "$key=".urlencode($value);
        }
        $contextparmsstr = implode('&', $parmsarr);
    } else {
        $contextparmsstr = $contextparms;
    }

    if (!empty($contextparmsstr)) {
        if (strstr($url, '?') === false) {
            $url = $url.'?';
        } else {
            $url = $url.'&';
        }
    }

    $str = '';
    for ($i = 0; $i < $maxsize / $pagesize; $i++) {
        if ($offset == $pagesize * $i) {
            $str .= ' <b>'.($i + 1).'</b> ';
        } else {
            $useroffset = $i * $pagesize;
            $str .= ' <a href="'.$url.$contextparmsstr.'&useroffset='.$useroffset.'">'.($i + 1).'</a> ';
        }
    }
    return $str;
}

function report_trainingsessions_print_completionbar($items, $done, $width) {
    global $CFG, $OUTPUT;

    $str = '';

    $str .= '<div class="completionbar">';
    $str .= '<b>'.get_string('done', 'report_trainingsessions').' : </b>';

    if(empty($items)) {
        $str .= 'Pas d\'activités';
    } else {
        $completionpercent = intval($done/$items*100);
        $str .= $completionpercent.'% ('.$done.'/'.$items.')';
    }
    $str .= '</div>';

    return $str;
}

/***
 * Print the grades
 */
function report_trainingsessions_print_grades($grades) {
    $str = '';
    $gradecols = array();
    $gradetitles = array();
    $gradeformats = array();
    report_trainingsessions_add_graded_columns($gradecols, $gradetitles, $gradeformats);

    // Print grades if they exist.
    if (!empty($gradecols)) {
        $str .= '<nobreak>';
        $str .= '<br/><h2>'.get_string('grades').'</h2>';
        $str .= '<table id="structure-table" style="width:100%;"><tbody>';
        $str .= '<tr valign="top">';
        $str .= '<th style="width:40%; padding-left:2px;"><b>'.get_string('structureitem', 'report_trainingsessions').'</b></th>';
        $str .= '<th style="width:20%; padding-left:2px;"><b>'.get_string('grade').'</b></th>';
        $str .= '<th style="width:20%; padding-left:2px;"><b>'.get_string('maxgrade', 'grades').'</b></th>';
        $str .= '<th style="width:20%; padding-left:2px;"><b>'.get_string('date').'</b></th>';
        $str .= '</tr>';
        $i = 0;
        foreach($gradecols as $gc) {
            $str .= '<tr>';
            $str .= '<td style="width:40%; font-size:0.9em; border:1px solid #a0a0a0; padding-left:2px;">'.$gradetitles[$i].'</td>';
            if($grades[$i]->grade==null) {
                $str .= '<td style="width:20%; background-color: #d0d0d0; font-size:0.9em; border:1px solid #a0a0a0; padding-left:2px;">'.get_string('noextragrade', 'report_trainingsessions').'</td>';
                $str .= '<td style="width:20%; font-size:0.9em; border:1px solid #a0a0a0; padding-left:2px;">'.sprintf('%0.2f', $grades[$i]->grademax).'</td>';
                $str .= '<td style="width:20%; font-size:0.9em; border:1px solid #a0a0a0; padding-left:2px;">'.get_string('noextragrade', 'report_trainingsessions').'</td>';
            } else {
                $str .= '<td style="width:20%; background-color: #d0d0d0; font-size:0.9em; border:1px solid #a0a0a0; padding-left:2px;">'.sprintf('%0.2f', $grades[$i]->grade).'</td>';
                $str .= '<td style="width:20%; font-size:0.9em; border:1px solid #a0a0a0; padding-left:2px;">'.sprintf('%0.2f', $grades[$i]->grademax).'</td>';
                $str .= '<td style="width:20%; font-size:0.9em; border:1px solid #a0a0a0; padding-left:2px;">'.userdate($grades[$i]->timemodified).'</td>';
            }
            $str .= '</tr>';
            $i++;
        }
        $str .= '</tbody></table>';
        $str .= '</nobreak>';
    }
    return $str;
}
