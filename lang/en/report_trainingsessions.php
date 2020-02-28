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
 * Strings for component 'report_trainingsessions'.
 *
 * @package    report_trainingsessions
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Privacy.
$string['privacy:metadata'] = "The Trainingsessions Report does not store any data belonging to users";

$string['trainingsessions:iscompiled'] = 'Is compiled in reports'; // @DYNAKEY
$string['trainingsessions:view'] = 'Can view training session report'; // @DYNAKEY
$string['trainingsessions:viewother'] = 'Can view training session reports from other users'; // @DYNAKEY
$string['trainingsessions:downloadreports'] = 'Can download report documents'; // @DYNAKEY
$string['trainingsessions:batch'] = 'Can batch reports'; // @DYNAKEY
$string['trainingsessions:usegrading'] = 'Can setup grading output'; // @DYNAKEY

$string['accountstart'] = 'User account creation date';
$string['activitytime'] = 'Time in activities';
$string['addcoursegrade'] = 'Add course grade to report';
$string['addmodulelabel'] = 'Add activity module';
$string['addmoduletitle'] = 'Add an activity module you want to add grade in report';
$string['addtimebonus'] = 'time bonus on overal grade';
$string['addtimegrade'] = 'Time grade';
$string['allcourses'] = 'All courses';
$string['allgroups'] = 'All groups';
$string['availableactivities'] = 'Available activities';
$string['batchdate'] = 'Task date ';
$string['batchreports_task'] = 'Batch reports'; // @DYNAKEY
$string['bgcolor'] = 'Background color';
$string['binary'] = 'Binary output';
$string['bonusgrademode'] = 'Time bonus grade mode';
$string['bonusgrademodedefault'] = 'Bonus grade mode default';
$string['bonusgrademodedefault_desc'] = 'Default value for the bonus grade mode when first setup in a course';
$string['calculated'] = 'Calculated';
$string['calculatedcolumns'] = 'Calculated columns (XLS Only)';
$string['checklistadvice'] = 'Special side completion effects'; // @DYNAKEY
$string['chooseagroup'] = 'Choose a group';
$string['colors'] = 'Colors';
$string['columnname'] = 'Column name: ';
$string['connections'] = 'Connections';
$string['contiguoussessions'] = 'Contiguous sessions';
$string['continuous'] = 'Continuous output';
$string['coupling'] = 'Coupling';
$string['course'] = 'Course';
$string['courseglobals'] = 'Course global areas';
$string['coursegrade'] = 'Enable course score';
$string['courselabel'] = 'as column: ';
$string['coursename'] = 'Group name';
$string['courseraw'] = 'Batchs';
$string['courses'] = 'Courses';
$string['coursesessions'] = 'Working sessions in course (real guessed times)';
$string['coursestart'] = 'Course start date';
$string['coursesummary'] = 'Per participant summary';
$string['coursetime'] = 'Time in course (activities excluded)';
$string['coursetoolargenotice'] = 'Course is too large and no groups inside. Cannot compile.';
$string['credit'] = 'Credit: ';
$string['crop'] = 'Crop out of range sessions';
$string['csv'] = 'Text (CSV)';
$string['csvoutputtoiso'] = 'Iso CSV Output';
$string['currentcourse'] = 'Current course';
$string['dates'] = 'Key dates';
$string['debugmode'] = 'Debug mode on';
$string['defaultmeanformula'] = '=AVERAGE({col}{minrow}:{col}{maxrow})';
$string['defaultstartdate'] = 'Default start date';
$string['defaultstartdate_desc'] = 'Default start date';
$string['defaultsumformula'] = '=SUM({col}{minrow}:{col}{maxrow})';
$string['disabled'] = '|--------- disabled -----------|';
$string['disablesuspendedenrolments'] = 'Ignore suspended enrolments';
$string['disablesuspendedstudents'] = 'Ignore suspended students';
$string['discrete'] = 'Discrete output';
$string['discreteforcenumber'] = 'Force numeric on discrete';
$string['done'] = 'Performed: ';
$string['duration'] = 'Duration';
$string['elapsed'] = 'Total course time'; // @DYNAKEY
$string['elapsedadvice'] = 'Elapsed time can be different from session time range due to extra credit times on sessions breaks. Refer to the Use Stats block configuration.';
$string['elapsedinitem'] = 'Elapsed time';
$string['elapsedlastweek'] = 'Last week time'; // @DYNAKEY
$string['email'] = 'Email';
$string['emulatecommunity'] = 'Emulate community version';
$string['emulatecommunity_desc'] = 'If enabled, the plugin will behave as the public community version. This might loose features !';
$string['enablecoursescore'] = 'Enable course score';
$string['enablelearningtimecheckcoupling'] = 'Enable LTC coupling';
$string['enablelearningtimecheckcoupling_desc'] = 'If enabled, the session reports will use the working days filtering of the Learning Time Check Report';
$string['enddate'] = 'End date';
$string['enroldate'] = 'User enrol date';
$string['enterprisesign'] = 'Enterprise';
$string['equlearningtime'] = 'Equivalent training time: ';
$string['errorbadcoursestructure'] = 'Course structure error : bad id {$a}';
$string['errorbadviewid'] = 'non existing report view';
$string['errorcontinuousscale'] = 'You cannot use scales as grade source in continuous mode';
$string['errorcoursestructurefirstpage'] = 'Course structure error: failed getting first page';
$string['errorcoursetoolarge'] = 'Course is too large. Choosing a group';
$string['errordiscretenoranges'] = 'You must define ranges when using discrete mode';
$string['errornoabsolutepath'] = 'Path must be relative';
$string['errornotingroup'] = 'You have not access to all users and do not have any group membership.';
$string['exceldatefmt'] = 'yyyy/mm/dd hh:mm';
$string['exceltimefmt'] = '[h]:mm:ss';
$string['extelapsed'] = 'Total time (extended)'; // @DYNAKEY
$string['extelapsedlastweek'] = 'Last week time (extended)'; // @DYNAKEY
$string['exthits'] = 'Hits (extended)'; // @DYNAKEY
$string['exthitslastweek'] = 'Hits last week (extended)'; // @DYNAKEY
$string['extotherelapsed'] = 'Extra out of course time'; // @DYNAKEY
$string['extotherlastweek'] = 'Extra time (last week)'; // @DYNAKEY
$string['extrauserinfo'] = 'Additional user info in reports';
$string['extrauserinfo_desc'] = 'You can optionnaly add user field data to the user info part';
$string['fail'] = 'FAIL';
$string['filetimesuffixformat'] = 'Ymd_His';
$string['firstaccess'] = 'First access';
$string['firstconnection'] = 'First connection';
$string['firstcourseaccess'] = 'First course access';
$string['firstcourseaccess_help'] = 'First time course has been accessed';
$string['firstenrolldate'] = 'First enroll';
$string['firstname'] = 'First Name';
$string['firstsessiontime'] = 'First session';
$string['formulalabel'] = 'Column label';
$string['from'] = 'From';
$string['generatecsv'] = 'Generate as CSV';
$string['generatepdf'] = 'Generate as PDF';
$string['generatereports'] = 'Generate reports';
$string['generatexls'] = 'Generate as XLS';
$string['grademodes'] = 'Grade modes';
$string['gradesettings'] = 'Grade settings';
$string['gradexlsformat'] = 'Excel grade format';
$string['gradexlsformat_desc'] = 'Choose a number format for grades';
$string['groups'] = 'Groups';
$string['head1application'] = 'Head 1 colors are uses on top super header row when suitable.';
$string['head2application'] = 'Head 2 colors are uses on normal header row just above data columns. this is the most common case.';
$string['head3application'] = 'Head 3 coors are used on end of table sumarizer, xhen suitable.';
$string['headsection'] = 'Heading section';
$string['hideemptymodules'] = 'Hide empty modules';
$string['hideemptymodules_desc'] = 'Is enabled, empty modules (no time spent) will not be printed into reports.';
$string['hits'] = 'Hits';
$string['hitslastweek'] = 'Hits last week';
$string['id'] = 'ID';
$string['idnumber'] = 'ID Number';
$string['in'] = 'In time';
$string['incourses'] = 'In courses';
$string['insessiontime'] = 'Inside rules learning time';
$string['institution'] = 'Institution';
$string['institutions'] = 'Institutions';
$string['instructure'] = 'Time in course activities';
$string['interactive'] = 'Interactive';
$string['interactivetitle'] = 'Produce this batch now!';
$string['item'] = 'Item';
$string['items'] = 'Items';
$string['json'] = 'JSON';
$string['lastaccess'] = 'Last access';
$string['lastcourseaccess'] = 'Last course access';
$string['lastcourseaccess_help'] = 'when course was last accessed';
$string['lastlogin'] = 'Last login';
$string['lastname'] = 'Surname';
$string['layout'] = 'Document layout';
$string['learningtimesessioncrop'] = 'Operation on out of range sessions';
$string['learningtimesessioncrop_desc'] = 'When coupling with learningtimecheck, out of valid range sessions could be croped, or kept and only marked into reports';
$string['libsmissing'] = 'This feature has been disabled as libs are missing. Install libs from http://github.com/vfremaux/moodle-local_vflibs to get PDF generation enabled.';
$string['licensekey'] = 'Pro license key';
$string['licensekey_desc'] = 'Input here the product license key you got from your provider';
$string['licenseprovider'] = 'Pro License provider';
$string['licenseprovider_desc'] = 'Input here your provider key';
$string['lineaggregators'] = 'Line aggregators';
$string['location'] = 'Location';
$string['ltcprogressinitems'] = 'LTC Progress (% items)';
$string['ltcprogressinmandatoryitems'] = 'LTC Progress (% mandatories)';
$string['mark'] = 'Mark out of range sessions';
$string['meandaytime'] = 'Mean Time per day';
$string['meanweektime'] = 'Mean time per week';
$string['modgrade'] = 'Activity grade';
$string['modulegrade'] = 'Activity module';
$string['modulegrades'] = 'Activity grades';
$string['never'] = 'Never';
$string['newtask'] = 'Add a new batch';
$string['nodata'] = 'No course data';
$string['noextragrade'] = 'Disabled';
$string['nopermissiontoview'] = 'You have not enough permissions in this course to view this information.';
$string['nosessions'] = 'No measurable session data';
$string['nostructure'] = 'No measurable course structure detected';
$string['nothing'] = 'No users to compile';
$string['now'] = 'Now !';
$string['onefulluserpersheet'] = 'One full user information per sheet';
$string['oneuserperrow'] = 'One user summary information per row in a single sheet';
$string['othertime'] = 'Other time'; // @DYNAKEY
$string['out'] = 'Out time';
$string['outofgroup'] = 'No group';
$string['elapsedoutofstructure'] = 'Other course use time'; // @DYNAKEY
$string['output:finalcoursegrade'] = 'Final grade';
$string['output:rawcoursegrade'] = 'Raw course grade';
$string['output:timebonus'] = 'Time bonus';
$string['output:timegrade'] = 'Time grade';
$string['outputdir'] = 'Output directory ';
$string['outputdirectory'] = 'Output directory in local course files';
$string['outsessiontime'] = 'Out rules remaining time';
$string['over'] = 'over';
$string['parts'] = 'parts';
$string['pass'] = 'PASS';
$string['pdf'] = 'PDF';
$string['pdfabsoluteverticaloffset'] = 'Doc abs. vert. offset';
$string['pdfabsoluteverticaloffset_desc'] = 'Tells the starting offset of the content generation relative to top of page in pdf generation (in mm).';
$string['pdfpage'] = 'Page: ';
$string['pdfpagecutoff'] = 'PDF page height cutoff';
$string['pdfpagecutoff_desc'] = 'Height in page for switching to next page (in mm).';
$string['pdfreportfooter'] = 'PDF report footer image';
$string['pdfreportfooter_desc'] = 'Provide a JPG image for the bottom footer (880px large x up to 100px height)';
$string['pdfreportheader'] = 'PDF report header image';
$string['pdfreportheader_desc'] = 'Provide a JPG image for the top header part (880px large x up to 220px height)';
$string['pdfreportinnerheader'] = 'PDF report inner header image';
$string['periodshift'] = 'Shift period';
$string['periodshiftto'] = 'Shift "to" date only';
$string['plugindist'] = 'Plugin distribution';
$string['pluginname'] = 'Training Sessions';
$string['printidnumber'] = 'Print ID Number';
$string['printidnumber_desc'] = 'If checked, adds IDNumber to reports';
$string['printlocation'] = 'Training location';
$string['printlocation_desc'] = 'The physical location of the training';
$string['printsessiontotal'] = 'Display the overal session elapsed time';
$string['printsessiontotal_desc'] = 'Do NOT display the total session time in on screen session reports.';
$string['profileinfotimeformat'] = '%d %B %Y';
$string['quickmonthlyreport'] = 'Quick monthly reports (PDF)';
$string['range'] = 'Range ';
$string['readableduration'] = 'Duration';
$string['real'] = 'Real: ';
$string['recipient'] = 'Recipient';
$string['recipient_desc'] = 'Default recipient of the PDF documents. May be locally overloaded by each operator.';
$string['replay'] = 'Replay same settings';
$string['replaydelay'] = 'Replay delay (min)';
$string['reportdate'] = 'Report date';
$string['reportfilemanager'] = 'Report files manager';
$string['reportformat'] = 'Document format';
$string['reportforuser'] = 'Report for ';
$string['reportlayout'] = 'Report layout';
$string['reports'] = 'Reports';
$string['reportscope'] = 'Scope';
$string['role'] = 'Role';
$string['scheduledbatches'] = 'Scheduled batches';
$string['scoresettings'] = 'Score Reporting Settings';
$string['sectionname'] = 'Section name';
$string['seedetails'] = 'See details';
$string['selectforreport'] = 'Select for reports';
$string['sessionduration'] = 'Session duration';
$string['sessionend'] = 'Session end';
$string['sessionreportdoctitle'] = 'Session report';
$string['sessionreports'] = 'User session report';
$string['sessionreporttitle'] = 'Session report document caption';
$string['sessionreporttitle_desc'] = 'Printed on first page of a user session report';
$string['sessions'] = 'Working sessions (real guessed times)';
$string['sessionsonly'] = 'User sessions only';
$string['sessionstart'] = 'Session start';
$string['showhits'] = 'Show events';
$string['showhits_desc'] = 'If set, the hit count will be added to the CSV lines';
$string['showitemfirstaccess'] = 'Show items first access date';
$string['showitemfirstaccess_desc'] = 'If set, the real date of first access to the item (log based) is displayed in reports';
$string['showitemlastaccess'] = 'Show items last access date';
$string['showitemlastaccess_desc'] = 'If set, the real date of last access to the item (log based) is displayed in reports';
$string['showmonthlyquickreports'] = 'Show monthly quick reports';
$string['showmonthlyquickreports_desc'] = 'If set, detail user report show monthly partial reports';
$string['showsectionsonly'] = 'show sections only';
$string['showsectionsonly_desc'] = 'If enabled, items details are not shown on report. Only the lowest aggreegation level over items.';
$string['showsessions'] = 'Show sessions detail';
$string['showsessions_desc'] = 'If set, the session details will be accessible in reports';
$string['showsseconds'] = 'Show seconds';
$string['showsseconds_desc'] = 'Show seconds in duration expression if enabled';
$string['singleexec'] = 'Single run';
$string['siteglobals'] = 'Site (non course sections)';
$string['specialgrades'] = 'Special grades';
$string['startdate'] = 'Start date';
$string['strfdate'] = '%Y-%m-%d';
$string['strfdatetime'] = '%Y-%m-%d %H:%M';
$string['strftime'] = '%H:%M:%S';
$string['structureitem'] = 'Course trackable item';
$string['structuretotal'] = 'Total {$a}:';
$string['studentsign'] = 'Student';
$string['summarycolumns'] = 'Output columns for summary report';
$string['task'] = 'Task {$a}';
$string['taskname'] = 'Task';
$string['taskrecorded'] = 'Task successfully recorded';
$string['teachersign'] = 'Teacher';
$string['textapplication'] = 'This is a setting for default text of the document.';
$string['textcolor'] = 'Text color';
$string['timeelapsed'] = 'Time spent';
$string['timeelapsedcurweek'] = 'Time spent cur. week';
$string['timegrade'] = 'Time grade source';
$string['timegrademode'] = 'Time grade mode';
$string['timegrademodedefault'] = 'Time grade mode default';
$string['timegrademodedefault_desc'] = 'Default value for the time grade mode when first setup in a course';
$string['timegraderanges'] = 'Time grade ranges';
$string['timegradesourcedefault'] = 'Time grade source default';
$string['timegradesourcedefault_desc'] = 'Default value for the bonus grade source when first setup in a course';
$string['timeperpart'] = 'Time elapsed per part';
$string['timesource'] = 'Time source';
$string['timespent'] = 'Spent';
$string['timespentlastweek'] = 'Spent last week';
$string['to'] = 'To';
$string['todate'] = 'Date end';
$string['tonow'] = 'To now';
$string['total'] = 'Total';
$string['totalduration'] = 'Total duration';
$string['totalsessions'] = 'Total session time';
$string['totalsessiontime'] = 'Total working sessions time';
$string['totalsitetime'] = 'Total site time';
$string['totalwdtime'] = 'Total WD time';
$string['trainingreports'] = 'Training Reports';
$string['trainingsessions'] = 'Training Sessions';
$string['trainingsessions_report_advancement'] = 'Progress Report';
$string['trainingsessions_report_connections'] = 'Connection Report';
$string['trainingsessions_report_institutions'] = 'Institution Report';
$string['trainingsessionsreport'] = 'Training Session Reports';
$string['trainingsessionsscores'] = 'Score addition to reports';
$string['unvisited'] = 'Unvisited';
$string['updatefromaccountstart'] = 'Get from user first access';
$string['updatefromcoursestart'] = 'Get from course start';
$string['updatefromenrolstart'] = 'Get from user\'s enrol date';
$string['uploadglobals'] = 'File uploads';
$string['uploadresult'] = 'Download raw results';
$string['user'] = 'Per participant';
$string['userdetail'] = 'Participant detail';
$string['userid'] = 'User ID';
$string['userlist'] = 'One row per participant';
$string['usersheets'] = 'One sheetset per participant';
$string['usersummary'] = 'Participant summary';
$string['visiteditems'] = 'Visited Items.';
$string['weekstartdate'] = 'Week start';
$string['workday'] = 'WDay';
$string['workingdays'] = 'Work days report';
$string['workingsessions'] = 'Work sessions';
$string['workweek'] = 'Week';
$string['xls'] = 'XLS';
$string['xlsadditions'] = 'XLS Additions';
$string['xlsexportlocale'] = 'XLS Export Locale';
$string['xlsformula'] = 'Formula (Excel expression)';
$string['xlsmeanformula'] = 'XLS Mean Formula';
$string['xlssumformula'] = 'XLS Sum Formula';

$string['pdfreportinnerheader_desc'] = 'Provide a JPG image for the top header part in inner pages (880px large x up to 150px height). If
none given, the first page header will be used again.';

$string['quickgroupcompile'] = '<h3>Quick Compile for {$a} users:</h3><p>Quick compilation provides a quick summary report for groups less than
50 users, directly in the root directory of your course files.</p>';

$string['xlsmeanformula_desc'] = 'XLS Mean Formula. Use {minrow} and {maxrow} placeholders to fix the vertical range, and {col} as current column
identifier. A cell reference can be : ${col}$4, $Y${minrow}';

$string['xlssumformula_desc'] = 'XLS Sum Formula. Use {minrow} and {maxrow} placeholders to fix the vertical range, and {col} as current column
identifier. A cell reference can be : ${col}$4, $Y${minrow}';

$string['scoresettingsadvice'] = 'In course summary reports (one user per line), you may add additional output columns with scores from the
gradebook. You can add the global course grade, or choose to add one (or more) single activity grade(s) in the report.';

$string['calculated_help'] = 'Enter an excel formula using local excel references as produced in the output document. Use {row} placeholder to
insert the current line number in cell references. Use english function names.

Example :

=AVERAGE($C${row}:$D${row})
';

$string['toobig'] = '<p>Compilation group is too big to be performed in quick compilation. We incline you programming a delayed batch at a
time that will not affect your currently working users.<br/>To setup a batch, preset the compilation parameters in the above form, and
register a new batch with the desired configuration, and setting batch time and output dir from the course file storage location origin
(relative path, absolute path rejected).</p><p>You can also program a regular compilation batch that will compile every \"replaydelay\"
minutes to the desired output.</p>';

$string['lineaggregators_help'] = '
<p>Define aggregators as a list of aggregators switches starting from left most columns in the
resulting excel sheet. Separe switches with comas or semicolumns. Leaving blank disables the
aggregator line.
</p>
<ul>
<li><b>m :</b> mean</li>
<li><b>s :</b> sum</li>
</ul>

<p>
Example : if an excel output has 10 colums and a sum is required on column 10, than
enter : ;;;;;;;;;s
</p>';

$string['proversionrequired'] = '
<p>You are trying to use a feature that is only available in the "Pro" version of this plugin. The "Pro" version of this plugin
is a paied for version that help us support the development cycle of this plugin with quality insurance concerns, enhanced featuring
and full support to our customers.</p>

<p<Pro version can be obtained from our distributors:</p>

<p><a href="http://www.mylearningfactory.com/index.php/documentation/Distributeurs?lang=en_utf8">MyLF Distributors</a></p<
';

$string['timegraderanges_help'] = '
Time grade ranges let you cut the elapsed time into pieces for achieving a discrete time grade effect. binay modes will need
to give only one time threshold (in minutes) separating the PASSED and FAILED state. In "discrete" mode, enter a list (coma separated)
of time threshold separating the ranges. The last range stands for "time over last value".
';

$string['csvoutputtoiso_desc'] = 'If enabled, the course raw report will be generated in ISO-8859-1 encoding for old CSV compliant applications.';

$string['modulegrades_help'] = '
   You can add here more columns to the report from the course grade book, choosing the activity module that will be source for the score.
   You may also define the column label that will be used for this column in the report sheets. If left blank, the column name will be in order
   of availability, the coursemodule IDnumber, or a module identifier built in by Moodle.
';

$string['summarycolumns_desc'] = '
<p>Choose columns by commenting with # any line. You can reorder lines to change the output order.</p>
<p>(format keys : a as text, t as date, d as duration, n as numeric).</p>
';

$string['totalsessiontime_help'] = 'Note that session list counts some durations that can be outside this course. Total session
time should usually be higher than in course time calculation';

$string['insessiontime_help'] = 'This is the "in" learning time that matched validated rules';

$string['outsessiontime_help'] = 'This is the remaining learning time that do NOT match validation rules';

$string['activitytime_help'] = '
<p>This time calculation considers all use time spent in course activities, letting course
    layout times out of calculation. In certain cases (when using the Learning Time Check (non standard) with
    standard time allocation (http://github.com/vfremaux/moodle-mod_learningtimecheck.git), additional
    standard time are used rather than real extracted times from log.</p>
';

$string['elapsed_help'] = '
<p>This summarizes all time spent in the course or any dependancy of the course.</p>
';

$string['equlearningtime_help'] = '
<p>Equivalent learning time summarizes all time spent in course, including standard allocation times if
    the Learning Time Check checklist module is used (http://github.com/vfremaux/moodle-mod_learningtimecheck.git).</p>
';

$string['learningtimecheckadvice_help'] = '
<p>When using a Learning Time Check module that enables teachers to validate activities without
any student interaction in the course, some apparent information discrepancy may appear.</p>
<p>This is a normal situation that reports consistant information regarding the effective
    use of the platform</p>
';

$string['coursetime_help'] = '
<p>this summarizes the time passed in general screens of the course but OUTSIDE activities.
';

$string['othertime_help'] = '
<p>Elapsed time that cannot be directly assigned to a course activity module.</p>
';

$string['outputdir_help'] = '
<p>You may select an output subdirectory for generating your output documents. Note that the storage context where to find those documents
    is the course from where you programmed the batch, even if the compilation course mentionned "All courses"</p>
';

$string['batchdate_help'] = '
<p>This setting means the exact date at which the batch will be lauched and the documents generated. If you fear the documents are heavy (lot
 of students, lot of histories to track), choose a date/time in a low load period of your server.</p>
';

$string['replaydelay_help'] = '
<p> If set to a positive value (in minutes), the batch will not be discarded after execution, but replayed continuously with that delay.
 Start date and/or end date will be shifted accordingly if a sliding period replay is selected.</p>
';

$string['reportscope_help'] = '
<p>Some reports allow scanning all courses of the user. Note that some reports do not use the scope.</p>
';

$string['plugindist_desc'] = '
<p>This plugin is the community version and is published for anyone to use as is and check the plugin\'s
core application. A "pro" version of this plugin exists and is distributed under conditions to feed the life cycle, upgrade, documentation
and improvement effort.</p>
<p>Note that both components report_trainingsessions and blocks_use_stats must work using the same distribution level.</p>
<p>Please contact one of our distributors to get "Pro" version support.</p>
<p><a href="http://www.mylearningfactory.com/index.php/documentation/Distributeurs?lang=en_utf8">MyLF Distributors</a></p>';

$string['extelapsed_help'] = '
The extended course time calculates the time strictly spent in the course context, plus time spent to get down to the course
material and some time spent in general site screens the user has access to.';

$string['elapsedoutofstructure_help'] = '
Time spent in the course scope, but not assignable to any "structural element" of the course (section, page, module).';

$string['extelapsedlastweek_help'] = '
The extended course time calculates the time strictly spent in the course context, plus time spent to get down to the course
material and some time spent in general site screens the user has access to limited to the last week timerange.';

$string['elapsedlastweek_help'] = '
The extended course time calculates the time strictly spent inside the course context limited to the last week timerange.';

$string['extotherelapsed_help'] = '
<p>Elapsed time outside of this course, but attached to this course sessions. They are usually spent in user pages, or in global site scope.</p>';

$string['grademodes_help'] = 'Grade modes define how the grade is calculated from the original data input:

    * Binary (When possible) : A single threshold will be input and the score will be the full scale score or 0. If the score is based on
      a scale, the score will switch between the lowest and the highest index.
    * Discrete : A set of ranges will be input that splits the time value into acceptable ranges. The score max grade will be divided into
      equal portions of the score scale. If a scale is used for scoring, then the ranges should provide N - 1 thresholds.
    * Continuous : A full score equivalent time will be input. If the input value is greater or equal to the reference value,
    the given score will be full score,
      otherwise the score will be the rounded closest linear interpolation of the input vs. the threshold.
';

$string['disablesuspendedstudents_desc'] = 'If enabled, suspended students wil not appear in reports';

$string['disablesuspendedenrolments_desc'] = 'If enabled, students with suspended enrolments only will not appear in reports';

$string['discreteforcenumber_desc'] = 'Force numeric format on discrete time grade (excel output). the discrete values
of time grade scale should be numerically interpretable.';

$string['xlsexportlocale_desc'] = 'Used to force locale when exporting and générating excel exports.
Leave empty for using site default locale, or force with an explicit locale code such as en_EN.UTF-8';

$string['hasdisabledenrolmentsrestriction'] = 'Suspended enrolements are filtered out.';