<?php

/**
* a raster for xls printing of a report structure header
* with all the relevant data about a user.
*
*/
function training_reports_print_header_csv($userid, $courseid, $data) {
    global $CFG, $DB;

    $user = $DB->get_record('user', array('id' => $userid));
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        die("No course for course id $courseid\n");
    }

    $csvstr = "#\n";

    $csvstr .= '# '.get_string('sessionreports', 'report_trainingsessions')."\n";

    $csvstr .= '# '.get_string('user').' : ';
    $csvstr .= fullname($user)."\n";

    $csvstr .= '# '.get_string('idnumber').' : ';
    $csvstr .= $user->idnumber."\n";

    $csvstr .= '# '.get_string('email').' : ';
    $csvstr .= $user->email."\n";

    $csvstr .= '# '.get_string('city').' : ';
    $csvstr .= $user->city."\n";

    $csvstr .= '# '.get_string('institution').' : ';
    $csvstr .= $user->institution."\n";

    $csvstr .= '# '.get_string('course', 'report_trainingsessions').' : ';
    $csvstr .= $course->fullname."\n";

    $csvstr .= '# '.get_string('from').' : ';
    $csvstr .= userdate($data->from)."\n";

    $csvstr .= '# '.get_string('to').' : ';
    $csvstr .= userdate(time())."\n";

    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

    // print group status
    $csvstr .= '# '.get_string('groups');
    $str = '';
    if (!empty($usergroups)) {
        foreach ($usergroups as $group) {
            $str = $group->name;
            if ($group->id == groups_get_course_group($course)) {
                $str = "[$str]";
            }
            $groupnames[] = $str;
        }
        $str = implode(', ', $groupnames);
    }

    $csvstr .= $str."\n";

    $context = context_course::instance($courseid);
    $csvstr .= '# '.get_string('roles').' : ';
    $roles = get_user_roles($context, $userid);
    $rolenames = array();
    foreach ($roles as $role) {
        $rolenames[] = $role->shortname;
    }
    $csvstr .= strip_tags(implode(",", $rolenames))."\n";

    // print completion bar
    if (empty($data->items)) {
        $completed = 0;
    } else {
        $completed = (0 + @$data->done) / $data->items;
    }
    $remaining = 1 - $completed;
    $completedpc = ceil($completed * 100);
    $remainingpc = 100 - $completedpc;

    $csvstr .= '# '.get_string('done', 'report_trainingsessions').' : ';
    $csvstr .= (0 + @$data->done). ' ' . get_string('over', 'report_trainingsessions'). ' '. (0 + @$data->items). ' ('.$completedpc.' %)'."\n";

    $csvstr .= '# '.get_string('elapsed', 'report_trainingsessions').' : ';
    $csvstr .= training_reports_format_time((0 + @$data->elapsed), 'xlsd')."\n";

    $csvstr .= '# '.get_string('hits', 'report_trainingsessions').' : ';
    $csvstr .= (0 + @$data->events)."\n";

    $csvstr .= "#\n";

    return $csvstr;
}

/**
* a raster for xls printing of a report structure.
*
*/
function training_reports_print_csv(&$str, &$structure, &$aggregate, &$done, $level = 1) {

    if (empty($structure)) {
        '# ';
        $str = '# '.get_string('nostructure', 'report_trainingsessions')."\n";
        return $str;
    }

    // makes a blank dataobject.
    if (!isset($dataobject)) {
        $dataobject = new StdClass;
        $dataobject->elapsed = 0;
        $dataobject->events = 0;
    }

    if (is_array($structure)) {
        // recurs in sub structures
        foreach ($structure as $element) {
            if (isset($element->instance) && empty($element->instance->visible)) {
                continue; // non visible items should not be displayed
            }
            $res = training_reports_print_csv($element, $aggregate, $done, $level);
            $dataobject->elapsed += $res->elapsed;
            $dataobject->events += $res->events;
        } 
    } else {
        // prints a single row
        if (!isset($element->instance) || !empty($element->instance->visible)) {
            // Non visible items should not be displayed.
            if (!empty($structure->name)) {
                // Write element title.
                $str = shorten_text($structure->name, 85);

                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $done++;
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }

                $csvline = $str; // saves the current row for post writing aggregates

                if (!empty($structure->subs)) {
                    // debug_trace("with subs");
                    $res = training_reports_print_csv($structure->subs, $aggregate, $done, $level + 1);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }

                $str = training_reports_format_time($dataobject->elapsed, 'xlsd');
                $csvline .= ';'.training_reports_format_time(@$aggregate[$structure->type][$structure->id]->firstaccess, 'xls');
                $csvline .= $str;
                $csvline .= $dataobject->events;

            } else {
                // It is only a structural module that should not impact on level
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $res = training_reports_print_csv($structure->subs, $aggregate, $done, $level);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }
            }
        }
    }
    return $dataobject;
}

/**
 * print session table in an initialied worksheet
 * @param object $worksheet
 * @param int $row
 * @param array $sessions
 * @param object $xls_formats
 */
function training_reports_print_sessions_csv(&$str, &$sessions, $courseid) {

    $totalelapsed = 0;

    if (!empty($sessions)) {
        foreach ($sessions as $s) {

            if ($courseid && !array_key_exists($courseid, $s->courses)) {
                continue; // omit all sessions not visiting this course
            }

            $csvline = training_reports_format_time(@$s->sessionstart, 'xls');
            if (!empty($s->sessionend)){
                $csvline .= ';'. training_reports_format_time(@$s->sessionend, 'xls');
            } else {
                $csvline .= ';';
            }
            $csvline .= ';'.format_time(0 + @$s->elapsed);
            $csvline .= ';'.training_reports_format_time(0 + @$s->elapsed, 'xlsd');
            $totalelapsed += 0 + @$s->elapsed;
        }
        $str .= $csvline;
    }
    return $totalelapsed;
}

/**
 * a raster for Excel printing of a report structure.
 *
 * @param ref $worksheet a buffer for accumulating output
 * @param object $aggregate aggregated logs to explore.
 */
function training_reports_print_allcourses_csv(&$str, &$aggregate) {
    global $CFG, $COURSE, $DB;

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
                    if (!$course = $DB->get_record('course', array('id' => $cid), 'id,idnumber,shortname,fullname,category')) {
                        continue;
                    }
                    $courses[$cid] = $course;
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

        $coursecats = $DB->get_records_list('course_categories', 'id', array_keys($catids));
    }

    if (!empty($output)) {

        $elapsedstr = get_string('elapsed', 'report_trainingsessions');
        $hitsstr = get_string('hits', 'report_trainingsessions');
        $coursestr = get_string('course');

        if (isset($output[0])) {
            $str .= $elapsedstr;
            $str .= ';'.training_reports_format_time($output[0][SITEID]->elapsed, 'xlsd');
            $str .= ';'."\n";

            $str .= $hitsstr;
            $str .= ';'.$output[0][SITEID]->events;
            $str .= ';'."\n";

            $str .= "#\n";
            $str .= "#\n";
            $str .= "#\n";
        }

        foreach ($output as $catid => $catdata) {
            if ($catid == 0) {
                continue;
            }
            $str .= $coursecats[$catid]->name.";;\n";

            $str .= $coursestr;
            $str .= ';'.$elapsedstr;
            $str .= ';'.$hitsstr."\n";

            foreach ($catdata as $cid => $cdata) {
                $ccontext = context_course::instance($cid);
                if (has_capability('report/trainingsessions:view', $ccontext)) {
                    $str .= $courses[$cid]->fullname;
                    $str .= ';'.training_reports_format_time($cdata->elapsed, 'xlsd');
                    $str .= ';'.$cdata->events."\n";
                } else {
                    $str .= $courses[$cid]->fullname;
                    $str .= ';';
                    $str .= get_string('nopermissiontoview', 'report_trainingsessions')."\n";
                }
            }
        }
    }

    return $return;
}

/**
 * a raster for Excel printing of a report structure.
 *
 * @param ref $worksheet a buffer for accumulating output
 * @param object $aggregate aggregated logs to explore.
 */
function training_reports_print_courses_line_header_csv(&$str) {

    $dataline = array();
    $dataline[] = 'ix';
    $dataline[] = 'uid';
    $dataline[] = 'uidnumber';
    $dataline[] = 'username';
    $dataline[] = 'lastname';
    $dataline[] = 'firstname';

    $dataline[] = 'cid';
    $dataline[] = 'shortname';
    $dataline[] = 'fullname';

    $dataline[] = 'elapsed';
    $dataline[] = 'hits';

    $str .= implode(';', $dataline)."\n";
}

/**
 * a raster for Excel printing of a report structure.
 *
 * @param ref $worksheet a buffer for accumulating output
 * @param object $aggregate aggregated logs to explore.
 */
function training_reports_print_courses_line_csv(&$str, &$aggregate, $user) {
    global $CFG, $COURSE, $DB;
    static $lineix = 1;

    $output = array();
    $courses = array();
    $courseids = array();
    if (!empty($aggregate['coursetotal'])) {
        foreach ($aggregate['coursetotal'] as $cid => $cdata) {
            if ($cid == 0) {
                $cid = SITEID;
            }

            // Some caching.
            if (!in_array($cid, $courseids)) {
                if (!$course = $DB->get_record('course', array('id' => $cid), 'id,idnumber,shortname,fullname,category')) {
                    continue;
                }
                $courses[$cid] = $course;
            }

            $dataline = array();
            $dataline[] = $lineix;
            $dataline[] = $user->id;
            $dataline[] = $user->idnumber;
            $dataline[] = $user->username;
            $dataline[] = $user->lastname;
            $dataline[] = $user->firstname;

            $dataline[] = $courses[$cid]->id;
            $dataline[] = $courses[$cid]->shortname;
            $dataline[] = $courses[$cid]->fullname;

            $events = $cdata->elapsed;
            $hours = floor($cdata->elapsed / HOURSECS);
            $remmins = $cdata->elapsed % HOURSECS;
            $mins = floor($remmins / 60);
            $secs = $remmins % 60;
            $dataline[] = sprintf('%02d', $hours).':'.sprintf('%02d', $mins).':'.sprintf('%02d', $secs);
            $dataline[] = $cdata->events;

            $str .= implode(';', $dataline)."\n";

            $lineix++;
        }
    }
}
