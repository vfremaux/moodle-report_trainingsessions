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
 * This file keeps track of upgrades to the wiki module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * @package report_trainingsessions
 * @copyright 2010
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 */
defined('MOODLE_INTERNAL') || die;

function xmldb_report_trainingsessions_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2015092201) {

        // Define table report_trainingsessions to be created.
        $table = new xmldb_table('report_trainingsessions');

        // Adding fields to table flashcard_card.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('moduleid', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, null, null, null, null, null);
        $table->add_field('label', XMLDB_TYPE_CHAR, '32', null, null, null, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);

        // Adding keys to table report_trainingsessions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table)) {
            // Launch create table for flashcard_card.
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2015092201, 'report', 'trainingsessions');
    }

    if ($oldversion < 2016122400) {
        // Define table report_trainingsessions to be created.
        $table = new xmldb_table('report_trainingsessions');

        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, 10, null, null, null, null, 'sortorder');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('ranges', XMLDB_TYPE_CHAR, 255, null, null, null, null, 'grade');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2016122400, 'report', 'trainingsessions');
    }

    if ($oldversion < 2017011800) {
        $table = new xmldb_table('report_trainingsessions');
        $field = new xmldb_field('label', XMLDB_TYPE_CHAR, 64, null, null, null, null, 'moduleid');

        $dbman->change_field_precision($table, $field);
        upgrade_plugin_savepoint(true, 2017011800, 'report', 'trainingsessions');
    }

    if ($oldversion < 2017020200) {
        $table = new xmldb_table('report_trainingsessions');
        $field = new xmldb_field('ranges', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'grade');

        $dbman->change_field_precision($table, $field);
        upgrade_plugin_savepoint(true, 2017020200, 'report', 'trainingsessions');
    }

    if ($oldversion < 2019041700) {
        // Relocate pdf header/footer configs if necessary.
        relocate_header_files();

        upgrade_plugin_savepoint(true, 2019041700, 'report', 'trainingsessions');
    }

    if ($oldversion < 2019111900) {

        // Define table report_trainingsessions_fa to be created.
        $table = new xmldb_table('report_trainingsessions_fa');

        // Adding fields to table report_trainingsessions_fa.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 0);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 0);
        $table->add_field('timeaccessed', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 0);

        // Adding keys to table report_trainingsessions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $table->add_index('ix_usercourse', XMLDB_INDEX_NOTUNIQUE, array('userid, courseid'));

        if (!$dbman->table_exists($table)) {
            // Launch create table for flashcard_card.
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2019111900, 'report', 'trainingsessions');
    }

    if ($oldversion < 2019112200) {

        // Define table report_trainingsessions to be created.
        $table = new xmldb_table('report_trainingsessions_btc');

        // Adding fields to table flashcard_card.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('taskname', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table->add_field('reportscope', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('reportlayout', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('reportformat', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('outputdir', XMLDB_TYPE_CHAR, '128', null, null, null, null, null, null);
        $table->add_field('timefrom', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('timeto', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('batchdate', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('replay', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('replaydelay', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);

        // Adding keys to table report_trainingsessions_btc.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('ix_coursegroup', XMLDB_INDEX_NOTUNIQUE, array('courseid', 'groupid'));

        if (!$dbman->table_exists($table)) {
            // Launch create table for flashcard_card.
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2019112200, 'report', 'trainingsessions');
    }

    return true;
}

function relocate_header_files() {
    global $DB;

    $fs = get_file_storage();

    $fileareas = array('pdfreportheader', 'pdfreportinnerheader', 'pdfreportfooter');

    foreach ($fileareas as $filearea) {
        $goodparams = array('component' => 'report_trainingsessions', 'filearea' => $filearea);
        $goodrecs = $DB->get_records('files', $goodparams);

        $badparams = array('component' => 'core', 'filearea' => $filearea);
        $badrecs = $DB->get_records('files', $badparams);

        if (empty($goodrecs) && !empty($badrecs)) {
            $sql = "
                UPDATE
                    {files}
                SET
                    component = 'report_trainingsessions'
                WHERE
                    component = 'core' AND
                    filearea = ?
            ";
            $DB->execute($sql, array($filearea));
        }

        // Clean old area whenever.
        $systemcontext = context_system::instance();
        $fs->delete_area_files($systemcontext->id, 'core', $filearea);
    }
}
