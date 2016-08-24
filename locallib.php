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

/**
 * Local functions for this module
 *
 * @package    report_trainingsessions
 * @category   report
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @version    moodle 2.x
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('TASK_SINGLE', 0);
define('TASK_REPLAY', 1);
define('TASK_SHIFT', 2);
define('TASK_SHIFT_TO', 3);

/**
 * decodes a course structure giving an ordered and
 * recursive image of the course.
 * The course structure will recognize topic, weekly and flexipage
 * course format, keeping an accurate image of the course ordering.
 *
 * @param int $courseid
 * @param reference $itemcount a recursive propagating counter in case of flexipage
 * or recursive content.
 * @return a complex structure representing the course organisation
 */
function report_trainingsessions_get_course_structure($courseid, &$itemcount) {
    global $CFG, $DB;

    $structure = array();

    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        print_error('errorbadcoursestructure', 'report_trainingsessions', $courseid);
    }

    if ($course->format == 'page') {
        include_once $CFG->dirroot.'/course/format/page/lib.php';
        include_once $CFG->dirroot.'/course/format/page/page.class.php';

        // get first top level page (contains course structure)
        $nestedpages = course_page::get_all_pages($courseid, 'nested');
        if (empty($nestedpages)) {
            print_error('errorcoursestructurefirstpage', 'report_trainingsessions');
        }

        // adapt structure from page format internal nested
        foreach ($nestedpages as $key => $page) {
            if (!($page->display > FORMAT_PAGE_DISP_HIDDEN)) {
                continue;
            }

            $pageelement = new StdClass;
            $pageelement->type = 'page';
            $pageelement->plugintype = 'page';
            $pageelement->name = format_string($page->nametwo);

            $pageelement->subs = page_get_structure_from_page($page, $itemcount);
            $structure[] = $pageelement;
        }
    } else if($course->format == 'flexsections') {
            trainingsessions_fill_structure_from_flexiblesections($structure, 0, $itemcount);
    } else {
        // browse through course_sections and collect course items.
        $structure = array();

        $maxsections = $DB->get_field('course_format_options', 'value', array('courseid' => $courseid, 'format' => $course->format, 'name' => 'numsections'));

        if ($sections = $DB->get_records('course_sections', array('course' => $courseid), 'section ASC')) {
            trainingsessions_fill_structure_from_sections($structure, $sections, $itemcount);
        }
    }

    return $structure;
}

/**
 * 
 * @global type $DB
 * @global type $COURSE
 * @param type $structure
 * @param type $parentid
 * @param type $itemcount
 * @return boolean
 */
function trainingsessions_fill_structure_from_flexiblesections(&$structure, $parentid, &$itemcount) {
    global $DB, $COURSE;

    $sql = "
        SELECT
            cs.*,
            cfo.value as parent
        FROM
            {course_sections} cs,
            {course_format_options} cfo
        WHERE
            cs.course = cfo.courseid AND
            cfo.name = 'parent' AND
            cs.id = cfo.sectionid AND
            cs.course = ? AND
            cfo.value = ?
    ";
    $sections = $DB->get_records_sql($sql, array($COURSE->id, $parentid));
    if ($sections) {
        foreach ($sections as $s) {
            $element = new StdClass;
            $element->type = 'section';
            $element->plugintype = 'section';
            $element->instance = $s;
            $element->instance->visible = $s->visible;
            $element->id = $s->id;
            //shall we try to capture any title in there ?
            if (preg_match('/<h[1-7][^>]*?>(.*?)<\\/h[1-7][^>]*?>/i', $s->summary, $matches)) {
                $element->name = $matches[1];
            } else {
                if ($s->section) {
                    $element->name = get_string('section').' '.$s->section ;
                } else {
                    $element->name = get_string('headsection', 'report_trainingsessions');
                }
            }
    
            if (!empty($s->sequence)) {
                $element->subs = array();
                $sequence = explode(",", $s->sequence);
                foreach ($sequence as $seq) {
                    if (!$cm = $DB->get_record('course_modules', array('id' => $seq))) {
                        // if (debugging()) notify("missing module of id $seq");
                        continue;
                    }
                    $module = $DB->get_record('modules', array('id' => $cm->module));
                    if (preg_match('/label$/', $module->name)) {
                        continue; // discard all labels
                    }
                    $moduleinstance = $DB->get_record($module->name, array('id' => $cm->instance));
                    $sub = new StdClass;
                    $sub->id = $cm->id;
                    $sub->plugintype = 'mod';
                    $sub->type = $module->name;
                    $sub->instance = $cm;
                    $sub->name = $moduleinstance->name;
                    $sub->visible = $cm->visible;
                    $element->subs[] = $sub;
                    $itemcount++;
                }
            }
            $subsections = array();
            trainingsessions_fill_structure_from_flexiblesections($subsections, $s->section, $itemcount);
            if (!empty($subsections)) {
                foreach ($subsections as $s) {
                    $element->subs[] = $s;
                }
            }
            $structure[] = $element;
        }
        return true;
    }
    // No subsections.
    return false;
}

/**
 * 
 * @global type $DB
 * @param type $structure
 * @param type $sections
 * @param type $itemcount
 */
function trainingsessions_fill_structure_from_sections(&$structure, $sections, &$itemcount) {
    global $DB;

    $sectioncount = 0;
    foreach ($sections as $section) {
        $element = new StdClass;
        $element->type = 'section';
        $element->plugintype = 'section';
        $element->instance = $section;
        $element->instance->visible = $section->visible;
        $element->id = $section->id;
        //shall we try to capture any title in there ?
        if (preg_match('/<h[1-7][^>]*?>(.*?)<\\/h[1-7][^>]*?>/i', $section->summary, $matches)) {
            $element->name = $matches[1];
        } else {
            if ($section->section) {
                $element->name = get_string('section').' '.$section->section ;
            } else {
                $element->name = get_string('headsection', 'report_trainingsessions') ;
            }
        }

        if (!empty($section->sequence)) {
            $element->subs = array();
            $sequence = explode(",", $section->sequence);
            foreach ($sequence as $seq) {
                if (!$cm = $DB->get_record('course_modules', array('id' => $seq))) {
                    // if (debugging()) notify("missing module of id $seq");
                    continue;
                }
                $module = $DB->get_record('modules', array('id' => $cm->module));
                if (preg_match('/label$/', $module->name)) {
                    continue; // discard all labels
                }
                $moduleinstance = $DB->get_record($module->name, array('id' => $cm->instance));
                $sub = new StdClass;
                $sub->id = $cm->id;
                $sub->plugintype = 'mod';
                $sub->type = $module->name;
                $sub->instance = $cm;
                $sub->name = $moduleinstance->name;
                $sub->visible = $cm->visible;
                $element->subs[] = $sub;
                $itemcount++;
            }
        }
        $structure[] = $element;
        $maxsections = $DB->get_field('course_format_options', 'value', array('courseid' => $COURSE->id, 'format' => $COURSE->format, 'name' => 'numsections'));
        if ($sectioncount == $maxsections) {
            // Do not go further, even if more sections are in database.
            break;
        }
        $sectioncount++;
    }
}

/**
 * get the complete inner structure for one page of a page menu.
 * Recursive function.
 *
 * @param record $page
 * @param reference $itemcount a recursive propagating counter in case of flexipage
 * or recursive content.
 */
function page_get_structure_from_page($page, &$itemcount) {
    global $VISITED_PAGES, $DB;

    if (!isset($VISITED_PAGES)) {
        $VISITED_PAGES = array();
    }

    if (in_array($page->id, $VISITED_PAGES)) {
        return;
    }
    $VISITED_PAGES[] = $page->id;

    $structure = array();

    // Get page items from first page. They are located in the center column.
    $select = " pageid = ? AND (position = 'c' OR position = 'r') ";
    $pageitems = $DB->get_records_select('format_page_items', $select, array($page->id), 'position, sortorder');

    // Analyses course content component stack.
    if ($pageitems) {
        foreach ($pageitems as $pi) {

            if (!$pi->cmid) {

                // Is a block.
                $b = $DB->get_record('block_instances', array('id' => $pi->blockinstance));
                if (!$b) {
                    continue;
                }
                $bp = $DB->get_record('block_positions', array('blockinstanceid' => $pi->blockinstance));
                $blockinstance = block_instance($b->blockname, $b);

                $element = new StdClass;
                $element->type = $b->blockname;
                $element->plugintype = 'block';
                $element->instance = $b;
                if ($bp) {
                    $element->instance->visible = $bp->visible * $pi->visible; // a block can be hidden by its page_module insertion.
                } else {
                    $element->instance->visible = $pi->visible;
                }
                $element->name = (!empty($blockinstance->config->title)) ? $blockinstance->config->title : '';
                $element->id = $b->id;
                // $itemcount++;

                // Tries to catch modules, pages or resources in content.
    
                $source = @$blockinstance->config->text;

                // If there is no subcontent, do not consider this bloc in reports.
                if ($element->subs = page_get_structure_in_content($source, $itemcount)) {
                    $structure[] = $element;
                }
            } else {
                // Is a module.
                $cm = $DB->get_record('course_modules', array('id' => $pi->cmid));
                $module = $DB->get_record('modules', array('id' => $cm->module));

                switch ($module->name) {
                    case 'customlabel':
                    case 'label':
                    case 'pagemenu':
                        break;
                    default:
                        $element = new StdClass;
                        $element->type = $module->name;
                        $element->plugin = 'mod';
                        $moduleinstance = $DB->get_record($module->name, array('id' => $cm->instance));
                        $element->name = $moduleinstance->name;
                        $element->instance = $cm;
                        $element->instance->visible = $element->instance->visible * $pi->visible; // a bloc can be hidden by its page_module insertion.
                        $element->id = $cm->id;
                        $structure[] = $element;
                        $itemcount++;
                }
            }
        }
    }

    if (!empty($page->childs)) {
        foreach ($page->childs as $key => $child) {
            if (!($child->display > FORMAT_PAGE_DISP_HIDDEN)) {
                continue;
            }

            $pageelement = new StdClass;
            $pageelement->type = 'page';
            $pageelement->name = format_string($child->nametwo);

            $pageelement->subs = page_get_structure_from_page($child, $itemcount);
            $structure[] = $pageelement;
        }
    }

    return $structure;
}

/**
 * get substructures hidden in content. this applies to content in HTML blocks that
 * may be inserted in page based formats. Not applicable to topic and weekly format.
 *
 * @param string $source the textual source code of the content
 * @param reference $itemcount a recursive propagating counter in case of flexipage
 * or recursive content.
 */
function page_get_structure_in_content($source, &$itemcount) {
    global $VISITED_PAGES, $DB;

    $structure = array();

    // get all links
    $pattern = '/href=\\"(.*)\\"/';
    preg_match_all($pattern, $source, $matches);
    if (isset($matches[1])) {
        foreach ($matches[1] as $href) {
            // Jump to another page.
            if (preg_match('/course\\/view.php\\?id=(\\d+)&page=(\\d+)/', $href, $matches)) {
                if (in_array($matches[2], $VISITED_PAGES)) {
                    continue;
                }
                $page = $DB->get_record('format_page', array('id' => $matches[2]));
                $element = new StdClass;
                $element->type = 'pagemenu';
                $element->plugin = 'mod';
                $element->subs = page_get_structure_from_page($page, $itemcount);
                $structure[] = $element;
                $VISITED_PAGES[] = $matches[2];
            }
            // Points a module.
            if (preg_match('/mod\\/([a-z_]+)\\/.*\\?id=(\\d+)/', $href, $matches)) {
                $element = new StdClass;
                $element->type = $matches[1];
                $element->plugin = 'mod';
                $module = $DB->get_record('modules', array('name' => $element->type));
                $cm = $DB->get_record('course_modules', array('id' => $matches[2]));
                $moduleinstance = $DB->get_record($element->type, array('id' => $cm->instance));
                $element->name = $moduleinstance->name;
                $element->instance = &$cm;
                $element->id = $cm->id;
                $structure[] = $element;
                $itemcount++;
            }
        }
    }

    return $structure;
}


/**
 * special time formating
 * xlsd stands for xls duration
 * @param type $timevalue
 * @param type $mode
 * @return string
 */
function report_trainingsessions_format_time($timevalue, $mode = 'html') {
    if ($timevalue) {
        if ($mode == 'html') {
            $secs = $timevalue % 60;
            $mins = floor($timevalue / 60);
            $hours = floor($mins / 60);
            $mins = $mins % 60;

            if ($hours > 0) return "{$hours}h {$mins}m {$secs}s";
            if ($mins > 0) return "{$mins}m {$secs}s";
            return "{$secs}s";
        } elseif($mode == 'xlsd') {
            $secs = $timevalue % 60;
            $mins = floor($timevalue / 60);
            $hours = floor($mins / 60);
            $mins = $mins % 60;

            if ($hours > 0) return "{$hours}h {$mins}m {$secs}s";
            if ($mins > 0) return "{$mins}m {$secs}s";
            return "{$secs}s";
        } else {
            // for excel time format we need have a fractional day value
            return userdate($timevalue, '%Y-%m-%d %H:%M:%S (%a)');
            // return  $timevalue / DAYSECS;
        }
    } else {
        if ($mode == 'html') {
            return get_string('unvisited', 'report_trainingsessions');
        }
        return '';
    }
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
    $xls_formats = array();
    // titles
    $xls_formats['t'] = $workbook->add_format();
    $xls_formats['t']->set_size(20);
    $xls_formats['tt'] = $workbook->add_format();
    $xls_formats['tt']->set_size(10);
    $xls_formats['tt']->set_color(1);
    $xls_formats['tt']->set_fg_color(4);
    $xls_formats['tt']->set_bold(1);

    // paragraphs
    $xls_formats['p'] = $workbook->add_format();
    $xls_formats['p']->set_size(10);
    $xls_formats['p']->set_bold(0);

    $xls_formats['pb'] = $workbook->add_format();
    $xls_formats['pb']->set_size(10);
    $xls_formats['pb']->set_bold(1);

    $xls_formats['a1'] = $workbook->add_format();
    $xls_formats['a1']->set_size(14);
    $xls_formats['a1']->set_fg_color(31);
    $xls_formats['a2'] = $workbook->add_format();
    $xls_formats['a2']->set_size(12);
    $xls_formats['a3'] = $workbook->add_format();
    $xls_formats['a3']->set_size(10);

    $xls_formats['z'] = $workbook->add_format();
    $xls_formats['z']->set_size(9);
    $xls_formats['zt'] = $workbook->add_format();
    $xls_formats['zt']->set_size(9);
    $xls_formats['zt']->set_num_format('[h]:mm:ss');
    $xls_formats['zd'] = $workbook->add_format();
    $xls_formats['zd']->set_size(9);
    $xls_formats['zd']->set_num_format('aaaa/mm/dd hh:mm');
    
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
 * A raster for printing in raw format with all the relevant data about a user. 
 * @param int $userid user to compile info for
 * @param int $courseid the course to compile reports in
 * @param objectref &$data input data to aggregate. Provides time information as 'elapsed" and 'weekelapsed' members.
 * @param string &$rawstr the output buffer reference. Column names come from outside.
 * @param int $from compilation start time
 * @param int $to compilation end time
 * @return void. $rawstr is appended by reference.
 */
function report_trainingsessions_print_globalheader_raw($userid, $courseid, &$data, &$rawstr, $from, $to) {
    global $COURSE, $DB;

    $config = get_config('report_trainingsessions');

    $user = $DB->get_record('user', array('id' => $userid));
    if ($courseid != $COURSE->id) {
        $course = $DB->get_record('course', array('id' => $courseid));
    } else {
        $course = &$COURSE;
    }

    $resultset = array();

    // group
    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

    if (!empty($usergroups)) {
        foreach($usergroups as $group) {
            $str = $group->name;
            if ($group->id == groups_get_course_group($course)) {
                $str = "$str";
            }
            $groupnames[] = $str;
        }
        $resultset[] = implode(', ', $groupnames); // entity
    } else {
        $resultset[] = get_string('outofgroup', 'report_trainingsessions'); // entity
    }

    // userid
    $resultset[] = $user->id;

    // lastname
    if (!empty($config->csv_iso)) {
        $namestr = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->lastname))), 'ISO-8859-1', 'UTF-8');
    } else {
        $namestr = strtoupper(trim(preg_replace('/\s+/', ' ', $user->lastname)));
    }
    $namestr = mb_ereg_replace('/é|è|ë|ê/', 'E', $namestr);
    $namestr = mb_ereg_replace('/ä|a/', 'A', $namestr);
    $namestr = mb_ereg_replace('/ç/', 'C', $namestr);
    $namestr = mb_ereg_replace('/ü|ù|/', 'U', $namestr);
    $namestr = mb_ereg_replace('/î/', 'I', $namestr);
    $resultset[] = $namestr;

    // firstname
    if (!empty($config->csv_iso)) {
        $namestr = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->firstname))), 'ISO-8859-1', 'UTF-8');
    } else {
        $namestr = strtoupper(trim(preg_replace('/\s+/', ' ', $user->firstname)));
    }
    $namestr = mb_ereg_replace('/é|è|ë|ê/', 'E', $namestr);
    $namestr = mb_ereg_replace('/ä|a/', 'A', $namestr);
    $namestr = mb_ereg_replace('/ç/', 'C', $namestr);
    $namestr = mb_ereg_replace('/ü|ù|/', 'U', $namestr);
    $namestr = mb_ereg_replace('/î/', 'I', $namestr);
    $resultset[] = $namestr;

    // first enrol date
    $firstenroll = $DB->get_field_select('user_enrolments', 'MIN(timestart)', " timestart != 0 AND userid = ? ", array($user->id));
    $resultset[] = ($firstenroll) ? date('d/m/Y', $firstenroll) : '' ; // from date

    // first login date
    $firstlogin = $DB->get_field_select('log', 'MIN(time)', " userid = ? AND action = 'login' ", array($user->id));
    $resultset[] = ($firstlogin) ? date('d/m/Y', $firstlogin) : '' ; // firstlogin

    // last login date
    $lastlogin = $DB->get_field_select('log', 'MAX(time)', " userid = ? AND action = 'login' ", array($user->id));
    $resultset[] = ($lastlogin) ? date('d/m/Y', $lastlogin) : '' ; // firstlogin

    // Report from
    $resultset[] = date('d/m/Y', $from); // from date

    // report to
    $resultset[] = date('d/m/Y', $to); // to date

    // last week start day
    $resultset[] = date('d/m/Y', $to - DAYSECS * 7); // last week of period

    // time
    $resultset[] = report_trainingsessions_format_time(0 + @$data->elapsed, 'xlsd'); // elapsed time

    // time in last week
    $resultset[] = report_trainingsessions_format_time(@$data->weekelapsed, 'xlsd'); // elapsed time this week

    // add grades
    report_trainingsessions_add_graded_data($resultset, $userid);

    // $context = context_course::instance($courseid);
    // $roles = get_user_roles_in_context($userid, $context);
    // $resultset[] = $roles;

    if (!empty($config->csv_iso)) {
        $rawstr .= mb_convert_encoding(implode(';', $resultset)."\n", 'ISO-8859-1', 'UTF-8');
    } else {
        $rawstr .= implode(';', $resultset)."\n";
    }
}

/**
 * Local query to get course users.
 * // TODO check if yet usefull before delete
 */
function report_trainingsessions_get_course_users($courseid) {
    global $DB;

    $sql = "SELECT
                DISTINCT u.id, ".get_all_user_name_fields(true, 'u')."
            FROM
                {user} u
            JOIN
                {user_enrolments} ue 
            ON
                ue.userid = u.id
            JOIN
                {enrol} e 
               ON
                   e.id = ue.enrolid
            ORDER BY
                u.firstname ASC,
                u.lastname ASC
    ";

    $users = $DB->get_records_sql($sql, null);

    return $users;
}

/**
 * 
 * @global type $DB
 * @param type $courseid
 * @return type
 */
function report_trainingsessions_get_graded_modules($courseid) {
    global $DB;

    return $DB->get_records_select_menu('report_trainingsessions', "courseid = ? AND moduleid != 0", array($courseid), 'sortorder', 'id, moduleid');
}

/**
 * Get all graded modules into the course excluing those already linked to report.
 */
function report_trainingsessions_get_linkable_modules($courseid) {
    $modinfo = get_fast_modinfo($courseid);
    
    $cms = $modinfo->get_cms();
    $linkables = array(0 => get_string('disabled', 'report_trainingsessions'));
    foreach ($cms as $cminfo) {
        $linkables[$cminfo->id] = '['.$cminfo->modname.'] '.$cminfo->name;
    }
    return $linkables;
}

/**
 * Add extra column headers from grade settings.
 * @param arrayref &$columns a reference to the array of column headings.
 * @return void
 */
function report_trainingsessions_add_graded_columns(&$columns, &$formats = null, &$dformats = null) {
    global $DB, $COURSE;

    $coursemodinfo = get_fast_modinfo($COURSE->id);

    if ($graderecs = $DB->get_records('report_trainingsessions', array('courseid' => $COURSE->id), 'sortorder')) {
        $formatadds = array();
        $dformatadds = array();
        foreach ($graderecs as $rec) {
            if ($rec->moduleid) {
                // push in array
                $cminfo = $coursemodinfo->get_cm($rec->moduleid);
                $modlabel = (empty($rec->label)) ? (($cminfo->idnumber) ? $cminfo->idnumber : $cminfo->modulename.$cminfo->instance) : $rec->label;
                array_push($columns, $modlabel);
                $formatadds[] = 'p';
                $dformatadds[] = 'a2';
            } else {
                // Retain for adding at the full end of array.
                $courselabel = (empty($rec->label)) ? (($COURSE->idnumber) ? $COURSE->idnumber : $COURSE->shortname) : $rec->label;
                $last = $courselabel;
            }
        }

        if (isset($last)) {
            $columns[] = $last;
            $formatadds[] = 'p';
            $dformatadds[] = 'a2';
        }

        if (!is_null($formats)) {
            $formats = array_merge($formats, $formatadds);
        }

        if (!is_null($dformats)) {
            $dformats = array_merge($dformats, $dformatadds);
        }
    }
}

/**
 * Fetch scores and aggregate them to results.
 * @param arrayref &$columns a reference to the array of report values.
 * @return void
 */
function report_trainingsessions_add_graded_data(&$columns, $userid) {
    global $DB, $COURSE;

    if ($graderecs = $DB->get_records('report_trainingsessions', array('courseid' => $COURSE->id), 'sortorder')) {
        $havecoursegrade = false;
        foreach ($graderecs as $rec) {
            if ($rec->moduleid) {

                $modulegrade = report_trainingsessions_get_module_grade($rec->moduleid, $userid);
                // push in array
                array_push($columns, $modulegrade);
            } else {
                // Retain the coursegrade for adding at the full end of array.
                $havecoursegrade = true;
                $coursegrade = report_trainingsessions_get_course_grade($rec->courseid, $userid);
            }
        }

        if ( havecoursegrade === true ) {
            array_push($columns, $coursegrade);
        }
    }
}

/**
 * Gets the final course grade in gradebook.
 * @param int $courseid
 * @param int $userid
 * @return int the grade, or empty value
 */
function report_trainingsessions_get_course_grade($courseid, $userid) {
    global $DB;

    $sql = "
        SELECT
            g.finalgrade as grade
        FROM
            {grade_items} gi,
            {grade_grades} g
        WHERE
            g.userid = ? AND
            gi.itemtype = 'course' AND
            gi.courseid = ? AND
            g.itemid = gi.id
    ";
    $result = $DB->get_record_sql($sql, array($userid, $courseid));

    if ($result) {
        return $result->grade;
    }
    return '';
}

/**
 * Gets a final grade for a specific course module if exists
 * @param int $moduleid the course module ID
 * @param int $userid
 * @return the grade or empty value.
 */
function report_trainingsessions_get_module_grade($moduleid, $userid) {
    global $DB, $COURSE;

    $modinfo = get_fast_modinfo($COURSE->id);
    $cm = $modinfo->get_cm($moduleid);

    $sql = "
        SELECT
            g.finalgrade as grade
        FROM
            {grade_items} gi,
            {grade_grades} g
        WHERE
            g.userid = ? AND
            gi.itemtype = 'mod' AND
            gi.itemmodule = ? AND
            gi.iteminstance = ? AND
            g.itemid = gi.id
    ";
    $result = $DB->get_record_sql($sql, array($userid, $cm->modname, $cm->id));

    if ($result) {
        return $result->grade;
    }
    return '';
}

/**
 * Given a prefed tzarget list of users from a previous selection, discard users
 * that should not appear in reports
 * @param arrayref &$targetusers an array of selected users to filter out.
 * @return void
 */
function report_trainingsessions_filter_unwanted_users(&$targetusers, $course) {
    $context = context_course::instance($course->id);

    foreach ($targetusers as $uid => $unused) {
        if (!has_capability('report/trainingsessions:iscompiled', $context, $uid, false)) {
            unset($targetusers[$uid]);
        }
    }
}

/**
 * 
 * @global type $CFG
 * @global type $USER
 * @return type
 */
function report_trainingsessions_back_office_get_ticket() {
    global $CFG, $USER;

    if (file_exists($CFG->dirroot.'/auth/ticket/lib.php')) {
        include_once($CFG->dirroot.'/auth/ticket/lib.php');
        return ticket_generate($USER, 'trainingsessions_generator', me(), 'des');
    }
}

/**
 * Controls access to script with valid interactive session OR
 * non interactive token when batch is in progress.
 */
function report_trainingsessions_back_office_access($course = null) {
    global $CFG;

    $securitytoken = optional_param('ticket', '', PARAM_RAW);
    if (!empty($securitytoken)) {
        if (file_exists($CFG->dirroot.'/auth/ticket/lib.php')) {
            include_once($CFG->dirroot.'/auth/ticket/lib.php');
            if (!ticket_decodeTicket($securitytoken)) {
                die('Access is denied by Ticket Auth');
            }
        } else {
            die('Ticket presented but no library for it');
        }
    } else {
        if (!is_null($course)) {
            require_login($course);
            $context = context_course::instance($course->id);
            require_capability('report/trainingsessions:viewother', $context);
        } else {
            require_login();
            $context = context_system::instance();
            require_capability('report/trainingsessions:viewother', $context);
        }
    }
}

/**
 * 
 * @param type $sessions
 * @param type $courseid
 * @return int
 */
function report_trainingsessions_count_sessions_in_course(&$sessions, $courseid) {
    $count = 0;

    if (!empty($sessions)) {
        foreach ($sessions as $s) {

            if (!isset($s->sessionend) && empty($s->elapsed)) {
                // This is a "not true" session reliquate. Ignore it.
                continue;
            }

            if (empty($s->courses)) {
                continue;
            }

            if ($courseid) {
                if (in_array($courseid, $s->courses)) {
                    $count++;
                }
            } else {
                $count++;
            }
        }
    }
    return $count;
}

/**
 * 
 * @param type $user
 * @param type $id
 * @param type $from
 * @param type $to
 * @param type $timesession
 * @param type $uri
 * @param type $filerec
 * @param type $reportscope
 * @return type
 */
function report_trainingsessions_process_user_file($user, $id, $from, $to, $timesession, $uri, $filerec = null, $reportscope = 'currentcourse') {
    mtrace('Compile_users for user : '.fullname($user)."<br/>\n");

    $fs = get_file_storage();

    $rqfields = array();
    $rqfields[] = 'id='.$id;
    $rqfields[] = 'from='.$from;
    $rqfields[] = 'to='.$to;
    $rqfields[] = 'userid='.$user->id;
    $rqfields[] = 'timesession='.$timesession;
    $rqfields[] = 'scope='.$reportscope;
    $rqfields[] = 'ticket='.report_trainingsessions_back_office_get_ticket();

    $rq = implode('&', $rqfields);

    $ch = curl_init($uri.'?'.$rq);
    debug_trace("Firing url : {$uri}?{$rq}<br/>\n");
    if (debugging()) {
        mtrace('Calling : '.$uri.'?'.$rq."<br/>\n");
        mtrace('direct link : <a href="'.$uri.'?'.$rq."\">Generate direct single doc</a><br/>\n");
    }

    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle Report Batch');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rq);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $raw = curl_exec($ch);

    // check for curl errors
    $curlerrno = curl_errno($ch);
    if ($curlerrno != 0) {
        debugging("Request for <a href=\"{$uri}?{$rq}\">User {$user->id}</a> failed with curl error $curlerrno");
    }

    // check HTTP error code
    $info =  curl_getinfo($ch);
    if (!empty($info['http_code']) && ($info['http_code'] != 200) && ($info['http_code'] != 303)) {
        debugging("Request for <a href=\"{$uri}?{$rq}\">User {$user->id}</a> failed with HTTP code ".$info['http_code']);
    } else {
        if (!is_null($filerec)) {
            // feed pdf result in file storage.
            $oldfile = $fs->get_file($filerec->contextid, $filerec->component, $filerec->filearea, $filerec->itemid, $filerec->filepath, $filerec->filename);
            if ($oldfile) {
                // clean old file before.
                $oldfile->delete();
            }
            $newfile = $fs->create_file_from_string($filerec, $raw);
    
            $createdurl = moodle_url::make_pluginfile_url($filerec->contextid, $filerec->component, $filerec->filearea, $filerec->itemid, $filerec->filepath, $filerec->filename);
            mtrace('Result : <a href="'.$createdurl.'" >'.$filerec->filename."</a><br/>\n");
        } else {
            return $raw;
        }
    }

    curl_close($ch);
}

/**
 * 
 * @param type $group
 * @param type $id
 * @param type $from
 * @param type $to
 * @param type $timesession
 * @param type $uri
 * @param type $filerec
 * @param type $reportscope
 * @return type
 */
function report_trainingsessions_process_group_file($group, $id, $from, $to, $timesession, $uri, $filerec = null, $reportscope = 'currentcourse') {
    mtrace('Compile_users for group : '.$group->name."<br/>\n");

    $fs = get_file_storage();

    $rqfields = array();
    $rqfields[] = 'id='.$id;
    $rqfields[] = 'from='.$from;
    $rqfields[] = 'to='.$to;
    $rqfields[] = 'groupid='.$group->id;
    $rqfields[] = 'timesession='.$timesession;
    $rqfields[] = 'scope='.$reportscope;
    $rqfields[] = 'ticket='.report_trainingsessions_back_office_get_ticket();

    $rq = implode('&', $rqfields);

    $ch = curl_init($uri.'?'.$rq);
    debug_trace("Firing url : {$uri}?{$rq}<br/>\n");
    if (debugging()) {
        mtrace('Calling : '.$uri.'?'.$rq."<br/>\n");
        mtrace('direct link : <a href="'.$uri.'?'.$rq."\">Generate direct single doc</a><br/>\n");
    }

    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle Report Batch');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rq);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $raw = curl_exec($ch);

    // check for curl errors
    $curlerrno = curl_errno($ch);
    if ($curlerrno != 0) {
        debugging("Request for <a href=\"{$uri}?{$rq}\">Group {$group->id}</a> failed with curl error $curlerrno");
    }

    // check HTTP error code
    $info =  curl_getinfo($ch);
    if (!empty($info['http_code']) && ($info['http_code'] != 200) && ($info['http_code'] != 303)) {
        debugging("Request for <a href=\"{$uri}?{$rq}\">Group {$group->id}</a> failed with HTTP code ".$info['http_code']);
    } else {
        if (!is_null($filerec)) {
            // feed xls result in file storage.
            $oldfile = $fs->get_file($filerec->contextid, $filerec->component, $filerec->filearea, $filerec->itemid, $filerec->filepath, $filerec->filename);
            if ($oldfile) {
                // clean old file before.
                $oldfile->delete();
            }
            $newfile = $fs->create_file_from_string($filerec, $raw);
    
            $createdurl = moodle_url::make_pluginfile_url($filerec->contextid, $filerec->component, $filerec->filearea, $filerec->itemid, $filerec->filepath, $filerec->filename);
            mtrace('Result : <a href="'.$createdurl.'" >'.$filerec->filename."</a><br/>\n");
        } else {
            return $raw;
        }
    }

    curl_close($ch);
}

/**
 * 
 * @param type $courseid
 * @param type $groupid
 * @param type $range
 * @return type
 */
function report_trainingsessions_compute_groups($courseid, $groupid, $range) {

    // If no groups existing, get all course
    $groups = groups_get_all_groups($courseid);
    if (!$groups && !$groupid) {
        $groups = array();
        $group = new StdClass;
        $group->id = 0;
        $group->name = get_string('course');
        if ($range == 'user') {
            $context = context_course::instance($courseid);
            $group->target = get_enrolled_users($context);
        }
        $groups[] = $group;
    } elseif ($groups && !$groupid) {
        if ($range == 'user') {
            foreach ($groups as $group) {
                $group->target = groups_get_members($group->id);
            }
        }
    } else {
        // Only one group. Reduce group list to this group.
        if ($range == 'user') {
            $group = $groups[$groupid];
            $group->target = groups_get_members($groupid);
            $groups = array();
            $groups[] = $group;
        }
    }
    return $groups;
}

/**
 * Given a session that might overpass day boundaries, splice into single day sessions.
 * @see report_learningtimecheck for similar implementation and full unit tests.
 * @param object $session a session object with sessionstart, sessionend and elapsed members.
 */
function report_trainingsessions_splice_session($session) {
    $daytimestart = date('G', $session->sessionstart) * HOURSECS + date('i', $session->sessionstart) * MINSECS + date('s', $session->sessionstart);
    $endofday = 24 * HOURSECS;
    $daygap = $endofday - $daytimestart;
    $startstamp = $session->sessionstart;

    $sessions = array();

    while ($startstamp + $daygap < $session->sessionend) {
        $daysess = new StdClass();
        $daysess->sessionstart = $startstamp;
        $daysess->sessionend = $startstamp + $daygap;
        $daysess->courses = $session->courses;
        $daysess->elapsed = $daygap;
        $daytimestart = 0; // back to midnight;
        $daygap = $endofday - $daytimestart;
        $startstamp = $daysess->sessionend;
        $sessions[] = $daysess;
    }

    // We now need to keep the last segment
    if ($startstamp < $session->sessionend) {
        $daysess = new stdClass();
        $daysess->sessionstart = $startstamp;
        $daysess->sessionend = $session->sessionend;
        $daysess->courses = $session->courses;
        $daysess->elapsed = $session->sessionend - $daysess->sessionstart;
        $sessions[] = $daysess;
    }

    return $sessions;
}

/**
 * Gives the available format options.
 */
function report_trainingsessions_get_batch_formats() {
    global $CFG;

    $formatoptions = array(
        'xls' => get_string('xls', 'report_trainingsessions'),
        'csv' => get_string('csv', 'report_trainingsessions'),
    );

    if (!is_dir($CFG->dirroot.'/local/vflibs')) {
        $formatoptions['pdf'] = get_string('pdf', 'report_trainingsessions');
    }

    return $formatoptions;
}
