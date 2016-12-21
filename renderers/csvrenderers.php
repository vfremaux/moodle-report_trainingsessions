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
