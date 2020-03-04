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

namespace report\trainingsessions;

use StdClass;
use \context_course;
use \context_system;
use \moodle_url;

defined('MOODLE_INTERNAL') || die;

class HtmlRenderer {

    protected $rt;

    public function __construct($rt) {
        $this->rt = $rt;
    }

    /**
     * a raster for html printing of a report structure.
     *
     * @param string ref $str a buffer for accumulating output
     * @param object $structure a course structure object.
     */
    public function print_allcourses_html(&$aggregate, &$return) {
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
                $template->siteelapsed = $this->rt->format_time($output[0][SITEID]->elapsed, $durationformat);
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
                        $catlinetpl->elapsed = $this->rt->format_time($cdata->elapsed, $durationformat).'<br/>';
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
     * @param object $structure a course structure object.
     * @param objectref $aggregate an object with all the time samples inside.
     * @param objectref &$dataobject an object reference for collecting overall calculated time and events.
     * @param integerref &$done a give back integer counting the "done" items.
     * @param string $indent indent string for the current level
     * @param int $level the current nesting level
     * @return a printable template.
     */
    public function print_html($structure, &$aggregate, &$done, $indent = '', $level = 0) {
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
        $template->showhits = $config->showhits;
        $template->showitemfirstaccess = $config->showitemfirstaccess;
        $template->showitemlastaccess = $config->showitemlastaccess;

        if (empty($structure) && empty($config->showsectionsonly)) {
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

        $template->elapsed = 0;
        $template->events = 0;
        $template->firstaccess = null;
        $template->lastaccess = null;

        if (is_array($structure)) {
            // If an array of elements produce successively each output and collect aggregates.
            $template->hassubs = true;
            foreach ($structure as $element) {
                if (isset($element->instance) && empty($element->instance->visible)) {
                    // Non visible items should not be displayed nor calculated.
                    continue;
                }
                $subtemplate = $this->print_html($element, $aggregate, $done, $indent, $level + 1);
                if ($subtemplate) {
                    $template->elapsed += $subtemplate->elapsed;
                    $template->events += (0 + @$subtemplate->events);
                    // echo "Getting from subs in structural element ";
                    // print_object($subtemplate);
                    trainingsessions::updatefirst($template->firstaccess, $subtemplate->firstaccess);
                    trainingsessions::updatelast($template->lastaccess, $subtemplate->lastaccess);
                    $template->structures[] = $subtemplate;
                }
            }

            // If array results empty, returns nothing.
            if (empty($template->structures)) {
                return null;
            }
        } else {
            // We are a real element, or structure.
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

                    $dataobject = new StdClass;
                    $dataobject->firstaccess = null;
                    $dataobject->lastaccess = null;
                    $dataobject->elapsed = 0;
                    $dataobject->events = 0;

                    if (!empty($structure->subs)) {
                        $subtemplate = $this->print_html($structure->subs, $aggregate, $done, $indent, $level + 1);
                        if ($subtemplate) {
                            $template->structures[] = $subtemplate;
                            $dataobject = $subtemplate;
                            $template->hassubs = true;
                            // echo "Getting from subs in structural element (element)";
                            // print_object($subtemplate);
                            trainingsessions::updatefirst($template->firstaccess, @$dataobject->firstaccess);
                            trainingsessions::updatelast($template->lastaccess, @$dataobject->lastaccess);
                        }
                    }

                    if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                        $done++;
                        $dataobject = $aggregate[$structure->type][$structure->id];
                        // May not have access date depending on structure type. (aka sections)
                    }

                    trainingsessions::updatefirst($template->firstaccess, @$dataobject->firstaccess);
                    trainingsessions::updatelast($template->lastaccess, @$dataobject->lastaccess);

                    if (!in_array($structure->type, $ignoremodulelist)) {
                        if (!empty($dataobject->timesource) && $dataobject->timesource == 'credit' && $dataobject->elapsed) {
                            $template->source = get_string('credittime', 'block_use_stats');
                        }
                        if (!empty($dataobject->timesource) && $dataobject->timesource == 'declared' && $dataobject->elapsed) {
                            $template->source = get_string('declaredtime', 'block_use_stats');
                        }
                        $template->elapsedstr = $this->rt->format_time($dataobject->elapsed, $durationformat);
                        if (!empty($dataobject->real)) {
                            $template->real = true;
                            $template->realstr = $this->rt->format_time($dataobject->real, $durationformat);
                        } else if (!empty($dataobject->credit)) {
                            $template->credit = true;
                            $template->creditstr = $this->rt->format_time($dataobject->credit, $durationformat);
                        }
                    } else {
                        $template->source = get_string('ignored', 'block_use_stats');
                    }

                } else {
                    // It is only a structural module that should not impact on display, but may have on calculated stats.
                    if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                        $dataobject = $aggregate[$structure->type][$structure->id];
                        // count stats directly attached to this object level.
                        $template->elapsed = $dataobject->elapsed;
                        $template->events = $dataobject->events;
                        trainingsessions::updatefirst($template->firstaccess, $dataobject->firstaccess);
                        trainingsessions::updatelast($template->lastaccess, $dataobject->lastaccess);
                    }
                    if (!empty($structure->subs)) {
                        // Print for sub array.
                        $subtemplate = $this->print_html($structure->subs, $aggregate, $done, $indent, $level + 1);
                        if ($subtemplate) {
                            $template->hassubs = true;
                            $template->elapsed += $subtemplate->elapsed;
                            $template->events += $subtemplate->events;
                            // echo "Getting from subs in non structural element ";
                            // print_object($subtemplate);
                            trainingsessions::updatefirst($template->firstaccess, $subtemplate->firstaccess);
                            trainingsessions::updatelast($template->lastaccess, $subtemplate->lastaccess);
                            $template->structures[] = $subtemplate;
                        }
                    }
                }
            }
        }

        // Post format textual expressions.
        if ($template->firstaccess) {
            $template->firstaccessstr = date('Y/m/d H:i', $template->firstaccess);
        } else {
            $template->firstaccessstr = '--';
        }

        if ($template->lastaccess) {
            $template->lastaccessstr = date('Y/m/d H:i', $template->lastaccess);
        } else {
            $template->lastaccessstr = '--';
        }

        if (is_siteadmin()) {
            $template->eventsstr = ' ('.(0 + @$template->events).')';
        }

        // echo "Level Finished : $level\n";
        // print_object($template);

        if ($level == 0) {
            return $OUTPUT->render_from_template('report_trainingsessions/structure', $template);
        }

        return $template;
    }

    /**
     * a raster for html printing of a report structure header
     * with all the relevant data about a user.
     */
    public function print_header_html($user, $course, $data, $cols, $short = false, $withcompletion = true,
                                                       $withnooutofstructure = false) {
        global $OUTPUT;

        $config = get_config('report_trainingsessions');

        if (!empty($config->showseconds)) {
            $durationformat = 'htmlds';
        } else {
            $durationformat = 'htmld';
        }

        // Ask config for enabled info.

        $template = new StdClass;
        $template->short = $short;
        $template->showhits = $config->showhits;

        $template->userpicture = $OUTPUT->user_picture($user, array('size' => 32, 'courseid' => $course->id));
        $template->fullname = fullname($user);

        // Print group status.
        $groupnames = $this->rt->get_user_groups($user->id, $course->id);
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
        $context = context_course::instance($course->id);
        $roles = role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL);
        $userroles = get_user_roles($context, $user->id);
        $uroles = array();

        foreach ($userroles as $rid => $r) {
            $uroles[] = $roles[$r->roleid]->localname;
        }
        $template->roles = implode (",", $uroles);

        if (!empty($data->linktousersheet)) {
            $params = array('view' => 'user',
                            'id' => $course->id,
                            'userid' => $user->id,
                            'from' => $data->from,
                            'to' => $data->to);
            $template->hasdetails = true;
            $template->detailurl = new moodle_url('/report/trainingsessions/index.php', $params);
        }

        if ($withcompletion) {
            $template->withcompletion = true;
            // Print completion bar.
            if (!array_key_exists('ltcprogressinitems', $data) && !array_key_exists('ltcprogressinmandatoryitems', $data)) {
                $template->completionbar = $this->print_progressionbar(0 + @$data->items, 0 + @$data->done, 500);
            } else {
                $bars = '';
                if (array_key_exists('ltcprogressinitems', $data)) {
                    $progress = $this->print_progressionbar(0 + @$data->ltcitems, 0 + @$data->ltcdone, 500);
                    $progress .= ' '.get_string('ltc', 'learningtimecheck');
                    $bars .= '<div class="all-items" style="height:50px">'.$progress.'</div>';
                }
                if (array_key_exists('ltcprogressinmandatoryitems', $data)) {
                    $progress = $this->print_progressionbar(0 + @$data->ltcmandatoryitems, 0 + @$data->ltcmandatorydone, 500);
                    $progress .= ' '.get_string('mandatories', 'learningtimecheck');
                    $bars .= '<div class="mandatory-items" style="height:50px">'.$progress.'</div>';
                }
                $template->completionbar = $bars;
            }
        }

        $this->add_time_totalizers($data, $cols, $template, $durationformat);

        $this->rt->add_graded_columns($gradecols, $gradetitles, $gradeformats);

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

        $this->add_times($data, $cols, $template, get_string('profileinfotimeformat', 'report_trainingsessions'));

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

        return $OUTPUT->render_from_template('report_trainingsessions/userheader', $template);
    }

    /**
     * Prints all time measurement items.
     */
    public function add_time_totalizers($data, $cols, &$template, $durationformat) {
        global $OUTPUT;

        $timecols = array('elapsed', 'extelapsed', 'extotherelapsed',
                          'activitytime', 'coursetime', 'othertime', 'uploadtime',
                          'elapsedoutofstructure',
                          'elapsedlastweek', 'extelapsedlastweek', 'extotherelapsedlastweek');

        foreach ($cols as $c) {

            $c = trim($c);

            if (!in_array($c, $timecols)) {
                continue;
            }

            $totalizertpl = new Stdclass;
            $totalizertpl->key = $c;
            $totalizertpl->name = get_string($c, 'report_trainingsessions');
            $totalizertpl->help = $OUTPUT->help_icon($c, 'report_trainingsessions');
            $totalizertpl->elapsed = $this->rt->format_time(0 + @$data->$c, $durationformat);
            $h = str_replace('elapsed', 'hits', $c);
            $h = str_replace('time', 'hits', $h);  // Alternative if not an "elapsed" column.
            $totalizertpl->hits = 0 + @$data->$h;

            $template->totalizers[] = $totalizertpl;
        }
    }

    /**
     * Prints all time measurement items.
     */
    public function add_times($data, $cols, &$template, $timeformat) {
        global $OUTPUT;

        $timecols = array('firstaccess', 'lastlogin', 'firstcourseaccess', 'lastcourseaccess');

        foreach ($cols as $c) {

            $c = trim($c);

            if (!in_array($c, $timecols)) {
                continue;
            }

            $timestpl = new Stdclass;
            $timestpl->key = $c;
            $timestpl->name = get_string($c, 'report_trainingsessions');
            $timestpl->help = $OUTPUT->help_icon($c, 'report_trainingsessions');
            $timestpl->elapsed = $this->rt->format_time(0 + @$data->$c, $timeformat);
            /*
            $h = str_replace('elapsed', 'hits', $c);
            $h = str_replace('time', 'hits', $h);  // Alternative if not an "elapsed" column.
            $timestpl->hits = 0 + $data->$h;
            */

            $template->times[] = $timestpl;
            $template->hastimes = true;
        }
    }

    /**
     * prints a report over each connection session
     * @param array $sessions an array of session descriptions
     * @param integer $courseid the current courseid if the report is within a course scope
     * @param integer $userid the current userid viewing the report
     */
    public function print_session_list($sessions, $courseid = 0, $userid = 0) {
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

            $daysessions = $this->rt->splice_session($session);

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
                        if (!empty($ltcconfig->checkworkingdays) && !report_learningtimecheck::is_valid($fakecheck)) {
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
                                if (!$startcheck = report_learningtimecheck::check_time($fakecheck, $ltcconfig)) {
                                    $sessiontpl->startstyle = 'style="color:#ff0000"';
                                }

                                // End check :
                                $fakecheck = new StdClass();
                                $fakecheck->userid = $userid;
                                $fakecheck->usertimestamp = $s->sessionend;
                                if (!$endcheck = report_learningtimecheck::check_time($fakecheck, $ltcconfig)) {
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
                $sessiontpl->elps = $this->rt->format_time(@$s->elapsed, $durationformat);
                $totalelapsed += @$s->elapsed;

                $template->sessions[] = $sessiontpl;
            }
        }

        if (!empty($config->printsessiontotal)) {
            $template->printtotal = true;
            $template->totalsessionstimehelpicon = $OUTPUT->help_icon('totalsessiontime', 'report_trainingsessions');
            $template->totalelapsed = $this->rt->format_time($totalelapsed, $durationformat);

            if (!empty($config->enablelearningtimecheckcoupling) &&
                    (!empty($ltcconfig->checkworkingdays) ||
                            !empty($ltcconfig->checkworkinghours))) {
                $template->haslearningtimecheckdata = true;
                $template->tplinhelpicon = $OUTPUT->help_icon('insessiontime', 'report_trainingsessions');
                $tpl->induration = $this->rt->format_time($induration, $durationformat);

                $template->tplouthelpicon = $OUTPUT->help_icon('outsessiontime', 'report_trainingsessions');
                $template->outduration = $this->rt->format_time($outduration, $durationformat);
            }
        }

        return $OUTPUT->render_from_template('report_trainingsessions/sessionlist', $template);
    }

    public function print_total_site_html($dataobject) {
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
        $str .= $this->rt->format_time(0 + $dataobject->elapsed, $durationformat);
        $str .= $OUTPUT->help_icon('totalsitetime', 'report_trainingsessions');
        $str .= '<br/>';
        $str .= '<b>'.$hitsstr.':</b> ';
        $str .= 0 + @$dataobject->events;

        return $str;
    }

    public function print_pager($maxsize, $offset, $pagesize, $url, $contextparms) {

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

    public function print_progressionbar($items, $done, $width) {
        global $OUTPUT;

        $template = new StdClass;

        if (!empty($items)) {
            $completed = $done / $items;
        } else {
            $completed = 0;
        }
        $template->total = $items;
        $template->done = $done;
        $template->value = round($completed * 100);
        $template->pixurl = $OUTPUT->image_url('progress1', 'report_trainingsessions');

        return $OUTPUT->render_from_template('report_trainingsessions/progressionbar', $template);
    }
}