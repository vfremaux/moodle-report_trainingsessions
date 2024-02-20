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
 * @version    moodle 2.x
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @subpackage  cli
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
 * This script is to be used from PHP command line and will create a set
 * of Virtual VMoodle automatically from a CSV nodelist description.
 * Template names can be used to feed initial data of new VMoodles.
 * The standard structure of the nodelist is given by the nodelist-dest.csv file.
 */

global $CLI_VMOODLE_PRECHECK;

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);
$CLI_VMOODLE_PRECHECK = true; // Force first config to be minimal.

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');

require_once($CFG->libdir.'/clilib.php'); // Cli only functions.

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'interactive'   => false,
        'help'          => false,
        'reportlayout'  => false,
        'format'        => false,
        'from'          => false,
        'to'            => false,
        'host'          => true
    ),
    array(
        'h' => 'help',
        'H' => 'host',
        'l' => 'reportlayout',
        'F' => 'format',
        'f' => 'from',
        't' => 'to'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Not recognized options ".$unrecognized);
}

if ($options['help']) {
    $help = "Batch all courses for reports.
Please note you must execute this script with the same uid as apache!
Note also that this is a VERY resource intensive script if many courses and many users in courses.
Do NOT launch on working hours.

Options:
\t--interactive No interactive questions or confirmations
\t-l, --reportlayout  the layout. Defaults to oneuserperrow
\t-h, --format      The format (xls,csv,json,pdf) some may not be supported in the current distribution
\t-h, --help      Print out this help
\t-H, --host  processes a specific host (vmoodle environments).

Example:
\t\$sudo -u www-data /usr/bin/php report/trainingsessions/cli/batch_all_courses.php
"; // TODO: localize - to be translated later when everything is finished.

    echo $help;
    die;
}

if (!empty($options['host'])) {
    // Arms the vmoodle switching.
    echo('Arming for '.$options['host']."\n"); // Mtrace not yet available.
    define('CLI_VMOODLE_OVERRIDE', $options['host']);
}

// Replay full config whenever. If vmoodle switch is armed, will switch now config.

if (!defined('MOODLE_INTERNAL')) {
    // If we are still in precheck, this means this is NOT a VMoodle install and full setup has already run.
    // Otherwise we only have a tiny config at this location, sso run full config again forcing playing host if required.
    require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
}
echo('Config check : playing for '.$CFG->wwwroot."\n");

require_once($CFG->dirroot.'/report/trainingsessions/locallib.php');

// Fakes an admin identity for all the process.
$USER = get_admin();

mtrace('Starting CLI trainingsessions "batch all" reports'."\n");
$config = get_config('report_trainingsession');

$rt = \report\trainingsessions\trainingsessions::instance();
$accesskey = $rt->back_office_get_ticket();

$courses = $DB->get_records('courses', []);

if (empty($options['format'])) {
    $options['format'] = 'xls';
}

$batchpath = 'batchs/';
if ($options['format'] == 'json') {
    if (!report_trainingsessions_supports_feature('format/json')) {
        die ("unsupported format in this distribution");
    } else {
        $batchpath = 'pro/batchs/';
    }
}

if ($options['format'] == 'pdf') {
    if (!report_trainingsessions_supports_feature('format/pdf')) {
        die ("unsupported format in this distribution");
    } else {
        $batchpath = 'pro/batchs/';
    }
}

if (empty($options['format']))  {
    $options['format'] = 'xls';
}

if (empty($options['reportlayout']))  {
    $options['reportlayout'] = 'oneuserperrow';
}

if (!empty($courses)) {
    foreach ($courses as $cid => $c) {
        if ($c->id == SITEID) {
            continue;
        }

        $curl = $CFG->wwwroot.'/report/trainingsessions/'.$batchpath.'group'.$options['format'].'report_batch.php?ticket='.$accessticket;
        $curl .= '&id='.$cid;
        $curl .= '&reportlayout='.$options['reportlayout'];
        if (!empty($options['from']))  {
            $curl .= '&from='.$options['from'];
        }

        if (!empty($options['to']))  {
            $curl .= '&to='.$options['to'];
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, 1200);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle TrainingSessions Report Batch');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rq);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml charset=UTF-8"));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $raw = curl_exec($ch);

        // Check for curl errors.
        $curlerrno = curl_errno($ch);
        if ($curlerrno != 0) {
            debugging("Request for {$uri}?{$rq} / Group {$group->id} failed with curl error $curlerrno");
        }

        // Check HTTP error code.
        $info = curl_getinfo($ch);
        if (!empty($info['http_code']) && ($info['http_code'] != 200) && ($info['http_code'] != 303)) {
            echo("Request for {$uri}?{$rq} / Group {$group->id} failed with HTTP code ".$info['http_code']."\n");
        }

        curl_close($ch);
    }
}