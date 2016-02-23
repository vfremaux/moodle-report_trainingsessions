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

defined('MOODLE_INTERNAL') || die;

/**
 * @package    report_trainingsessions
 * @category   report
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * this file provides with WS document requests to externalize report documents
 * from within an external management system
 */
 
class report_trainingsessions_external {

    function fetch_report($reporttype, $reportscope, $reportformat, $from, $to, $courseid = 0, $groupid = 0, $userid = 0) {
        // ensure report format is always lowercase : 
        $reportformat = strtolower($reportformat);

        if (!in_array($reportformat, array('csv', 'xls', 'pdf', 'json'))) {
            $data = new StdClass();
            $data->errorcode = 100;
            return ;
        }

        if (!in_array($reportscope, array('allcourses', 'currentcourse'))) {
            $data = new StdClass();
            $data->errorcode = 200;
            return ;
        }

        if (!in_array($reporttype, array('onefulluserpersheet', 'onefulluserperfile', 'oneuserperrow', 'alluserssessionsinglefile', 'sessions'))) {
            $data = new StdClass();
            $data->errorcode = 300;
            return ;
        }

        // Resolve the adequate document generator to call
        switch ($reportformat) {
            case 'xls':
                switch ($reportlayout) {
                    case 'onefulluserpersheet':
                        $reporttype = 'report';
                        $range = 'user';
                        $rangeid = $user->username;
                        $uri = new moodle_url('/report/trainingsessions/groupxlsreportperuser_batch_task.php');
                        break;
                    case 'oneuserperrow':
                        $reporttype = 'summary';
                        $range = 'group';
                        $rangeid = $groupid;
                        $uri = new moodle_url('/report/trainingsessions/groupxlsreportsummary_batch_task.php');
                        break;
                    default:
                        $reporttype = 'sessions';
                        $range = 'user';
                        $rangeid = $user->username;
                        $uri = new moodle_url('/report/trainingsessions/userxlssessionsreport_batch_task.php');
                        break;
                }
                break;
            case 'pdf':
                switch ($reportlayout) {
                    case 'onefulluserpersheet':
                        $reporttype = 'peruser';
                        $range = 'group';
                        $rangeid = $groupid;
                        break;

                    case 'onefulluserperfile':
                        $reporttype = 'peruser';
                        $range = 'user';
                        $rangeid = $user->username;
                        break;

                    case 'oneuserperrow':
                        $reporttype = 'summary';
                        $range = 'group';
                        $rangeid = $groupid;
                        break;

                    case 'allusersessionssinglefile':
                        $reporttype = 'sessions';
                        $range = 'group';
                        $rangeid = $groupid;
                        break;

                    default:
                        $reporttype = 'sessions';
                        $range = 'user';
                        $rangeid = $user->username;
                }
                break;
            case 'json':
                break;
            default: // csv case
                switch ($reportlayout) {
                }
        }

        $uri = new moodle_url('/report/trainingsessions/'.$range.$reportformat.'report'.$reporttype.'_batch_task.php');
        if ($reportformat != 'json') {
            $filename = 'trainingsessions_'.$range.'_'.$rangeid.'_'.$reporttype.'_'.date('Y-M-d', time()).'.'.$reportformat;
        } else {
            $filename = '';
        }

        $timesession = time();

        $result = new StdClass;
        $result->filename = $filename;
        if ($range == 'user') {
            $result->file = report_trainingsessions_process_group_file($group, $id, $from, $to, $timesession, $uri, null, $reportscope);
        } else {
            $result->file = report_trainingsessions_process_user_file($group, $id, $from, $to, $timesession, $uri, null, $reportscope);
        }

        return json_encode($result);
    }

    public static function fetch_report_parameters() {

        return new external_function_parameters (
            array(
                '$reporttype' => new external_value(
                        PARAM_ALPHA,
                        'report type'),
                'reportscope' => new external_value(
                        PARAM_ALPHA,
                        'scope of data scanned for report'),
                'reportformat' => new external_value(
                        PARAM_ALPHA,
                        'document content format'),
                'from' => new external_value(
                        PARAM_INT,
                        'period start timestamp'),
                'to' => new external_value(
                        PARAM_INT,
                        'period end timestamp'),
                'courseid' => new external_value(
                        PARAM_INT,
                        'course target restriction (for group ranged reports)'),
                'groupid' => new external_value(
                        PARAM_INT,
                        'group target restriction (for group ranged reports)'),
                'userid' => new external_value(
                        PARAM_INT,
                        'user targetting restriction (for user range reports)'),
            )
        );
    }

    public static function fetch_report_returns() {
        return new external_value(PARAM_RAW, 'a document content in a single object');
    }
}