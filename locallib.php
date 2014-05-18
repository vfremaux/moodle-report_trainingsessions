<?php

/**
* decodes a course structure giving an ordered and
* recursive image of the course.
* The course structure will recognize topic, weekly and flexipage
* course format, keeping an accurate image of the course ordering.
*
* @param int $courseid
* @param reference $itemcount a recursive propagating counter in case of flexipage
* or recursive content.
* @return a complex structure representing the course organisation
*/
function reports_get_course_structure($courseid, &$itemcount){
    global $CFG, $DB;
    
    $structure = array();

    if (!$course = $DB->get_record('course', array('id' => $courseid))){
        print_error('errorbadcoursestructure', 'report_trainingsessions', $courseid);
    }
    
    if ($course->format == 'page'){
        include_once $CFG->dirroot.'/course/format/page/lib.php';
        include_once $CFG->dirroot.'/course/format/page/page.class.php';

        // get first top level page (contains course structure)
        $nestedpages = course_page::get_all_pages($courseid, 'nested');
        if (empty($nestedpages)){
            print_error('errorcoursestructurefirstpage', 'report_trainingsessions');        
        }
        
        // adapt structure from page format internal nested
        foreach($nestedpages as $key => $page){
            if (!($page->display > FORMAT_PAGE_DISP_HIDDEN)) continue;
            
            $pageelement = new StdClass;
            $pageelement->type = 'page';
            $pageelement->name = format_string($page->nametwo);
            
            $pageelement->subs = page_get_structure_from_page($page, $itemcount);
            $structure[] = $pageelement;
        }
    } else {
        // browse through course_sections and collect course items.
        $structure = array();

        if ($sections = $DB->get_records('course_sections', array('course' => $courseid), 'section ASC')) {
            foreach ($sections as $section) {
                $element = new StdClass;
                $element->type = 'section';
                $element->plugintype = 'section';
                $element->instance = $section;
                $element->instance->visible = $section->visible;
                $element->id = $section->id;
                //shall we try to capture any title in there ?
                if (preg_match('/<h[1-7][^>]*?>(.*?)<\\/h[1-7][^>]*?>/i', $section->summary, $matches)){
                    $element->name = $matches[1];
                } else {
                    if ($section->section){
                        $element->name = get_string('section').' '.$section->section ;
                    } else {
                        $element->name = get_string('headsection', 'report_trainingsessions') ;
                    }
                }

                if (!empty($section->sequence)) {
                    $element->subs = array();
                    $sequence = explode(",", $section->sequence);
                    foreach ($sequence as $seq) {
                       	if (!$cm = $DB->get_record('course_modules', array('id' => $seq))){
                       		// if (debugging()) notify("missing module of id $seq");
                       		continue;
                    	}
                       $module = $DB->get_record('modules', array('id' => $cm->module));
                       if (preg_match('/label$/', $module->name)) continue; // discard all labels
                       $moduleinstance = $DB->get_record($module->name, array('id' => $cm->instance));
                       $sub = new StdClass;
                       $sub->id                 = $cm->id;
                       $sub->plugin             = 'mod';
                       $sub->type               = $module->name;
                       $sub->instance           = $cm;
                       $sub->name               = $moduleinstance->name;
                       $sub->visible            = $cm->visible;
                       $element->subs[] = $sub;
                       $itemcount++;
                    }
                }
                $structure[] = $element;    
            }
        }
    }

    return $structure;
}

/**
* get the complete inner structure for one page of a page menu.
* Recursive function.
*
* @param record $page
* @param reference $itemcount a recursive propagating counter in case of flexipage
* or recursive content.
*/
function page_get_structure_from_page($page, &$itemcount){
    global $VISITED_PAGES, $DB;
    
    if (!isset($VISITED_PAGES)) $VISITED_PAGES = array();

    if (in_array($page->id, $VISITED_PAGES)) return;    
    $VISITED_PAGES[] = $page->id;    
    
    $structure = array();
    
    // get page items from first page. They are located in the center column    
    $select = " pageid = ? AND (position = 'c' OR position = 'r') ";
    $pageitems = $DB->get_records_select('format_page_items', $select, array($page->id), 'position, sortorder');
    
    // analyses course content component stack
	if ($pageitems){
	    foreach($pageitems as $pi){
	    	
	        if (!$pi->cmid){

	            // is a block
	            $b = $DB->get_record('block_instances', array('id' => $pi->blockinstance));
	            $bp = $DB->get_record('block_positions', array('blockinstanceid' => $pi->blockinstance));
	            $blockinstance = block_instance($b->blockname, $b);

	            $element = new StdClass;
	            $element->type = $b->blockname;
	            $element->plugintype = 'block';
	            $element->instance = $b;
	            if ($bp){
		            $element->instance->visible = $bp->visible * $pi->visible; // a block can be hidden by its page_module insertion.
		        } else {
		        	$element->instance->visible = $pi->visible;
		        }
	            $element->name = (!empty($blockinstance->config->title)) ? $blockinstance->config->title : '' ;
	            $element->id = $b->id;
	            // $itemcount++;
	                        
	            // tries to catch modules, pages or resources in content
	
	            $source = @$blockinstance->config->text;

	            // if there is no subcontent, do not consider this bloc in reports.
	            if ($element->subs = page_get_structure_in_content($source, $itemcount)){
	                $structure[] = $element;
	            }            
	        } else {
	            // is a module
	            $cm = $DB->get_record('course_modules', array('id' => $pi->cmid));
	            $module = $DB->get_record('modules', array('id' => $cm->module));
	            
	            switch($module->name){
	                case 'customlabel':
	                case 'label':
	                case 'pagemenu':
		                break;
	                default:{
	                    $element = new StdClass;
	                    $element->type = $module->name;
	                    $element->plugin = 'mod';
	                    $moduleinstance = $DB->get_record($module->name, array('id' => $cm->instance));
	                    $element->name = $moduleinstance->name;
	                    $element->instance = $cm;
	                    $element->instance->visible = $element->instance->visible * $pi->visible; // a bloc can be hidden by its page_module insertion.
	                    $element->id = $cm->id;
	                    $structure[] = $element;
	                    $itemcount++;
	                }
	            }
	        }
		}
	}
	
	if (!empty($page->childs)){
        foreach($page->childs as $key => $child){
            if (!($child->display > FORMAT_PAGE_DISP_HIDDEN)) continue;
            
            $pageelement = new StdClass;
            $pageelement->type = 'page';
            $pageelement->name = format_string($child->nametwo);
            
            $pageelement->subs = page_get_structure_from_page($child, $itemcount);
            $structure[] = $pageelement;
        }
	}
	
    return $structure;
}

/**
* get substructures hidden in content. this applies to content in HTML blocks that
* may be inserted in page based formats. Not applicable to topic and weekly format.
*
* @param string $source the textual source code of the content
* @param reference $itemcount a recursive propagating counter in case of flexipage
* or recursive content.
*/
function page_get_structure_in_content($source, &$itemcount){
    global $VISITED_PAGES, $DB;

    $structure = array();

    // get all links
    $pattern = '/href=\\"(.*)\\"/';
    preg_match_all($pattern, $source, $matches);
    if (isset($matches[1])){
        foreach($matches[1] as $href){
            // jump to another page
            if (preg_match('/course\\/view.php\\?id=(\\d+)&page=(\\d+)/', $href, $matches)){
                if (in_array($matches[2], $VISITED_PAGES)) continue;
                $page = $DB->get_record('format_page', array('id' => $matches[2]));
                $element = new StdClass;
                $element->type = 'pagemenu';
                $element->plugin = 'mod';
                $element->subs = page_get_structure_from_page($page, $itemcount);
                $structure[] = $element;
                $VISITED_PAGES[] = $matches[2];
            }
            // points a module
            if (preg_match('/mod\\/([a-z_]+)\\/.*\\?id=(\\d+)/', $href, $matches)){
                $element = new StdClass;
                $element->type = $matches[1];
                $element->plugin = 'mod';
                $module = $DB->get_record('modules', array('name' => $element->type));
                $cm = $DB->get_record('course_modules', array('id' => $matches[2]));
                $moduleinstance = $DB->get_record($element->type, array('id' => $cm->instance));
                $element->name = $moduleinstance->name;
                $element->instance = &$cm;
                $element->id = $cm->id;
                $structure[] = $element;
                $itemcount++;
            }
        }
    }

    return $structure;
}


/**
* special time formating
* xlsd stands for xls duration
*/
function training_reports_format_time($timevalue, $mode = 'html'){
    if ($timevalue){
        if ($mode == 'html'){
            return format_time($timevalue);
        } elseif($mode == 'xlsd') {
        	$secs = $timevalue % 60;
        	$mins = floor($timevalue / 60);
        	$hours = floor($mins / 60);
        	$mins = $mins % 60;
        	
	        if ($hours > 0) return "{$hours}h {$mins}m {$secs}s";
	        if ($mins > 0) return "{$mins}m {$secs}s";
	        return "{$secs}s";
        } else {
            // for excel time format we need have a fractional day value
            return strftime('%Y-%m-%d %H:%I:%S (%a)', $timevalue);
            // return  $timevalue / DAYSECS;
        }
    } else {
    	if ($mode == 'html'){
	        return get_string('unvisited', 'report_trainingsessions');
	    }
	    return '';
    }
}

/**
* sets up a set fo formats
* @param object $workbook
* @return array of usable formats keyed by a label
*
* Formats : 
* t : Big Title
* tt : section caption
* p : bolded paragraph
* z : numeric (normal)
* zt : time format
* zd : date format
*/
function training_reports_xls_formats(&$workbook){
	$xls_formats = array();
	// titles
    $xls_formats['t'] = $workbook->add_format();
    $xls_formats['t']->set_size(20);
    $xls_formats['tt'] = $workbook->add_format();
    $xls_formats['tt']->set_size(10);
    $xls_formats['tt']->set_color(1);
    $xls_formats['tt']->set_fg_color(4);
    $xls_formats['tt']->set_bold(1);

	// paragraphs
    $xls_formats['p'] = $workbook->add_format();
    $xls_formats['p']->set_size(10);
    $xls_formats['p']->set_bold(0);

    $xls_formats['pb'] = $workbook->add_format();
    $xls_formats['pb']->set_size(10);
    $xls_formats['pb']->set_bold(1);

    $xls_formats['a1'] = $workbook->add_format();
    $xls_formats['a1']->set_size(14);
    $xls_formats['a1']->set_fg_color(31);
    $xls_formats['a2'] = $workbook->add_format();
    $xls_formats['a2']->set_size(12);
    $xls_formats['a3'] = $workbook->add_format();
    $xls_formats['a3']->set_size(10);

    $xls_formats['z'] = $workbook->add_format();
    $xls_formats['z']->set_size(9);
    $xls_formats['zt'] = $workbook->add_format();
    $xls_formats['zt']->set_size(9);
    $xls_formats['zt']->set_num_format('[h]:mm:ss');
    $xls_formats['zd'] = $workbook->add_format();
    $xls_formats['zd']->set_size(9);
    $xls_formats['zd']->set_num_format('aaaa/mm/dd hh:mm');
    
    return $xls_formats;
}

/**
* initializes a new worksheet with static formats
* @param int $userid
* @param int $startrow
* @param array $xls_formats
* @param object $workbook
* @return the initialized worksheet.
*/
function training_reports_init_worksheet($userid, $startrow, &$xls_formats, &$workbook, $purpose = 'usertimes'){
    global $DB, $CFG;
    
    $user = $DB->get_record('user', array('id' => $userid));

	if ($purpose == 'usertimes' || $purpose == 'allcourses'){
		if ($CFG->latinexcelexport){
	    	$sheettitle = mb_convert_encoding(fullname($user), 'ISO-8859-1', 'UTF-8');		
	    } else {
	    	$sheettitle = fullname($user);
	    }
	} else {
		if ($CFG->latinexcelexport){
    		$sheettitle = mb_convert_encoding(fullname($user), 'ISO-8859-1', 'UTF-8').' ('.get_string('sessions', 'report_trainingsessions').')';
    	} else {
    		$sheettitle = fullname($user).' ('.get_string('sessions', 'report_trainingsessions').')';
    	}
	}

    $worksheet = $workbook->add_worksheet($sheettitle);
	if ($purpose == 'usertimes'){
    	$worksheet->set_column(0,0,24);
	    $worksheet->set_column(1,1,64);
    	$worksheet->set_column(2,2,12);
    	$worksheet->set_column(3,3,4);
	} elseif ($purpose == 'allcourses'){
    	$worksheet->set_column(0,0,50);
	    $worksheet->set_column(1,1,50);
    	$worksheet->set_column(2,2,12);
    	$worksheet->set_column(3,3,4);
	} else {
    	$worksheet->set_column(0,0,30);
	    $worksheet->set_column(1,1,30);
    	$worksheet->set_column(2,2,20);
    	$worksheet->set_column(3,3,10);
	}
    $worksheet->set_column(4,4,12);
    $worksheet->set_column(5,5,4);
    $worksheet->set_column(6,6,12);
    $worksheet->set_column(7,7,4);
    $worksheet->set_column(8,8,12);
    $worksheet->set_column(9,9,4);
    $worksheet->set_column(10,10,12);
    $worksheet->set_column(11,11,4);
    $worksheet->set_column(12,12,12);
    $worksheet->set_column(13,13,4);

    $worksheet->set_row($startrow - 1, 12, $xls_formats['tt']);
    $worksheet->write_string($startrow - 1, 0, get_string('firstaccess', 'report_trainingsessions'), $xls_formats['tt']);
    $worksheet->write_string($startrow - 1, 1, get_string('item', 'report_trainingsessions'), $xls_formats['tt']);
    $worksheet->write_string($startrow - 1, 2, get_string('elapsed', 'report_trainingsessions'), $xls_formats['tt']);
    $worksheet->write_string($startrow - 1, 3, get_string('hits', 'report_trainingsessions'), $xls_formats['tt']);
    
    return $worksheet;
}

/**
* a raster for printing in raw format 
* with all the relevant data about a user.
*
*/
function trainingsessions_print_globalheader_raw($userid, $courseid, &$data, &$rawstr, $from, $to){
    global $CFG, $COURSE, $DB;

    $user = $DB->get_record('user', array('id' => $userid));
    if ($courseid != $COURSE->id){
	    $course = $DB->get_record('course', array('id' => $courseid));
	} else {
		$course = &$COURSE;
	}

	$resultset = array();
    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');

    if (!empty($usergroups)){
        foreach($usergroups as $group){
            $str = $group->name;        
            if ($group->id == groups_get_course_group($course)){
                $str = "$str";
            }
            $groupnames[] = $str;
        }
        $resultset[] = implode(', ', $groupnames); // entity        
    } else {
        $resultset[] = get_string('outofgroup', 'report_trainingsessions'); // entity        
    }

	$resultset[] = $user->id; // userid
	$firstenroll = $DB->get_field_select('user_enrolments', 'MIN(timestart)', " timestart != 0 AND userid = ? ", array($user->id));
	$resultset[] = ($firstenroll) ? date('d/m/Y', $firstenroll) : '' ; // from date
	$firstlogin = $DB->get_field_select('log', 'MIN(time)', " userid = ? AND action = 'login' ", array($user->id));
	$resultset[] = ($firstlogin) ? date('d/m/Y', $firstlogin) : '' ; // firstlogin
	$lastlogin = $DB->get_field_select('log', 'MAX(time)', " userid = ? AND action = 'login' ", array($user->id));
	$resultset[] = ($lastlogin) ? date('d/m/Y', $lastlogin) : '' ; // firstlogin
	$resultset[] = date('d/m/Y', $from); // from date
	$resultset[] = date('d/m/Y', $to); // to date
	$resultset[] = date('d/m/Y', $to - DAYSECS * 7); // last week of period
	if (!empty($CFG->report_trainingsessions_csv_iso)){
		$namestr = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->lastname))), 'ISO-8859-1', 'UTF-8');
	} else {
		$namestr = strtoupper(trim(preg_replace('/\s+/', ' ', $user->lastname)));
	}
	$namestr = mb_ereg_replace('/é|è|ë|ê/', 'E', $namestr);
	$namestr = mb_ereg_replace('/ä|a/', 'A', $namestr);
	$namestr = mb_ereg_replace('/ç/', 'C', $namestr);
	$namestr = mb_ereg_replace('/ü|ù|/', 'U', $namestr);
	$namestr = mb_ereg_replace('/î/', 'I', $namestr);
	$resultset[] = $namestr;
	if (!empty($CFG->report_trainingsessions_csv_iso)){
		$namestr = mb_convert_encoding(strtoupper(trim(preg_replace('/\s+/', ' ', $user->firstname))), 'ISO-8859-1', 'UTF-8');
	} else {
		$namestr = strtoupper(trim(preg_replace('/\s+/', ' ', $user->firstname)));
	}
	$namestr = mb_ereg_replace('/é|è|ë|ê/', 'E', $namestr);
	$namestr = mb_ereg_replace('/ä|a/', 'A', $namestr);
	$namestr = mb_ereg_replace('/ç/', 'C', $namestr);
	$namestr = mb_ereg_replace('/ü|ù|/', 'U', $namestr);
	$namestr = mb_ereg_replace('/î/', 'I', $namestr);
	$resultset[] = $namestr;

    $resultset[] = raw_format_duration(@$data->elapsed); // elapsed time
    $resultset[] = raw_format_duration(@$data->weekelapsed); // elapsed time this week

    // $context = context_course::instance($courseid);
	// $roles = get_user_roles_in_context($userid, $context);
	// $resultset[] = $roles;

	if (!empty($CFG->report_trainingsessions_csv_iso)){
		$rawstr .= mb_convert_encoding(implode(';', $resultset)."\n", 'ISO-8859-1', 'UTF-8');
	} else {
		$rawstr .= implode(';', $resultset)."\n";
	}
}

function raw_format_duration($secs){
	$min = floor($secs / 60);
	$hours = floor($min / 60);
	$days = floor($hours / 24);

	$hours = $hours - $days * 24;
	$min = $min - ($days * 24 * 60 + $hours * 60);
	$secs = $secs - ($days * 24 * 60 * 60 + $hours * 60 * 60 + $min * 60);
	
	if ($days){	
		return $days.' '.get_string('days')." $hours ".get_string('hours')." $min ".get_string('min')." $secs ".get_string('secs');
	}
	if ($hours){	
		return $hours.' '.get_string('hours')." $min ".get_string('min')." $secs ".get_string('secs');
	}
	if ($min){	
		return $min.' '.get_string('min')." $secs ".get_string('secs');
	}
	return $secs.' '.get_string('secs');
}


/**
*
*
*/
function trainingsessions_get_course_users($courseid){
	global $DB;

    $sql = "SELECT 
    			DISTINCT u.id, u.firstname, u.lastname, u.idnumber
            FROM 
            	{user} u
            JOIN 
            	{user_enrolments} ue 
            ON 
            	ue.userid = u.id
            JOIN 
            	{enrol} e 
           	ON 
           		e.id = ue.enrolid
			ORDER BY 
				u.firstname ASC, 
				u.lastname ASC
    ";
	
    $users = $DB->get_records_sql($sql, null);
    
    return $users;
	
}
