<?php // $Id: mod.php,v 1.2 2010/07/22 11:55:15 vf Exp $

    if (!defined('MOODLE_INTERNAL')) {
        die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
    }

	$context = get_context_instance(CONTEXT_COURSE, $course->id);

    if (has_capability('coursereport/trainingsessions:view', $context)) {
        echo '<p>';
        $trainingsessionsreport = get_string('trainingsessionsreport', 'report_trainingsessions');
        echo "<a href=\"{$CFG->wwwroot}/course/report/trainingsessions/index.php?id={$course->id}\">";
        echo "$trainingsessionsreport</a>\n";
        echo '</p>';
    }
?>
