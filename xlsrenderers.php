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
 * a raster for xls printing of a report structure header
 * with all the relevant data about a user.
 *
 * @package    report_trainingsessions
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @version    moodle 2.x
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
if (!defined('MOODLE_INTERNAL')) die('You cannot use this script this way');

function trainingsessions_print_header_xls(&$worksheet, $userid, $courseid, $data, $xls_formats) {
    global $CFG, $DB;

    $user = $DB->get_record('user', array('id' => $userid));
    $course = $DB->get_record('course', array('id' => $courseid));

    $row = 0;

    $worksheet->set_row(0, 40, $xls_formats['t']);
    $worksheet->write_string($row, 0, get_string('sessionreports', 'report_trainingsessions'), $xls_formats['t']);
    $worksheet->merge_cells($row, 0, 0, 12);
    $row++;
    $worksheet->write_string($row, 0, get_string('user').' :', $xls_formats['pb']);
    $worksheet->write_string($row, 1, fullname($user));
    $row++;
    $worksheet->write_string($row, 0, get_string('idnumber').' :', $xls_formats['pb']);
    $worksheet->write_string($row, 1, $user->idnumber);
    $row++;
    $worksheet->write_string($row, 0, get_string('email').' :', $xls_formats['pb']);
    $worksheet->write_string($row, 1, $user->email);
    $row++;
    $worksheet->write_string($row, 0, get_string('city').' :', $xls_formats['pb']);
    $worksheet->write_string($row, 1, $user->city);
    $row++;
    $worksheet->write_string($row, 0, get_string('institution').' :', $xls_formats['pb']);
    $worksheet->write_string($row, 1, $user->institution);
    $row++;
    $worksheet->write_string($row, 0, get_string('course', 'report_trainingsessions').' :', $xls_formats['pb']);    
    $worksheet->write_string($row, 1, $course->fullname);
    $row++;
    $worksheet->write_string($row, 0, get_string('from').' :', $xls_formats['pb']);
    $worksheet->write_string($row, 1, userdate($data->from));
    $row++;
    $worksheet->write_string($row, 0, get_string('to').' :', $xls_formats['pb']);
    $worksheet->write_string($row, 1, userdate(time()));
    $row++;

    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

    // print group status
    $worksheet->write_string($row, 0, get_string('groups').' :', $xls_formats['pb']);
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

    $worksheet->write_string($row, 1, $str);
    $row++;

    $context = context_course::instance($courseid);
    $worksheet->write_string($row, 0, get_string('roles').' :', $xls_formats['pb']);
    $roles = get_user_roles($context, $userid);
    $rolenames = array();
    foreach ($roles as $role) {
        $rolenames[] = $role->shortname;
    }
    $worksheet->write_string($row, 1, strip_tags(implode(",", $rolenames)));

    $row++;
    // print completion bar
    if (empty($data->items)) {
        $completed = 0;
    } else {
        $completed = (0 + @$data->done) / $data->items;
    }
    $remaining = 1 - $completed;
    $completedpc = ceil($completed * 100);
    $remainingpc = 100 - $completedpc;

    $worksheet->write_string($row, 0, get_string('done', 'report_trainingsessions'), $xls_formats['pb']);
    $worksheet->write_string($row, 1, (0 + @$data->done).' '.get_string('over', 'report_trainingsessions'). ' '. (0 + @$data->items). ' ('.$completedpc.' %)');
    $row++;
    $worksheet->write_string($row, 0, get_string('elapsed', 'report_trainingsessions').' :', $xls_formats['pb']);
    $worksheet->write_string($row, 1, trainingsessions_format_time((0 + @$data->elapsed), 'xlsd'), $xls_formats['p']);
    $row++;
    $worksheet->write_string($row, 0, get_string('hits', 'report_trainingsessions').' :', $xls_formats['pb']);
    $worksheet->write_number($row, 1, (0 + @$data->events));

    return $row;
}

/**
 * a raster for xls printing of a report structure.
 *
 */
function trainingsessions_print_xls(&$worksheet, &$structure, &$aggregate, &$done, &$row, &$xls_formats, $level = 1) {

    if (empty($structure)) {
        $str = get_string('nostructure', 'report_trainingsessions');
        $worksheet->write_string($row, 1, $str);
        return;
    }

    // makes a blank dataobject.
    if (!isset($dataobject)) {
        $dataobject = new StdClass;
        $dataobject->elapsed = 0;
        $dataobject->events = 0;
    }

    if (is_array($structure)) {
        // recurse in sub structures
        foreach ($structure as $element) {
            if (isset($element->instance) && empty($element->instance->visible)) {
                // non visible items should not be displayed.
                continue;
            }
            $res = trainingsessions_print_xls($worksheet, $element, $aggregate, $done, $row, $xls_formats, $level);
            $dataobject->elapsed += $res->elapsed;
            $dataobject->events += $res->events;
        } 
    } else {
        // Prints a single row.
        $format = (isset($xls_formats['a'.$level])) ? $xls_formats['a'.$level] : $xls_formats['z'] ;

        if (!isset($element->instance) || !empty($element->instance->visible)) {
            // Non visible items should not be displayed.
            if (!empty($structure->name)) {
                // Write element title.
                $indent = str_pad('', 3 * $level, ' ');
                $str = $indent.shorten_text($structure->name, 85);
                $worksheet->write_string($row, 1, $str, $format);

                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $done++;
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }

                $thisrow = $row; // saves the current row for post writing aggregates
                $row++;
                if (!empty($structure->subs)) {
                    // debug_trace("with subs");
                    $res = trainingsessions_print_xls($worksheet, $structure->subs, $aggregate, $done, $row, $xls_formats, $level + 1);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }

                $str = trainingsessions_format_time($dataobject->elapsed, 'xlsd');
                $worksheet->write_string($thisrow, 0, trainingsessions_format_time(@$aggregate[$structure->type][$structure->id]->firstaccess, 'xls'), $xls_formats['p']);
                $worksheet->write_string($thisrow, 2, $str, $xls_formats['p']);
                $worksheet->write_number($thisrow, 3, $dataobject->events, $xls_formats['p']);

            } else {
                // It is only a structural module that should not impact on level
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $res = trainingsessions_print_xls($worksheet, $structure->subs, $aggregate, $done, $row, $xls_formats, $level);
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
function trainingsessions_print_sessions_xls(&$worksheet, $row, &$sessions, $courseid, &$xls_formats) {

    $totalelapsed = 0;

    if (!empty($sessions)) {
        foreach ($sessions as $s) {

            if ($courseid && !array_key_exists($courseid, $s->courses)) {
                // omit all sessions not visiting this course
                continue;
            }

            $worksheet->write_string($row, 0, trainingsessions_format_time(@$s->sessionstart, 'xls'), $xls_formats['p']);
            if (!empty($s->sessionend)) {
                $worksheet->write_string($row, 1, trainingsessions_format_time(@$s->sessionend, 'xls'), $xls_formats['p']);
            }
            $worksheet->write_string($row, 2, format_time(0 + @$s->elapsed), $xls_formats['tt']);    
            $worksheet->write_string($row, 3, trainingsessions_format_time(0 + @$s->elapsed, 'xlsd'), $xls_formats['p']);    
            $totalelapsed += 0 + @$s->elapsed;

            $row++;
        }
    }
    return $totalelapsed;
}

/**
 * a raster for Excel printing of a report structure.
 *
 * @param ref $worksheet a buffer for accumulating output
 * @param object $aggregate aggregated logs to explore.
 */
function trainingsessions_print_allcourses_xls(&$worksheet, &$aggregate, $row, &$xls_formats) {
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
                    $courses[$cid] = $DB->get_record('course', array('id' => $cid), 'id,idnumber,shortname,fullname,category');
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
            $worksheet->write_string($row, 0, get_string('site'), $xls_formats['tt']);
            $row++;
            $worksheet->write_string($row, 0, $elapsedstr, $xls_formats['p']);
            $worksheet->write_string($row, 1, trainingsessions_format_time($output[0][SITEID]->elapsed, 'xlsd'), $xls_formats['p']);
            $row++;
            $worksheet->write_string($row, 0, $hitsstr, $xls_formats['p']);
            $worksheet->write_number($row, 1, $output[0][SITEID]->events, $xls_formats['z']);
            $row++;
        }

        foreach ($output as $catid => $catdata) {
            if ($catid == 0) {
                continue;
            }
            $worksheet->write_string($row, 0, $coursecats[$catid]->name, $xls_formats['tt']);
            $row++;
            $worksheet->write_string($row, 0, $coursestr, $xls_formats['tt']);
            $worksheet->write_string($row, 1, $elapsedstr, $xls_formats['tt']);
            $worksheet->write_string($row, 2, $hitsstr, $xls_formats['tt']);
            $row++;

            foreach ($catdata as $cid => $cdata) {
                $ccontext = context_course::instance($cid);
                if (has_capability('report/trainingsessions:view', $ccontext)) {
                    $worksheet->write_string($row, 0, $courses[$cid]->fullname, $xls_formats['p']);
                    $worksheet->write_string($row, 1, trainingsessions_format_time($cdata->elapsed, 'xlsd'), $xls_formats['p']);
                    $worksheet->write_number($row, 2, $cdata->events, $xls_formats['z']);
                    $row++;
                } else {
                    $worksheet->write_string($row, 0, $courses[$cid]->fullname, $xls_formats['p']);
                    $worksheet->write_string($row, 2, get_string('nopermissiontoview', 'report_trainingsessions'), $xls_formats['p']);
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
function trainingsessions_print_rawline_xls(&$worksheet, $data, $dataformats, $row, &$xls_formats) {

    for ($i = 0 ; $i < count($data) ; $i++) {

        if (!array_key_exists($dataformats[$i], $xls_formats)) {
            throw new Exception('Unknown XLS format '.$dataformats[$i]);
        }

        if (preg_match('/^z/', $dataformats[$i])) {
            if ($dataformats[$i] == 'z') {
                $celldata = $data[$i];
            } else {
                $celldata =  trainingsessions_format_time($data[$i], 'xls');
            }
            $worksheet->write_string($row, $i, $celldata, $xls_formats[$dataformats[$i]]);
        } else {
            $worksheet->write_string($row, $i, $data[$i], $xls_formats[$dataformats[$i]]);
        }
    }
    return ++$row;
}
