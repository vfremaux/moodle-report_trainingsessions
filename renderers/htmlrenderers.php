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
function report_trainingsessions_print_allcourses_html(&$aggregate, &$return) {
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

    $template = new StdClass;

    $systemcontext = context_system::instance();
    $template->isadmin = has_capability('moodle/site:config', $systemcontext);

    $template->hasoutput = false;
    if (!empty($output)) {
        $template->hasoutput = true;

        if (isset($output[0])) {
            $template->siteelapsed = report_trainingsessions_format_time($output[0][SITEID]->elapsed, $durationformat);
            $template->siteevents = $output[0][SITEID]->events;
        }

        foreach ($output as $catid => $catdata) {
            if ($catid == 0) {
                continue;
            }
            $categorytpl = new StdClass;
            $categorytpl->categoryname = strip_tags(format_string($coursecats[$catid]->name));

            foreach ($catdata as $cid => $cdata) {
                $catlinetpl = new StdClass;
                $catlinetpl->coursename = format_string($courses[$cid]->fullname);
                $ccontext = context_course::instance($cid);
                if (has_capability('report/trainingsessions:view', $ccontext)) {
                    $catlinetpl->canview = true;
                    $catlinetpl->elapsed = report_trainingsessions_format_time($cdata->elapsed, $durationformat).'<br/>';
                    $catlinetpl->events = $cdata->events;
                } else {
                    $catlinetpl->canview = false;
                }
                $categorytpl->catlines[] = $catlinetpl;
            }
            $template->categories[] = $categorytpl;
        }
    } else {
        $template->hasoutput = false;
        $template->nodatanotification = $OUTPUT->notification(get_string('nodata', 'report_trainingsessions'));
    }

    return $OUTPUT->render_from_template('report_trainingsessions/allcourses', $template);
}

/**
 * a raster for html printing of a report structure.
 *
 * @param string ref $str a buffer for accumulating output
 * @param object $structure a course structure object.
 */
function report_trainingsessions_print_html($structure, &$aggregate, &$dataobject, &$done, $indent = '', $level = 0) {
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

    $template = new StdClass;
    $template->level = $level;
    $template->hassubs = false;
    if (is_siteadmin()) {
        $template->isadmin = true;
    }

    if (empty($structure)) {
        $template->hasstructure = false;
        return $OUTPUT->render_from_template('report_trainingsessions/structure', $template);
    }

    $template->hasstructure = true;

    $template->withtitle = false;
    if (!$titled) {
        $titled = true;
        $template->withtitle = true;
        $template->heading = $OUTPUT->heading(get_string('instructure', 'report_trainingsessions'));
    }

    $template->indent = str_repeat('&nbsp;&nbsp;', $level);

    // Initiates a blank dataobject.
    if (!isset($dataobject) || is_null($dataobject)) {
        $dataobject = new StdClass;
        $dataobject->elapsed = 0;
        $dataobject->events = 0;
    }

    if (is_array($structure)) {
        // If an array of elements produce successively each output and collect aggregates.
        $template->hassubs = true;
        foreach ($structure as $element) {
            if (isset($element->instance) && empty($element->instance->visible)) {
                // Non visible items should not be displayed.
                continue;
            }
            $subdataobject = null;
            $template->structures[] = report_trainingsessions_print_html($element, $aggregate, $subdataobject, $done, $indent, $level + 1);
            $dataobject->elapsed += $subdataobject->elapsed;
            $dataobject->events += (0 + @$subdataobject->events);
        }
    } else {
        $template->id = $structure->id;
        $template->hasbody = true;
        if (!isset($structure->instance) || !empty($structure->instance->visible)) {
            // Non visible items should not be displayed.
            // Name is not empty. It is a significant module (non structural).
            $template->type = $structure->type;
            $template->issection = false;
            if ($structure->type == 'section') {
                $template->issection = true;
            }
            if (!empty($structure->name)) {
                if (debugging()) {
                    $template->debuginfo = '['.$structure->type.'] ';
                }
                $template->name = shorten_text(strip_tags(format_string($structure->name)), 85);
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $fa = 0 + (@$aggregate[$structure->type][$structure->id]->firstaccess);
                    if ($fa) {
                        $template->firstaccess = date('Y/m/d H:i', $fa);
                    } else {
                        $template->firstaccess = '--';
                    }
                }
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $la = 0 + (@$aggregate[$structure->type][$structure->id]->lastaccess);
                    if ($la) {
                        $template->lastaccess = date('Y/m/d H:i', $la);
                    } else {
                        $template->lastaccess = '--';
                    }
                }
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $done++;
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $template->hassubs = true;
                    $subdataobject = null;
                    $template->structures[] = report_trainingsessions_print_html($structure->subs, $aggregate, $subdataobject, $done, $indent, $level + 1);
                }

                if (!in_array($structure->type, $ignoremodulelist)) {
                    if (!empty($dataobject->timesource) && $dataobject->timesource == 'credit' && $dataobject->elapsed) {
                        $template->source = get_string('credittime', 'block_use_stats');
                    }
                    if (!empty($dataobject->timesource) && $dataobject->timesource == 'declared' && $dataobject->elapsed) {
                        $template->source = get_string('declaredtime', 'block_use_stats');
                    }
                    $template->elapsed = report_trainingsessions_format_time($dataobject->elapsed, $durationformat);
                    if (!empty($dataobject->real)) {
                        $template->real = report_trainingsessions_format_time($dataobject->real, $durationformat);
                    } else if (!empty($dataobject->credit)) {
                        $template->credit = report_trainingsessions_format_time($dataobject->credit, $durationformat);
                    }
                    if (is_siteadmin()) {
                        $template->events = ' ('.(0 + @$dataobject->events).')';
                    }
                } else {
                    $template->source = get_string('ignored', 'block_use_stats');
                }

            } else {
                // It is only a structural module that should not impact on level.
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $template->hassubs = true;
                    $subdataobject = null;
                    $template->structures[] = report_trainingsessions_print_html($structure->subs, $aggregate, $subdataobject, $done, $indent, $level + 1);
                    $dataobject->elapsed += $subdataobject->elapsed;
                    $dataobject->events += $subdataobject->events;
                }
            }
        }
    }
    if ($level == 0) {
        return $OUTPUT->render_from_template('report_trainingsessions/structure', $template);
    }

    return $template;
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
    $gradecols = array();
    $gradetitles = array();
    $gradeformats = array();
    report_trainingsessions_add_graded_columns($gradecols, $gradetitles, $gradeformats);

    $user = $DB->get_record('user', array('id' => $userid));
    $course = $DB->get_record('course', array('id' => $courseid));

    $template = new StdClass;
    $template->short = $short;
    $template->isadmin = is_siteadmin();

    $template->userpicture = $OUTPUT->user_picture($user, array('size' => 32, 'courseid' => $course->id));
    $template->fullname = fullname($user);

    // Print group status.
    $groupnames = report_trainingsessions_get_user_groups($userid, $courseid);
    if (!empty($groupnames)) {
        $template->groupnames = $groupnames;
        $template->hasgroups = true;
    }

    // Print IDNumber.
    if (in_array('idnumber', $cols)) {
        $template->idnumber = $user->idnumber;
    }

    // Print Institution.
    if (in_array('institution', $cols)) {
        $template->institution = $user->institution;
    }

    // Print Department.
    if (in_array('department', $cols)) {
        $template->department . $user->department;
    }

    // Print roles list.
    $context = context_course::instance($courseid);
    $roles = role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL);
    $userroles = get_user_roles($context, $userid);
    $uroles = array();

    foreach ($userroles as $rid => $r) {
        $uroles[] = $roles[$r->roleid]->localname;
    }
    $template->roles = implode (",", $uroles);

    if (!empty($data->linktousersheet)) {
        $params = array('view' => 'user',
                        'id' => $courseid,
                        'userid' => $userid,
                        'from' => $data->from,
                        'to' => $data->to);
        $template->hasdetails = true;
        $template->detailurl = new moodle_url('/report/trainingsessions/index.php', $params);
    }

    if ($withcompletion) {
        $template->withcompletion = true;
        // Print completion bar.
        $template->completionbar = report_trainingsessions_print_completionbar(0 + @$data->items, 0 + @$data->done, 500);
    }

    if (!$short) {
        if (in_array('activitytime', $cols)) {
            $template->activitytime = report_trainingsessions_format_time(0 + @$data->activitytime, $durationformat);
            $template->activityevents = ' ('.(0 + @$data->activityevents).')';
            $template->activitytimehelp = $OUTPUT->help_icon('activitytime', 'report_trainingsessions');
        }

        if (in_array('othertime', $cols)) {
            $template->othertime = report_trainingsessions_format_time(0 + @$data->othertime, $durationformat);
            $template->otherevents = ' ('.(0 + @$data->otherevents).')';
            $template->othertimehelp = $OUTPUT->help_icon('othertime', 'report_trainingsessions');
        }

        if (in_array('coursetime', $cols)) {
            $template->coursetime = report_trainingsessions_format_time(0 + @$data->coursetime, $durationformat);
            $template->courseevents = ' ('.(0 + @$data->courseevents).')';
            $template->coursetimehelp = $OUTPUT->help_icon('coursetime', 'report_trainingsessions');
        }

        if (in_array('elapsed', $cols)) {
            $template->coursetotaltime = report_trainingsessions_format_time(0 + @$data->elapsed, $durationformat);
            $template->coursetotalevents = ' ('.(0 + @$data->hits).')';
            $template->coursetotaltimehelp = $OUTPUT->help_icon('coursetotaltime', 'report_trainingsessions');
        }

        if (in_array('extelapsed', $cols)) {
            $template->extelapsed = report_trainingsessions_format_time(0 + @$data->extelapsed, $durationformat);
            $template->extevents = ' ('.(0 + @$data->exthits).')';
            $template->extelapsedhelp = $OUTPUT->help_icon('extelapsed', 'report_trainingsessions');
        }

        if (in_array('extother', $cols)) {
            $template->extother = report_trainingsessions_format_time(0 + @$data->extother, $durationformat);
            $template->extotherevents = ' ('.(0 + @$data->extotherhits).')';
            $template->extotherhelp = $OUTPUT->help_icon('extother', 'report_trainingsessions');
        }

        if (in_array('elapsedlastweek', $cols)) {
            $template->elapsedlastweek .= report_trainingsessions_format_time(0 + @$data->elapsedlastweek, $durationformat);
            $template->elapsedlastweekevents = ' ('.(0 + @$data->hitslastweek).')';
            $template->elapsedlastweekhelp = $OUTPUT->help_icon('elapsedlastweek', 'report_trainingsessions');
        }

        if (in_array('extelapsedlastweek', $cols)) {
            $template->extelapsedlastweek .= report_trainingsessions_format_time(0 + @$data->extelapsedlastweek, $durationformat);
            $template->extelapsedlastweekevents = ' ('.(0 + @$data->exthitslastweek).')';
            $template->extelapsedlastweekhelp = $OUTPUT->help_icon('extelapsedlastweek', 'report_trainingsessions');
        }

        if (in_array('extotherlastweek', $cols)) {
            $template->extotherlastweek .= report_trainingsessions_format_time(0 + @$data->extotherlastweek, $durationformat);
        }

        // Print additional grades.
        if (!empty($gradecols)) {
            $i = 0;
            $template->hasgrades = true;
            foreach ($gradecols as $gc) {
                $gradetpl = new Stdclass;
                $gradetpl->label = $gradetitles[$i];
                $gradetpl->value = sprintf('%0.2f', $data->gradecols[$i]);
                $template->grades[] = $gradetpl;
                $i++;
            }
        }

        // Plug here specific details.
    }

    if (in_array('workingsessions', $cols)) {
        $template->workingsessions = true;
        if (!empty($data->sessions)) {
            $template->sessions = (0 + @$data->sessions);
        } else {
            $template->sessions = get_string('nosessions', 'report_trainingsessions');
        }

        if ((@$data->sessions) == 0 && (@$completedwidth > 0)) {
            $template->checklistadvice = $OUTPUT->help_icon('checklistadvice', 'report_trainingsessions');
        }
    }

    // Add printing for global course time (out of activities).
    if (!$short) {
        if (!$withnooutofstructure) {
            $template->withoutofstructure = true;
            $template->sessionduration = report_trainingsessions_format_time(0 + @$data->coursetime + @$data->othertime, $durationformat);
            $template->sessionevents = ' ('.(0 + @$data->courseevents + @$data->otherevents).')';
        }
        if (isset($data->upload)) {
            $template->hasupload = true;
            $template->uploadtime = report_trainingsessions_format_time(0 + @$data->upload->elapsed, $durationformat);
            $template->uploadevents = ' ('.(0 + @$data->upload->events).')';
        }
    }

    return $OUTPUT->render_from_template('report_trainingsessions/userheader', $template);
}

/**
 * prints a report over each connection session
 *
 */
function report_trainingsessions_print_session_list($sessions, $courseid = 0, $userid = 0) {
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

    $template = new StdClass;

    $sessionsstr = ($courseid) ? get_string('coursesessions', 'report_trainingsessions') : get_string('sessions', 'report_trainingsessions');
    $template->heading = $OUTPUT->heading($sessionsstr, 2);
    if (empty($sessions)) {
        $template->hassessions = false;
        $template->nosessionsstr = $OUTPUT->notification(get_string('nosessions', 'report_trainingsessions'));
        return $OUTPUT->render_from_template('report_trainingsessions/sessionlist', $template);
    }

    $template->hassessions = true;

    // Effective printing of available sessions.

    $totalelapsed = 0;
    $induration = 0;
    $outduration = 0;
    $template->truesessions = 0;

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

        $template->truesessions++;

        foreach ($daysessions as $s) {

            $sessiontpl = new StdClass;

            if (!isset($s->sessionstart)) {
                continue;
            }

            $sessiontpl->startstyle = '';
            $sessiontpl->endstyle = '';
            $sessiontpl->checkstyle = '';
            if (!empty($config->enablelearningtimecheckcoupling)) {

                if (!empty($ltcconfig->checkworkingdays) || !empty($ltcconfig->checkworkinghours)) {

                    // Always mark in html rendering.
                    // Start check :
                    $fakecheck = new StdClass();
                    $fakecheck->usertimestamp = $s->sessionstart;
                    $fakecheck->userid = $userid;

                    $outtime = false;
                    if (!empty($ltcconfig->checkworkingdays) && !report_learningtimecheck_is_valid($fakecheck)) {
                        $sessiontpl->startstyle = 'style="color:#A0A0A0"';
                        $sessiontpl->endstyle = 'style="color:#A0A0A0"';
                        $sessiontpl->checkstyle = 'style="color:#A0A0A0"';
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
                                $sessiontpl->startstyle = 'style="color:#ff0000"';
                            }

                            // End check :
                            $fakecheck = new StdClass();
                            $fakecheck->userid = $userid;
                            $fakecheck->usertimestamp = $s->sessionend;
                            if (!$endcheck = report_learningtimecheck_check_time($fakecheck, $ltcconfig)) {
                                $sessiontpl->endstyle = 'style="color:#ff0000"';
                            }

                            if (!$startcheck && !$endcheck) {
                                $sessiontpl->startstyle = 'style="color:#ff0000"';
                                $sessiontpl->endstyle = 'style="color:#ff0000"';
                                $sessiontpl->checkstyle = 'style="color:#ff0000"';
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

            $sessiontpl->sessionstartdate = userdate($s->sessionstart);
            $sessiontpl->sessionenddate = (isset($s->sessionend)) ? userdate(@$s->sessionend) : '';
            $sessiontpl->elps = report_trainingsessions_format_time(@$s->elapsed, $durationformat);
            $totalelapsed += @$s->elapsed;

            $template->sessions[] = $sessiontpl;
        }
    }

    if (!empty($config->printsessiontotal)) {
        $template->printtotal = true;
        $template->totalsessionstimehelpicon = $OUTPUT->help_icon('totalsessiontime', 'report_trainingsessions');
        $template->totalelapsed = report_trainingsessions_format_time($totalelapsed, $durationformat);

        if (!empty($config->enablelearningtimecheckcoupling) &&
                (!empty($ltcconfig->checkworkingdays) ||
                        !empty($ltcconfig->checkworkinghours))) {
            $template->haslearningtimecheckdata = true;
            $template->tplinhelpicon = $OUTPUT->help_icon('insessiontime', 'report_trainingsessions');
            $tpl->induration = report_trainingsessions_format_time($induration, $durationformat);

            $template->tplouthelpicon = $OUTPUT->help_icon('outsessiontime', 'report_trainingsessions');
            $template->outduration = report_trainingsessions_format_time($outduration, $durationformat);
        }
    }

    return $OUTPUT->render_from_template('report_trainingsessions/sessionlist', $template);
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
    $str .= '<b>'.$elapsedstr.':</b> ';
    $str .= report_trainingsessions_format_time(0 + $dataobject->elapsed, $durationformat);
    $str .= $OUTPUT->help_icon('totalsitetime', 'report_trainingsessions');
    $str .= '<br/>';
    $str .= '<b>'.$hitsstr.':</b> ';
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

    if (!empty($items)) {
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

    $str .= '<div class="completionbar">';
    $str .= '<b>'.get_string('done', 'report_trainingsessions').'</b>';

    $pixurl = $OUTPUT->image_url('green', 'report_trainingsessions');
    $str .= '<img src="'.$pixurl.'" style="width:'.$completedwidth.'px" class="donebar" align="top" title="'.$completedpc.'" />';
    $pixurl = $OUTPUT->image_url('blue', 'report_trainingsessions');
    $style = 'width:'.$remainingwidth.'px';
    $str .= '<img src="'.$pixurl.'" style="'.$style.'" class="remainingbar" align="top"  title="'.$remainingpc.'" />';
    $str .= '</div>';

    return $str;
}