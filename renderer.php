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
 * The standard renderer of the component
 *
 * @package    report_trainingsessions
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class report_trainingsessions_renderer extends plugin_renderer_base {

    /**
     *
     * @param type $course
     * @param type $view
     * @param type $from
     * @param type $to
     * @return type
     */
    function tabs($course, $view, $from, $to) {

        $context = context_course::instance($course->id);
        $rows = array();
        // Print tabs with options for user.
        $params = array('id' => $course->id, 'view' => 'user', 'from' => $from, 'to' => $to);
        $userurl = new moodle_url('/report/trainingsessions/index.php', $params);
        $rows[0][] = new tabobject('user', $userurl, get_string('userdetail', 'report_trainingsessions'));

        $params = array('id' => $course->id, 'view' => 'coursesummary', 'from' => $from, 'to' => $to);
        $usersummaryurl = new moodle_url('/report/trainingsessions/index.php', $params);
        $rows[0][] = new tabobject('coursesummary', $usersummaryurl, get_string('coursesummary', 'report_trainingsessions'));

        if (has_capability('report/trainingsessions:viewother', $context)) {
            $params = array('id' => $course->id, 'view' => 'course', 'from' => $from, 'to' => $to);
            $courseurl = new moodle_url('/report/trainingsessions/index.php', $params);
            $rows[0][] = new tabobject('course', $courseurl, get_string('course', 'report_trainingsessions'));
        }

        if (has_capability('report/trainingsessions:batch', $context)) {
            $params = array('id' => $course->id, 'view' => 'courseraw');
            $params["from[day]"] = date('d', $from);
            $params["from[month]"] = date('m', $from);
            $params["from[year]"] = date('Y', $from);
            $params["to[day]"] = date('d', $to);
            $params["to[month]"] = date('m', $to);
            $params["to[year]"] = date('Y', $to);
            $courserawurl = new moodle_url('/report/trainingsessions/index.php', $params);
            $rows[0][] = new tabobject('courseraw', $courserawurl, get_string('courseraw', 'report_trainingsessions'));
        }
        if (has_capability('report/trainingsessions:viewother', $context)) {
            $params = array('id' => $course->id, 'view' => 'allcourses', 'from' => $from, 'to' => $to);
            $allcoursesurl = new moodle_url('/report/trainingsessions/index.php', $params);
            $rows[0][] = new tabobject('allcourses', $allcoursesurl, get_string('allcourses', 'report_trainingsessions'));

            $params = array('id' => $course->id, 'from' => $from, 'to' => $to);
            $gradesettingsurl = new moodle_url('/report/trainingsessions/gradessettings.php', $params);
            $rows[0][] = new tabobject('gradesettings', $gradesettingsurl, get_string('gradesettings', 'report_trainingsessions'));
        }

        $str = print_tabs($rows, $view, null, null, true);

        return $str;
    }

    /**
     *
     * @global type $DB
     * @global type $OUTPUT
     * @global type $COURSE
     * @global type $CFG
     * @param type $userid
     * @param type $scope
     * @return string
     */
    function user_session_reports_buttons($userid, $scope = 'course') {
        global $DB, $OUTPUT, $COURSE, $CFG;

        if (!is_dir($CFG->dirroot.'/local/vflibs')) {
            if (debugging()) {
                return $OUTPUT->notification(get_string('libsmissing', 'report_trainingsessions'));
            }
        }

        $MON = array('JAN', 'FEV', 'MAR', 'AVR', 'MAI', 'JUI', 'JUIL', 'AOU', 'SEP', 'OCT', 'NOV', 'DEC');

        $str = '';

        $user = $DB->get_record('user', array('id' => $userid));
        $start = $user->firstaccess;
        $last = $user->lastaccess;

        if (!$start) {
            return;
        }

        $startmonth = date('m', $start);
        $startyear = date('Y', $start);
        $lastmonth = date('m', $last);
        $lastyear = date('Y', $last);

        $this->next_month($lastmonth, $lastyear);

        $notfirst = false;
        $startlistmonth = 1;
        $str .= '<div class="trainingsessions-buttons-wrapper">';
        while (($startyear * 100 + $startlistmonth) <= ($lastyear * 100 + 12)) {

            if ($startyear * 100 + $startlistmonth < $startyear * 100 + $startmonth) {
                $str .= $this->placeholder();
                $this->next_month($startlistmonth, $startyear);
                continue;
            }

            if ($startyear * 100 + $startlistmonth >= $lastyear * 100 + $lastmonth) {
                $str .= $this->placeholder();
                $this->next_month($startlistmonth, $startyear);
                continue;
            }

            $params['from'] = mktime(0, 0, 0, $startmonth, 1, $startyear);

            $nextyear = $startyear;
            $nextmonth = $startmonth;
            $this->next_month($nextmonth, $nextyear);

            $params['to'] = mktime(0, 0, 0, $nextmonth, 1, $nextyear);
            $params['id'] = $COURSE->id; // The course id (context for user targets).
            $params['userid'] = $userid; // User id.
            $params['scope'] = $scope;
            $params['outputname'] = 'report_user_'.$userid.'_sessions_'.$params['scope'].'.pdf';

            if ($startmonth == 1 && $notfirst) {
                $str .= '</div><div class="trainingsessions-buttons-wrapper">';
            }
            $url = new moodle_url('/report/trainingsessions/tasks/userpdfreportsessions_batch_task.php', $params);
            $attribs = array('target' => '_blank', 'class' => 'trainingsessions-inline-buttons');
            $str .= $this->single_button($url, $MON[(int)($startmonth - 1)].' '.$startyear, $attribs);
            $startmonth = $nextmonth;
            $startyear = $nextyear;
            $startlistmonth = $startmonth;
            $notfirst = true;
        }
        $str .= '</div>';
        return $str;
    }

    /**
     *
     * @return string
     */
    function placeholder() {
        return '<div class="month-placeholder"></div>';
    }

    /**
     *
     * @param type $url
     * @param type $label
     * @param type $attrs
     * @return type
     */
    function single_button($url, $label, $attrs) {

        // Gives a default method.
        if (empty($attrs) || !array_key_exists('method', $attrs)) {
            $attrs['method'] = 'get';
        }

        $attributes = array('type'     => 'submit',
                            'value'    => $label,
                            'disabled' => @$attrs['disabled'] ? 'disabled' : null,
                            'title'    => ''.@$attrs['tooltip']);

        $formid = html_writer::random_id('single_button');
        if (!array_key_exists('formid', $attrs)) {
            $attrs['formid'] = $formid;
        }

        // First the input element.
        $str = html_writer::empty_tag('input', $attributes);

        // Then hidden fields.
        $params = $url->params();
        if ($attrs['method'] === 'post') {
            $params['sesskey'] = sesskey();
        }
        foreach ($params as $var => $val) {
            $str .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $var, 'value' => $val));
        }

        // Then div wrapper for xhtml strictness.
        $str = html_writer::tag('div', $str);

        // Now the form itself around it.
        if ($attrs['method'] === 'get') {
            // Url without params, the anchor part allowed.
            $url = $url->out_omit_querystring(true);
        } else {
            // Url without params, the anchor part not allowed.
            $url = $url->out_omit_querystring();
        }
        if ($url === '') {
            // There has to be always some action.
            $url = '#';
        }
        $attributes = array('method' => $attrs['method'],
                            'action' => $url,
                            'id'     => $attrs['formid'],
                            'target' => @$attrs['target']);
        $str = html_writer::tag('form', $str, $attributes);

        // And finally one more wrapper with class.
        return html_writer::tag('div', $str, array('class' => @$attrs['class']));
    }

    /**
     *
     * @param int $m
     * @param type $y
     */
    function next_month(&$m, &$y) {
        $m++;
        if ($m > 12) {
            $y++;
            $m = 1;
        }
    }
}