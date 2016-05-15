moodle-report_trainingsessions
==============================

A structured report of use time using use_stats bloc time compliatons.

Provides : 

Student individual details report :
* Use time detailed in course structure
* Use time in course space (outside activities)
* Working sessions reports 

Dependancies: 
===============
Block moodle-block_use_stats

Optional :

For PDF generation, you will need using the VFLibs additional libraries you can get at 
http://github.com/vfremaux/moodle-local_vflibs

This will add adapted version of some core libraries. 

In our case, we need a better control of the page length in TCPDF for handling automatic
page breaks for long reports. This is not handled by the standard TCPDF library


Versions:
=========
Available in Moodle 2.x (master) and Moodle 1.9 (MOODLE_19_STABLE)

2016042900 : Adds a specific capability for batchs

