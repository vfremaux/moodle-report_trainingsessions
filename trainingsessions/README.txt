Use Time Based Course Reports
####################################

Author : Valery Fremaux (valery.fremaux@club-internet.fr)

Dependancies : works with the blocks/use_stats log analyser module.

####################################

The Course Training Session report provides a structured reporting of elapsed time
by the usrs within a Moodle course, and presenting those detailed result conformely
to the course most probable pedagogic layout. 

The pedagogic organisation is compiled from the internal course setup information and
will be aware of most course formats in Moodle. At least are handled : 

- Topic course format
- Weekly course format

as standard

# unchecked features

The flexipage (Moodlerooms) format handling works in Moodle 1.9. It needs to be checked against the new Moodle rooms
proposal for Moodle 2 flexipage.

#####################################

Install : Unzip the report in the /report directory of your Moodle installation.

You will need having installed the blocks/use_stats custom block

####################################

Features : 

* Per student reports : reports the entire pedagogic track with individual and summarized 
presence time.

* Per group (or training session bundled in groups) : Reports a summarized presence time 
for an entire group.

* Excel exports of individual timesheet

* Excel export of a group timesheet as an Excel multiple individual sheet.

* Raw report as one single Excel table for the course.

* "All course" summary capitalisers.

Automated generation of group reports
======================================

The trainingsession reports is provided with a batch generation URL that generates
automatically all group reports for a specific course. All you need to know is the course ID,
and add a cron task that wgets this url : 

wget -O /dev/null -q <moodlewwwroot>/course/report/trainingsessions/grouprawreport_batch.php?id=<courseid>

Setting capabilities for student access to his own report
=========================================================

As a default, student access is not given to reports. In case the own training
report needs to be displayed for students, change following configurations in Moodle :

1. Add the moodle/site:viewreports to the student role. 
2. Check the coursereport/trainingsessions:view is set for student role. 
3. Check the coursereport/trainingsessions:viewother is NOT set for student role. 

If you do not want students have access to this report everywhere in all courses they are enrolled in, 
but you want to control course by course the access to their report, use role override on student role
at course level and add an averride on the moodle/site:viewreports on that course. 
