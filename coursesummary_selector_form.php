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
 * A Selector form for course summary.
 *
 * @package    coursereport_trainingsessions
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) die('You cannot use this scirpt this way');

?>

<style type="text/css">
    #menugroupid{width:100%}
    #go_btn{width:100%}
</style>

<center>
<form action="#" name="courseselector" class="courseselector" method="get">
<input type="hidden" name="id" value="<?php p($course->id) ?>" />
<input type="hidden" name="startday" value="<?php p($startday) ?>" />
<input type="hidden" name="startmonth" value="<?php p($startmonth) ?>" />
<input type="hidden" name="startyear" value="<?php p($startyear) ?>" />
<input type="hidden" name="fromstart" value="" />
<input type="hidden" name="endday" value="<?php p($endday) ?>" />
<input type="hidden" name="endmonth" value="<?php p($endmonth) ?>" />
<input type="hidden" name="endyear" value="<?php p($endyear) ?>" />
<input type="hidden" name="toend" value="" />
<input type="hidden" name="output" value="html" />
<input type="hidden" name="asxls" value="" />
<input type="hidden" name="view" value="coursesummary" />
<table>
<tr valign="top">
    <td align="right">
        <?php
            print_string('from');
            echo ' :&nbsp;</td><td align="left">';
            print_date_selector('startday', 'startmonth', 'startyear', $from);
        ?>
    </td>
    <td align="right">
        <?php
            print_string('chooseagroup', 'report_trainingsessions');
		    echo " :&nbsp;";
        ?>
    </td>
    <td>
        <?php
            if (has_capability('moodle/site:accessallgroups', $context)){
                $groups = groups_get_all_groups($course->id);
            } else {
                $groups = groups_get_all_groups($course->id, $USER->id);
            }
            $groupoptions[0] = get_string('allgroups', 'report_trainingsessions');
            if ($groupid === false){
                $groupid=0;
            }
            foreach($groups as $group){
                $groupoptions[$group->id] = $group->name;
            }
            choose_from_menu($groupoptions, 'groupid', $groupid);
        ?>
    </td>
</tr>
<tr valign="top">
    <td align="right">
        <?php
            print_string('to');
            echo ' :&nbsp;</td><td align="left">';
            print_date_selector('endday', 'endmonth', 'endyear', $to);
        ?>
    </td>
    <td/><td align="right">
        <input type="submit" id="go_btn" name="go_btn" value="<?php print_string('update') ?>"/>
    </td>
</tr>
</table>
</form>
</center>
