<?php
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
 * json data format
 *
 * @package     report_trainingsessions
 * @category    report
 * @copyright   Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function report_trainingsessions_print_userinfo(&$csvbuffer, $user) {

    $str = '#'."\n";
    $str .= '# ln: '.$user->lastname."\n";
    $str .= '# fn: '.$user->firstname."\n";
    $str .= '# ID: '.$user->idnumber."\n";
    $str .= '#'."\n";

    $csvbuffer .= $str;
}

function report_trainingsessions_print_header(&$csvbuffer) {

    $config = get_config('report_trainingsessions');

    $headerline = array();
    $headerline[] = 'section';
    $headerline[] = 'plugin';
    $headerline[] = 'firstaccess';
    $headerline[] = 'elapsed';
    if (!empty($config->showhits)) {
        $headerline[] = 'events';
    }

    $csvbuffer .= implode($config->csvseparator, $headerline)."\n";
}

function report_trainingsessions_print_course_structure(&$csvbuffer, &$structure, &$aggregate) {
    static $currentstructure = '';

    $config = get_config('report_trainingsessions');

    if (empty($structure)) {
        $csvbuffer = get_string('nostructure', 'report_trainingsessions');
        return;
    }

    if (is_array($structure)) {
        // Recurse in sub structures.
        foreach ($structure as $element) {
            if (isset($element->instance) && empty($element->instance->visible)) {
                // Non visible items should not be displayed.
                continue;
            }
            if (!empty($config->hideemptymodules) && empty($element->elapsed) && empty($element->events)) {
                // Discard empty items.
                continue;
            }
            report_trainingsessions_print_course_structure($csvbuffer, $element, $aggregate);
        }
    } else {
        // Prints a single row.
        if (!isset($structure->instance) || !empty($structure->instance->visible)) {
            // Non visible items should not be displayed.
            if (!empty($structure->name)) {
                // Write element title.
                // TODO : Check how to force spanning on title.
                $dataline = array();
                if (($structure->plugintype == 'page') || ($structure->plugintype == 'section')) {
                    $currentstructure = $structure->name;
                } else {
                    // True activity.
                    $dataline = array();
                    $dataline[0] = $currentstructure;
                    $dataline[1] = shorten_text(get_string('pluginname', $structure->type), 40);
                    if (!empty($config->showhits)) {
                        $dataline[2] = report_trainingsessions_format_time(@$aggregate[$structure->type][$structure->id]->firstaccess, 'xls');
                        $dataline[3] = report_trainingsessions_format_time(@$aggregate[$structure->type][$structure->id]->elapsed, 'html');
                        $dataline[4] = $structure->events;
                    } else {
                        $dataline[2] = report_trainingsessions_format_time(@$aggregate[$structure->type][$structure->id]->firstaccess, 'xls');
                        $dataline[3] = report_trainingsessions_format_time(@$aggregate[$structure->type][$structure->id]->elapsed, 'html');
                    }

                    $csvbuffer .= implode($config->csvseparator, $dataline)."\n";
                }

                if (!empty($structure->subs)) {
                    report_trainingsessions_print_course_structure($csvbuffer, $structure->subs, $aggregate);
                }
            }
        }
    }
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
function report_trainingsessions_print_global_raw($courseid, &$cols, &$user, &$aggregate, &$weekaggregate, &$rawstr) {
    global $COURSE, $DB;

    $config = get_config('report_trainingsessions');

    $colsdata = report_trainingsessions_map_summary_cols($cols, $user, $aggregate, $weekaggregate, $courseid);

    // Add grades.
    report_trainingsessions_add_graded_data($colsdata, $user->id, $aggregate);

    if (!empty($config->csv_iso)) {
        $rawstr .= mb_convert_encoding(implode(';', $colsdata)."\n", 'ISO-8859-1', 'UTF-8');
    } else {
        $rawstr .= implode(';', $colsdata)."\n";
    }
}

function report_trainingsessions_print_global_header(&$csvbuffer) {

    $config = get_config('report_trainingsessions');

    $colskeys = report_trainingsessions_get_summary_cols();

    report_trainingsessions_add_graded_columns($colskeys, $footitles);

    if (!empty($config->csv_iso)) {
        $csvbuffer = mb_convert_encoding(implode(';', $colskeys)."\n", 'ISO-8859-1', 'UTF-8');
    } else {
        $csvbuffer = implode(';', $colskeys)."\n";
    }
}