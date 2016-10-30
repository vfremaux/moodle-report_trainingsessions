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

defined('MOODLE_INTERNAL') || die();

function report_trainingsessions_build_xls_format($workbook, $size, $bold, $color, $fgcolor, $numfmt = null) {

    $format = $workbook->add_format();

    if ($size != null) {
        $format->set_size($size);
    }

    if ($color != null) {
        $format->set_color($color);
    }

    if ($fgcolor != null) {
        $format->set_fg_color($fgcolor);
    }

    if ($bold != null) {
        $format->set_bold(1);
    }

    if ($numfmt != null) {
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
function report_trainingsessions_xls_formats(&$workbook) {
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
    $xls_formats['t']   = report_trainingsessions_build_xls_format( $workbook, $sizettl, $bold,    $colorbdy, $fgcolorbdy);
    $xls_formats['tt']  = report_trainingsessions_build_xls_format( $workbook, $sizebdy, $bold,    $colorttl, $fgcolorttl);

    // text formats
    $xls_formats['a0']  = report_trainingsessions_build_xls_format($workbook, $sizehd1, $bold,    $colorttl, $fgcolorttl);
    $xls_formats['a1']  = report_trainingsessions_build_xls_format($workbook, $sizehd1, $notbold, $colorhd1, $fgcolorhd1);
    $xls_formats['a2']  = report_trainingsessions_build_xls_format($workbook, $sizehd2, $notbold, $colorhd2, $fgcolorhd2);
    $xls_formats['a3']  = report_trainingsessions_build_xls_format($workbook, $sizehd3, $notbold, $colorhd3, $fgcolorhd3);
    $xls_formats['b']   = report_trainingsessions_build_xls_format($workbook, $sizebdy, $bold,    $colorbdy, $fgcolorbdy);
    $xls_formats['p']   = report_trainingsessions_build_xls_format($workbook, $sizebdy, $notbold, $colorbdy, $fgcolorbdy);

    // number formats
    $xls_formats['z']   = report_trainingsessions_build_xls_format($workbook, $sizebdy, $notbold, $colorbdy, $fgcolorbdy);

    // time formats
    $xls_formats['zt1'] = report_trainingsessions_build_xls_format($workbook, $sizehd1, $notbold, $colorhd1, $fgcolorhd1, $timefmt);
    $xls_formats['zt2'] = report_trainingsessions_build_xls_format($workbook, $sizehd2, $notbold, $colorhd2, $fgcolorhd2, $timefmt);
    $xls_formats['zt3'] = report_trainingsessions_build_xls_format($workbook, $sizehd3, $notbold, $colorhd3, $fgcolorhd3, $timefmt);
    $xls_formats['zt']  = report_trainingsessions_build_xls_format($workbook, $sizebdy, $notbold, $colorbdy, $fgcolorbdy, $timefmt);

    // date formats
    $xls_formats['zd']  = report_trainingsessions_build_xls_format($workbook, $sizebdy, $notbold, $colorbdy, $fgcolorbdy, $datefmt );

    // line-height formats (applying heights for different line types without any of the rest of the formatting)
    $xls_formats['_tt'] = report_trainingsessions_build_xls_format($workbook, $sizehd1, $notbold, $colorbdy, $fgcolorbdy);
    $xls_formats['_1']  = report_trainingsessions_build_xls_format($workbook, $sizehd1, $notbold, $colorbdy, $fgcolorbdy);
    $xls_formats['_2']  = report_trainingsessions_build_xls_format($workbook, $sizehd2, $notbold, $colorbdy, $fgcolorbdy);
    $xls_formats['_3']  = report_trainingsessions_build_xls_format($workbook, $sizehd3, $notbold, $colorbdy, $fgcolorbdy);

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
function report_trainingsessions_init_worksheet($userid, $startrow, &$xls_formats, &$workbook, $purpose = 'usertimes') {
    global $DB;

    $config = get_config('report_trainingsessions');
    $user = $DB->get_record('user', array('id' => $userid));

    if ($purpose == 'usertimes' || $purpose == 'allcourses') {
        if ($config->csv_iso) {
            $sheettitle = mb_convert_encoding(fullname($user), 'ISO-8859-1', 'UTF-8');
        } else {
            $sheettitle = fullname($user);
        }
    } else {
        if ($config->csv_iso) {
            $sheettitle = mb_convert_encoding(fullname($user), 'ISO-8859-1', 'UTF-8').' ('.get_string('sessions', 'report_trainingsessions').')';
        } else {
            $sheettitle = fullname($user).' ('.get_string('sessions', 'report_trainingsessions').')';
        }
    }

    $worksheet = $workbook->add_worksheet($sheettitle);
    if ($purpose == 'usertimes') {
        $worksheet->set_column(0,0,24);
        $worksheet->set_column(1,1,64);
        $worksheet->set_column(2,2,12);
        $worksheet->set_column(3,3,4);
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

    if ($purpose == 'usertimes' || $purpose == 'allcourses') {
        $worksheet->set_row($startrow - 1, 12, $xls_formats['tt']);
        $worksheet->write_string($startrow - 1, 0, get_string('firstaccess', 'report_trainingsessions'), $xls_formats['tt']);
        $worksheet->write_string($startrow - 1, 1, get_string('item', 'report_trainingsessions'), $xls_formats['tt']);
        $worksheet->write_string($startrow - 1, 2, get_string('elapsed', 'report_trainingsessions'), $xls_formats['tt']);
        if (!empty($config->showhits)) {
            $worksheet->write_string($startrow - 1, 3, get_string('hits', 'report_trainingsessions'), $xls_formats['tt']);
        }
    } else {
        $worksheet->write_string($startrow - 1, 0, get_string('sessionstart', 'report_trainingsessions'), $xls_formats['tt']);
        $worksheet->write_string($startrow - 1, 1, get_string('sessionend', 'report_trainingsessions'), $xls_formats['tt']);
        $worksheet->write_string($startrow - 1, 2, get_string('duration', 'report_trainingsessions'), $xls_formats['tt']);
    }

    return $worksheet;
}

/**
 * a raster for xls printing of a report structure header
 * with all the relevant data about a user.
 *
 * @package    report_trainingsessions
 * @category   report
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @version    moodle 2.x
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function report_trainingsessions_print_header_xls(&$worksheet, $userid, $courseid, $data, $xls_formats) {
    global $CFG, $DB;

    $config = get_config('report_trainingsessions');

    $user = $DB->get_record('user', array('id' => $userid));
    $course = $DB->get_record('course', array('id' => $courseid));

    $row = 0;

    $worksheet->set_row(0, 40, $xls_formats['t']);
    $worksheet->write_string($row, 0, get_string('sessionreports', 'report_trainingsessions'), $xls_formats['t']);
    $worksheet->merge_cells($row, 0, 0, 12);
    $row++;
    $worksheet->write_string($row, 0, get_string('user').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, fullname($user));
    $row++;
    $worksheet->write_string($row, 0, get_string('idnumber').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, $user->idnumber);
    $row++;
    $worksheet->write_string($row, 0, get_string('email').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, $user->email);
    $row++;
    $worksheet->write_string($row, 0, get_string('city').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, $user->city);
    $row++;
    $worksheet->write_string($row, 0, get_string('institution').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, $user->institution);
    $row++;
    $worksheet->write_string($row, 0, get_string('course', 'report_trainingsessions').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, format_string($course->fullname));
    $row++;
    $worksheet->write_string($row, 0, get_string('from').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, userdate($data->from));
    $row++;
    $worksheet->write_string($row, 0, get_string('to').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, userdate(time()));
    $row++;

    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

    // print group status
    $worksheet->write_string($row, 0, get_string('groups').' :', $xls_formats['b']);
    $str = '';
    if (!empty($usergroups)) {
        foreach ($usergroups as $group) {
            $str = $group->name;
            if ($group->id == groups_get_course_group($course)) {
                $str = "[$str]";
            }
            $groupnames[] = format_string($str);
        }
        $str = implode(', ', $groupnames);
    }

    $worksheet->write_string($row, 1, $str);
    $row++;

    $context = context_course::instance($courseid);
    $worksheet->write_string($row, 0, get_string('roles').' :', $xls_formats['b']);
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

    $worksheet->write_string($row, 0, get_string('done', 'report_trainingsessions'), $xls_formats['b']);
    $worksheet->write_string($row, 1, (0 + @$data->done).' '.get_string('over', 'report_trainingsessions'). ' '. (0 + @$data->items). ' ('.$completedpc.' %)');
    $row++;
    $worksheet->write_string($row, 0, get_string('elapsed', 'report_trainingsessions').' :', $xls_formats['b']);
    $worksheet->write_string($row, 1, report_trainingsessions_format_time((0 + @$data->elapsed), 'xlsd'), $xls_formats['p']);

    if (!empty($config->showhits)) {
        $row++;
        $worksheet->write_string($row, 0, get_string('hits', 'report_trainingsessions').' :', $xls_formats['b']);
        $worksheet->write_number($row, 1, (0 + @$data->events));
    }

    return $row;
}

/**
 * a raster for xls printing of a report structure.
 *
 */
function report_trainingsessions_print_xls(&$worksheet, &$structure, &$aggregate, &$done, &$row, &$xls_formats, $level = 1) {

    $config = get_config('report_trainingsessions');

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
            $res = report_trainingsessions_print_xls($worksheet, $element, $aggregate, $done, $row, $xls_formats, $level);
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
                $str = $indent.shorten_text(strip_tags($structure->name), 85);
                $worksheet->write_string($row, 1, $str, $format);

                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $done++;
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }

                $thisrow = $row; // saves the current row for post writing aggregates
                $row++;
                if (!empty($structure->subs)) {
                    // debug_trace("with subs");
                    $res = report_trainingsessions_print_xls($worksheet, $structure->subs, $aggregate, $done, $row, $xls_formats, $level + 1);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }

                $str = report_trainingsessions_format_time($dataobject->elapsed, 'xlsd');
                $worksheet->write_string($thisrow, 0, report_trainingsessions_format_time(@$aggregate[$structure->type][$structure->id]->firstaccess, 'xls'), $xls_formats['p']);
                $worksheet->write_string($thisrow, 2, $str, $xls_formats['p']);
                if (!empty($config->showhits)) {
                    $worksheet->write_number($thisrow, 3, $dataobject->events, $xls_formats['p']);
                }
            } else {
                // It is only a structural module that should not impact on level
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])) {
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $res = report_trainingsessions_print_xls($worksheet, $structure->subs, $aggregate, $done, $row, $xls_formats, $level);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }
            }
        }
    }
    return $dataobject;
}

// public wrapper for unified API
function report_trainingsessions_print_usersessions($worksheet, $userid, $row, $from, $to, &$course, &$xls_formats) {

    // Get data
    $logs = use_stats_extract_logs($from, $to, $userid, $course);
    $aggregate = use_stats_aggregate_logs($logs, 'module', 0, $from, $to);

    report_trainingsessions_print_sessions_xls($worksheet, $row, $aggregate['sessions'], $course, $xls_formats);
}

/**
 * print session table in an initialied worksheet
 * @param object $worksheet
 * @param int $row
 * @param array $sessions
 * @param object $course
 * @param object $xls_formats
 */
function report_trainingsessions_print_sessions_xls(&$worksheet, $row, $sessions, $courseorid, &$xls_formats) {
    global $CFG;

    if (is_object($courseorid)) {
        $courseid = $courseorid->id;
    } else {
        $courseid = $courseorid;
    }

    $config = get_config('report_traningsessions');
    if (!empty($config->enablelearningtimecheckcoupling)) {
        require_once($CFG->dirroot.'/report/learningtimecheck/lib.php');
        $ltcconfig = get_config('report_learningtimecheck');
    }

    $totalelapsed = 0;

    if (!empty($sessions)) {
        foreach ($sessions as $session) {

            if ($courseid && !array_key_exists($courseid, $session->courses)) {
                // Omit all sessions not visiting this course.
                continue;
            }

            // Fix eventual missing session end.
            if (!isset($session->sessionend) && empty($session->elapsed)) {
                // This is a "not true" session reliquate. Ignore it.
                continue;
            }

            // Fix all incoming sessions. possibly cropped by threshold effect.
            $session->sessionend = $session->sessionstart + $session->elapsed;

            $daysessions = report_trainingsessions_splice_session($session);

            foreach($daysessions as $s) {

                if (!empty($config->enablelearningtimecheckcoupling)) {

                    if (!empty($ltcconfig->checkworkingdays) || !empty($ltcconfig->checkworkinghours)) {
                        if (!empty($ltcconfig->checkworkingdays)) {
                            if (!report_learningtimecheck_is_valid($fakecheck)) {
                                continue;
                            }
                        }

                        if (!empty($ltcconfig->checkworkinghours)) {
                            if (!report_learningtimecheck_check_day($fakecheck, $ltcconfig)) {
                                continue;
                            }
        
                            report_learningtimecheck_crop_session($s, $ltcconfig);
                            if ($s->sessionstart && $s->sessionend) {
                                // Segment was not invalidated, possibly shorter than original.
                                $s->elapsed = $s->sessionend - $s->sessionstart;
                            } else {
                                // Croping results concluded into an invalid segment.
                                continue;
                            }
                        }
                    }
                }

                $worksheet->write_string($row, 0, report_trainingsessions_format_time(@$s->sessionstart, 'xls'), $xls_formats['p']);
                if (!empty($s->sessionend)) {
                    $worksheet->write_string($row, 1, report_trainingsessions_format_time(@$s->sessionend, 'xls'), $xls_formats['p']);
                }
                $worksheet->write_string($row, 2, format_time(0 + @$s->elapsed), $xls_formats['tt']);
                $worksheet->write_string($row, 3, report_trainingsessions_format_time(0 + @$s->elapsed, 'xlsd'), $xls_formats['p']);
                $totalelapsed += 0 + @$s->elapsed;
    
                $row++;
            }
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
function report_trainingsessions_print_allcourses_xls(&$worksheet, &$aggregate, $row, &$xls_formats) {
    global $CFG, $COURSE, $DB;

    $config = get_config('report_trainingsessions');

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
                    if (!$courses[$cid] = $DB->get_record('course', array('id' => $cid), 'id,idnumber,shortname,fullname,category')) {
                        // This course has gone away.
                        continue;
                    }
                    $courseids[$cid] = '';
                }

                $output[0 + @$courses[$cid]->category][$cid] = $cdata;
                $catids[0 + @$courses[$cid]->category] = '';
            } else {
                if (!isset($output[0][SITEID])) {
                    $output[0][SITEID] = new StdClass();
                    $output[0][SITEID]->elapsed = 0;
                    $output[0][SITEID]->events = 0;
                }
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
            $worksheet->write_string($row, 1, report_trainingsessions_format_time($output[0][SITEID]->elapsed, 'xlsd'), $xls_formats['p']);
            $row++;
            if (!empty($config->showhits)) {
                $worksheet->write_string($row, 0, $hitsstr, $xls_formats['p']);
                $worksheet->write_number($row, 1, $output[0][SITEID]->events, $xls_formats['z']);
                $row++;
            }
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
                    $worksheet->write_string($row, 1, report_trainingsessions_format_time($cdata->elapsed, 'xlsd'), $xls_formats['p']);
                    if (!empty($config->showhits)) {
                        $worksheet->write_number($row, 2, $cdata->events, $xls_formats['z']);
                    }
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
function report_trainingsessions_print_rawline_xls(&$worksheet, $data, $dataformats, $row, &$xls_formats) {

    for ($i = 0 ; $i < count($data) ; $i++) {

        if (!array_key_exists($dataformats[$i], $xls_formats)) {
            throw new Exception('Unknown XLS format '.$dataformats[$i]);
        }

        if (preg_match('/^z/', $dataformats[$i])) {
            if ($dataformats[$i] == 'z') {
                $celldata = $data[$i];
            } else {
                $celldata =  report_trainingsessions_format_time($data[$i], 'xls');
            }
            $worksheet->write_string($row, $i, $celldata, $xls_formats[$dataformats[$i]]);
        } else {
            $worksheet->write_string($row, $i, $data[$i], $xls_formats[$dataformats[$i]]);
        }
    }
    return ++$row;
}
