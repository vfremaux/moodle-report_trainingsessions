moodle-report_trainingsessions
==============================

A structured report of use time using use_stats bloc time compliations.

Provides: 

- Student individual details report :
- Use time detailed in course structure
- Use time in course space (outside activities)
- Working sessions reports
- supports CSV and XLS output (pro version for PDF and JSON formats)
- Trainng session overview
- Course summary report
- Course raw and batch programming 

Dependencies: 
===============
Block moodle-block_use_stats
Block moodle-auth_ticket for securing batch CURL tasks distribution.

Optional :

For PDF generation, you will need using the VFLibs additional libraries you can get at 
http://github.com/vfremaux/moodle-local_vflibs

This will add adapted version of some core libraries. 

In our case, we need a better control of the page length in TCPDF for handling automatic
page breaks for long reports. This is not handled by the standard TCPDF library


Versions:
=========
Moodle < 2.7 not supprted any more. 
active branches: 
- MOODLE_27_STABLE
- MOODLE_28_STABLE
- MOODLE_29_STABLE
- MOODLE_30_STABLE
- MOODLE_31_STABLE

<<<<<<< HEAD
Next comming branches:
- MOODLE_32_STALE

WORKING branches are unstable and used for continuous integration automated tests.

This is the community version of the report_trainingsessions plugin
A "Pro" featured version is available from our Distributors.

ActiveProLearn SAS (sales@activeprolearn.com)
Edunao SAS (cyril@edunao.com)

Pro version provides:
- Batch parallel execution using MultiCurl
- PDF renderers and generators
- JSON renderers and generators
- LearningTimeCheck time credits coupling
- Laborable/non laborable separation
=======
2016042900 : Adds a specific capability for batchs

>>>>>>> MOODLE_32_STABLE
