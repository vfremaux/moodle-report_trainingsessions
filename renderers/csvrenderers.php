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
 * json data format
 *
 * @package     report_trainingsessions
 * @category    report
 * @copyright   Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report\trainingsessions;

defined('MOODLE_INTERNAL') || die;

class CsvRenderer {

    protected $rt;

    public function __construct($rt) {
        $this->rt = $rt;
    }

    public function print_userinfo(&$csvbuffer, $user) {

        $str = '#'."\n";
        $str .= '# ln: '.$user->lastname."\n";
        $str .= '# fn: '.$user->firstname."\n";
        $str .= '# ID: '.$user->idnumber."\n";
        $str .= '#'."\n";

        $csvbuffer .= $str;
    }

    public function print_header(&$csvbuffer) {

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

    public function print_course_structure(&$csvbuffer, &$structure, &$aggregate) {
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
                $this->print_course_structure($csvbuffer, $element, $aggregate);
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
                            $firstaccess = @$aggregate[$structure->type][$structure->id]->firstaccess;
                            $dataline[2] = $this->rt->format_time($firstaccess, 'xls');
                            $elapsed = @$aggregate[$structure->type][$structure->id]->elapsed;
                            $dataline[3] = $this->rt->format_time($elapsed, 'html');
                            $dataline[4] = $structure->events;
                        } else {
                            $firstaccess = @$aggregate[$structure->type][$structure->id]->firstaccess;
                            $dataline[2] = $this->rt->format_time($firstaccess, 'xls');
                            $elapsed = @$aggregate[$structure->type][$structure->id]->elapsed;
                            $dataline[3] = $this->rt->format_time($elapsed, 'html');
                        }

                        $csvbuffer .= implode($config->csvseparator, $dataline)."\n";
                    }

                    if (!empty($structure->subs)) {
                        $this->print_course_structure($csvbuffer, $structure->subs, $aggregate);
                    }
                }
            }
        }
    }

    /**
     * A raster for printing in raw format with all the relevant data about a user.
     * @param int $courseid the course to compile reports in
     * @param arrayref &$cols the course to compile reports in
     * @param objectref &$user user to compile info for
     * @param objectref &$data input data to aggregate. Provides time information as 'elapsed" and 'weekelapsed' members.
     * @param string &$rawstr the output buffer reference. Column names come from outside.
     * @param int $from compilation start time
     * @param int $to compilation end time
     * @return void. $rawstr is appended by reference.
     */
    public function print_global_raw($courseid, &$cols, &$user, &$aggregate, &$weekaggregate, &$rawstr, $dataformats) {

        $config = get_config('report_trainingsessions');
        $datetimefmt = get_string('strfdatetime', 'report_trainingsessions');

        $colsdata = $this->rt->map_summary_cols($cols, $user, $aggregate, $weekaggregate, $courseid, false /* non associative */);

        $i = 0;
        foreach ($colsdata as &$data) {
            if ($dataformats[$i] == 'd') {
                if ($data > 0) {
                    if ($data > 100000000) {
                        // Is very likely a date.
                        $data = $this->rt->format_time($data, 'htmld');
                    } else {
                        $data = sprintf('%02d:%02d:%02d', ($data / 3600), ($data / 60) % 60, $data % 60);
                    }
                }
            }
            if ($dataformats[$i] == 't') {
                if ($data > 0) {
                    $data = $this->rt->format_time($data, 'html');
                }
            }
            $i++;
        }

        $this->rt->add_graded_columns($cols, $unusedtitles, $unusedformats);

        // Add grades.
        $this->rt->add_graded_data($colsdata, $user->id, $aggregate);

        if (!empty($config->csv_iso)) {
            $rawstr .= mb_convert_encoding(implode(';', $colsdata)."\n", 'ISO-8859-1', 'UTF-8');
        } else {
            $rawstr .= implode(';', $colsdata)."\n";
        }
    }

    /**
     * A raster for printing in raw format with all the relevant data about a user.
     * @param int $courseid the course to compile reports in
     * @param arrayref &$cols the course to compile reports in
     * @param objectref &$user user to compile info for
     * @param objectref &$data input data to aggregate. Provides time information as 'elapsed" and 'weekelapsed' members.
     * @param string &$rawstr the output buffer reference. Column names come from outside.
     * @param int $from compilation start time
     * @param int $to compilation end time
     * @return void. $rawstr is appended by reference.
     */
    public function print_row(&$colsdata, &$rawstr) {

        $config = get_config('report_trainingsessions');

        if (!empty($config->csv_iso)) {
            $rawstr .= mb_convert_encoding(implode(';', $colsdata)."\n", 'ISO-8859-1', 'UTF-8');
        } else {
            $rawstr .= implode(';', $colsdata)."\n";
        }
    }

    /**
     * Prints the CSV column title headers from all settings defined cols and grade cols.
     * @param $stringref $csvbuffer the csv document buffer
     */
    public function print_global_header(&$csvbuffer) {

        $config = get_config('report_trainingsessions');

        $colskeys = $this->rt->get_summary_cols();
        $this->rt->add_graded_columns($colskeys, $footitles);

        if (!empty($config->csv_iso)) {
            $csvbuffer = mb_convert_encoding(implode(';', $colskeys)."\n", 'ISO-8859-1', 'UTF-8');
        } else {
            $csvbuffer = implode(';', $colskeys)."\n";
        }
    }

    public function print_session_header(&$csvbuffer) {

        $colheads = array(
            'sessionstart',
            'sessionend',
            'elapsedsecs',
            'elapsedsecs'
        );

        $csvbuffer = implode(';', $colheads)."\n";
    }

    /**
     * print session table in an initialied worksheet
     * @param object $worksheet
     * @param int $row
     * @param array $sessions
     * @param object $course
     * @param object $xlsformats
     */
    public function print_usersessions(&$csvbuffer, $userid, $courseorid, $from, $to, $id) {
        global $CFG, $DB;

        if (is_object($courseorid)) {
            $course = $courseorid;
        } else {
            $course = $DB->get_record('course', array('id' => $courseorid));
        }

        // Get data.
        $logs = use_stats_extract_logs($from, $to, $userid, $course);
        $aggregate = use_stats_aggregate_logs($logs, $from, $to);

        if (report_trainingsessions_supports_feature('calculation/coupling')) {
            $hasltc = false;
            if (file_exists($CFG->dirroot.'/report/learningtimecheck/lib.php')) {
                $config = get_config('report_traningsessions');
                if (!empty($config->enablelearningtimecheckcoupling)) {
                    require_once($CFG->dirroot.'/report/learningtimecheck/lib.php');
                    $ltcconfig = get_config('report_learningtimecheck');
                    $hasltc = true;
                }
            }
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

                foreach ($daysessions as $s) {

                    if ($hasltc && !empty($config->enablelearningtimecheckcoupling)) {

                        $startfakecheck = new StdClass;
                        $startfakecheck->userid = $userid;
                        $startfakecheck->usertimestamp = $session->sessionstart;

                        $endfakecheck = new StdClass;
                        $endfakecheck->userid = $userid;
                        $endfakecheck->usertimestamp = $session->sessionend;

                        if (!empty($ltcconfig->checkworkingdays) || !empty($ltcconfig->checkworkinghours)) {
                            if (!empty($ltcconfig->checkworkingdays)) {
                                $startisvalid = report_learningtimecheck::is_valid($startfakecheck);
                                $endisvalid = report_learningtimecheck::is_valid($endfakecheck);
                                if (!$startisvalid && !$endisvalid) {
                                    // Session start nor end are in a workingday day.
                                    continue;
                                }
                            }

                            if (!empty($ltcconfig->checkworkinghours)) {
                                $startdaycheck = report_learningtimecheck::check_day($startfakecheck, $ltcconfig);
                                $enddaycheck = report_learningtimecheck::check_day($startfakecheck, $ltcconfig);
                                if (!$startdaycheck && !$enddaycheck) {
                                    // Session start nor end are in a valid day.
                                    continue;
                                }

                                report_learningtimecheck::crop_session($s, $ltcconfig);
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

                    $dataline[] = $this->rt->format_time(@$s->sessionstart, 'html');
                    if (!empty($s->sessionend)) {
                        $dataline[] = $this->rt->format_time(@$s->sessionend, 'html');
                    } else {
                        $dataline[] = '';
                    }
                    $dataline[] = $s->elapsed;
                    $dataline[] = $this->rt->format_time(0 + @$s->elapsed, 'html');
                    $totalelapsed += 0 + @$s->elapsed;

                    $csvbuffer .= implode(';', $dataline)."\n";
                }
            }
        }
        return $totalelapsed;
    }
}