<?php

/**
* a raster for html printing of a report structure.
*
* @param string ref $str a buffer for accumulating output
* @param object $structure a course structure object.
*/
function training_reports_print_allcourses_html(&$str, &$aggregate){
	global $CFG, $COURSE, $OUTPUT, $DB;

	$output = array();
	$courses = array();
	$courseids = array();
	$return = new StdClass;
	$return->elapsed = 0;
	$return->events = 0;

	if (!empty($aggregate['coursetotal'])){	
		foreach($aggregate['coursetotal'] as $cid => $cdata){
			if ($cid != 0){
				if (!in_array($cid, $courseids)){
					$courses[$cid] = $DB->get_record('course', array('id' =>  $cid), 'id,idnumber,shortname,fullname,category');
					$courseids[$cid] = '';
				}
				@$output[$courses[$cid]->category][$cid] = $cdata;
				@$catids[$courses[$cid]->category] = '';
			} else {
				// echo "ignoring hidden $cdata->elapsed ";
				$output[0][SITEID]->elapsed += $cdata->elapsed;
				$output[0][SITEID]->events += $cdata->events;
			}
			$return->elapsed += $cdata->elapsed;
			$return->events += $cdata->events;
		}

		$coursecats = $DB->get_records_list('course_categories', 'id', array_keys($catids));
	}

	if (!empty($output)){	
		
		$elapsedstr = get_string('elapsed', 'report_trainingsessions');
		$hitsstr = get_string('hits', 'report_trainingsessions');
		$coursestr = get_string('course');
		
		if (isset($output[0])){
			$str .= '<h2>'.get_string('site').'</h2>';
			$str .= $elapsedstr.' : '.format_time($output[0][SITEID]->elapsed).'<br/>';
			$str .= $hitsstr.' : '.$output[0][SITEID]->events;
		}
		
		foreach($output as $catid => $catdata){
			if ($catid == 0) continue;
			$str .= '<h2>'.$coursecats[$catid]->name.'</h2>';
			$str .= '<table class="generaltable" width="100%">';
			$str .= '<tr class="header"><td class="header c0" width="70%"><b>'.$coursestr.'</b></td><td class="header c1" width="15%"><b>'.$elapsedstr.'</b></td><td class="header c2" width="15%"><b>'.$hitsstr.'</b></td></tr>';
			foreach($catdata as $cid => $cdata){
				$ccontext = context_course::instance($cid);
				if (has_capability('report/trainingsessions:view', $ccontext)){
					$str .= '<tr valign="top"><td>'.$courses[$cid]->fullname.'</td><td>';
					$str .= format_time($cdata->elapsed).'<br/>';
					$str .= '</td><td>';
					$str .= $cdata->events;
					$str .= '</td></tr>';
				} else {
					$str .= '<tr valign="top"><td>'.$courses[$cid]->fullname.'</td><td colspan="2">';
					$str .= get_string('nopermissiontoview', 'report_trainingsessions');
					$str .= '</td></tr>';
				}
			}
			$str .= '</table>';
		}
	} else {
		$str .= $OUTPUT->box(get_string('nodata', 'report_trainingsessions'), 'generalbox', '', true);
	}

	return $return;
}

/**
* a raster for html printing of a report structure.
*
* @param string ref $str a buffer for accumulating output
* @param object $structure a course structure object.
*/
function training_reports_print_html(&$str, $structure, &$aggregate, &$done, $indent='', $level = 1){
	global $CFG, $COURSE;
	
    if (isset($CFG->block_use_stats_ignoremodules)){
        $ignoremodulelist = explode(',', $CFG->block_use_stats_ignoremodules);
    } else {
    	$ignoremodulelist = array();
    }

    if (empty($structure)) {
        $str .= get_string('nostructure', 'report_trainingsessions');
        return;
    }

    $indent = str_repeat('&nbsp;&nbsp;', $level);
    $suboutput = '';

    // initiates a blank dataobject
    if (!isset($dataobject)){
    	$dataobject = new StdClass;
        $dataobject->elapsed = 0;
        $dataobject->events = 0;
    }

    if (is_array($structure)){
        // if an array of elements produce sucessively each output and collect aggregates
        foreach($structure as $element){
            if (isset($element->instance) && empty($element->instance->visible)) continue; // non visible items should not be displayed
            $res = training_reports_print_html($str, $element, $aggregate, $done, $indent, $level);
            $dataobject->elapsed += $res->elapsed;
            $dataobject->events += (0 + @$res->events);
        } 
    } else {
    	$nodestr = '';
        if (!isset($structure->instance) || !empty($structure->instance->visible)){ // non visible items should not be displayed
            // name is not empty. It is a significant module (non structural)
            if (!empty($structure->name)){
                $nodestr .= "<table class=\"sessionreport level$level\">";
                $nodestr .= "<tr class=\"sessionlevel{$level}\" valign=\"top\">";
                $nodestr .= "<td class=\"sessionitem item\" width=\"40%\">";
                $nodestr .= $indent;
                if (debugging()){
                    $nodestr .= '['.$structure->type.'] ';
                }
                $nodestr .= shorten_text($structure->name, 85);
                $nodestr .= '</td>';
                $nodestr .= "<td class=\"sessionitem rangedate\" width=\"20%\">";
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])){
                	$nodestr .= date('Y/m/d h:i', 0 + (@$aggregate[$structure->type][$structure->id]->firstaccess));
            	}
                $nodestr .= '</td>';
                $nodestr .= "<td class=\"sessionitem rangedate\" width=\"20%\">";
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])){
                	$nodestr .= date('Y/m/d h:i', 0 + (@$aggregate[$structure->type][$structure->id]->lastaccess));
            	}
                $nodestr .= '</td>';
                $nodestr .= "<td class=\"reportvalue rangedate\" align=\"right\" width=\"20%\">";
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])){
                    $done++;
                    $dataobject = $aggregate[$structure->type][$structure->id];
                } 
                if (!empty($structure->subs)) {
                    $res = training_reports_print_html($suboutput, $structure->subs, $aggregate, $done, $indent, $level + 1);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }                

				if (!in_array($structure->type, $ignoremodulelist)){
					if (!empty($dataobject->timesource) && $dataobject->timesource == 'credit' && $dataobject->elapsed){
						$nodestr .= get_string('credittime', 'block_use_stats');
					}
					if (!empty($dataobject->timesource) && $dataobject->timesource == 'declared' && $dataobject->elapsed){
						$nodestr .= get_string('declaredtime', 'block_use_stats');
					}
	                $nodestr .= training_reports_format_time($dataobject->elapsed, 'html');
	                $nodestr .= ' ('.(0 + @$dataobject->events).')';
	            } else {
	            	$nodestr .= get_string('ignored', 'block_use_stats');
	            }
    
                // plug here specific details
                $nodestr .= '</td>';
                $nodestr .= '</tr>';
                $nodestr .= "</table>\n";
            } else {
                // It is only a structural module that should not impact on level
                if (isset($structure->id) && !empty($aggregate[$structure->type][$structure->id])){
                    $dataobject = $aggregate[$structure->type][$structure->id];
                }
                if (!empty($structure->subs)) {
                    $res = training_reports_print_html($suboutput, $structure->subs, $aggregate, $done, $indent, $level);
                    $dataobject->elapsed += $res->elapsed;
                    $dataobject->events += $res->events;
                }
            }
    
            if (!empty($structure->subs)){
                $str .= "<table class=\"trainingreport subs\">";
                $str .= "<tr valign=\"top\">";
                $str .= "<td colspan=\"2\">";
                $str .= '<br/>';
                $str .= $suboutput;
                $str .= '</td>';
                $str .= '</tr>';
                $str .= "</table>\n";
            }
            $str .= $nodestr;
        }
    }   
    return $dataobject;
}

/**
* a raster for html printing of a report structure header
* with all the relevant data about a user.
*
*/
function training_reports_print_header_html($userid, $courseid, $data, $short = false, $withcompletion = true, $withnooutofstructure = false){
    global $CFG, $DB, $OUTPUT;
    
    $user = $DB->get_record('user', array('id' => $userid));
    $course = $DB->get_record('course', array('id' => $courseid));
    
    echo "<center>";
    echo "<div class=\"report-trainingsession userinfobox\">";

    $usergroups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name');
    echo '<h1>';
    echo $OUTPUT->user_picture($user, array('size' => 32, 'courseid' => $course->id));    
    echo '&nbsp;&nbsp;&nbsp;'.fullname($user).'</h1>';

    // print group status
    if (!empty($usergroups)){
        echo '<b>'.get_string('groups');
        echo ':</b> ';
        foreach($usergroups as $group){
            $str = $group->name;        
            if ($group->id == get_current_group($courseid)){
                $str = "$str";
            }
            $groupnames[] = $str;
        }
        echo implode(', ', $groupnames);
                
    }
    
    // print roles list
    $context = context_course::instance($courseid);
	$roles = role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL);
    echo '<br/><b>'.get_string('roles').':</b> ';
    $userroles = get_user_roles($context, $userid);
    $uroles = array();
    
    foreach($userroles as $rid => $r){
    	$uroles[] = $roles[$r->roleid]->localname;
	}
    echo implode (",", $uroles);

    if (!empty($data->linktousersheet)){
        echo "<br/><a href=\"{$CFG->wwwroot}/report/trainingsessions/index.php?view=user&amp;id={$courseid}&amp;userid=$userid\">".get_string('seedetails', 'report_trainingsessions').'</a>';
    }

	if($withcompletion){
	    // print completion bar
	    if (!empty($data->items)){
	        $completed = $data->done / $data->items;
	    } else {
	        $completed = 0;
	    }
	    $remaining = 1 - $completed;
	    $completedpc = ceil($completed * 100);
	    $remainingpc = 100 - $completedpc;
	    $completedwidth = floor(500 * $completed);
	    $remainingwidth = floor(500 * $remaining);
	
	    echo '<p class="completionbar">';
	    echo '<b>'.get_string('done', 'report_trainingsessions').'</b>';
	    
	    echo "<img src=\"{$CFG->wwwroot}/report/trainingsessions/pix/green.gif\" style=\"width:{$completedwidth}px\" class=\"donebar\" align=\"top\" title=\"{$completedpc} %\" />";
	    echo "<img src=\"{$CFG->wwwroot}/report/trainingsessions/pix/blue.gif\" style=\"width:{$remainingwidth}px\" class=\"remainingbar\" align=\"top\"  title=\"{$remainingpc} %\" />";
	}
    
    // Start printing the overall times
    
    if (!$short){

        echo '<br/><b>';
        echo get_string('equlearningtime', 'report_trainingsessions');
        echo ':</b> '.training_reports_format_time(0 + @$data->elapsed, 'html');
        echo ' ('.(0 + @$data->hits).')';
		echo $OUTPUT->help_icon('equlearningtime', 'report_trainingsessions');

        echo '<br/><b>';
        echo get_string('activitytime', 'report_trainingsessions');
        echo ':</b> '.training_reports_format_time(0 + @$data->activityelapsed, 'html');
		echo $OUTPUT->help_icon('activitytime', 'report_trainingsessions');
    
        // plug here specific details
    }    
    echo '<br/><b>';

    echo get_string('workingsessions', 'report_trainingsessions');
    echo ':</b> '.(0 + @$data->sessions);
    if (@$data->sessions == 0 && (@$completedwidth > 0)){
		echo $OUTPUT->help_icon('checklistadvice', 'report_trainingsessions');
	}
    
    echo '</p></div></center>';

	// add printing for global course time (out of activities)    
    if (!$short){
    	if (!$withnooutofstructure){
			echo $OUTPUT->heading(get_string('outofstructure', 'report_trainingsessions'));    
	        echo "<table cellspacing=\"0\" cellpadding=\"0\" width=\"100%\" class=\"sessionreport\">";
	        echo "<tr class=\"sessionlevel2\" valign=\"top\">";
	        echo "<td class=\"sessionitem\">";
	        print_string('courseglobals', 'report_trainingsessions');
	        echo '</td>';
	        echo "<td class=\"sessionvalue\">";
	        echo training_reports_format_time($data->course->elapsed).' ('.$data->course->hits.')';
	        echo '</td>';
	        echo '</tr>';
		}
        if (isset($data->upload)){
	        echo "<tr class=\"sessionlevel2\" valign=\"top\">";
	        echo "<td class=\"sessionitem\">";
	        print_string('uploadglobals', 'report_trainingsessions');
	        echo '</td>';
	        echo "<td class=\"sessionvalue\">";
	        echo training_reports_format_time($data->upload->elapsed).' ('.$data->upload->hits.')';
	        echo '</td>';
	        echo '</tr>';
	    }
        echo '</table>';
    }
}

/**
* prints a report over each connection session
*
*/
function training_reports_print_session_list(&$str, $sessions, $courseid = 0){
	global $OUTPUT;
	
	$sessionsstr = ($courseid) ? get_string('coursesessions', 'report_trainingsessions') : get_string('sessions', 'report_trainingsessions') ;
	$str .= $OUTPUT->heading($sessionsstr, 2);
	if (empty($sessions)){
		$str .= $OUTPUT->box(get_string('nosessions', 'report_trainingsessions'));
		return;
	}

	// effective printing of available sessions
	$str .= '<table width="100%" id="session-table">';
	$str .= '<tr valign="top">';
	$str .= '<td width="33%"><b>'.get_string('sessionstart', 'report_trainingsessions').'</b></td>';
	$str .= '<td width="33%"><b>'.get_string('sessionend', 'report_trainingsessions').'</b></td>';
	$str .= '<td width="33%"><b>'.get_string('duration', 'report_trainingsessions').'<sup>*</sup></b></td>';
	$str .= '</tr>';
	
	$totalelapsed = 0;

	foreach($sessions as $s){

		if ($courseid && !array_key_exists($courseid, $s->courses)) continue; // omit all sessions not visiting this course

		if (!isset($s->sessionstart)) continue;

		$sessionenddate = (isset($s->sessionend)) ? userdate(@$s->sessionend) : '' ;
		$str .= '<tr valign="top">';
		$str .= '<td>'.userdate($s->sessionstart).'</td>';
		$str .= '<td>'.$sessionenddate.'</td>';
		$str .= '<td>'.format_time(@$s->elapsed).'</td>';
		$str .= '</tr>';
		$totalelapsed += @$s->elapsed;
	}
	$str .= '<tr valign="top">';
	$str .= '<td><br/><b>'.get_string('totalsessions', 'report_trainingsessions').' '.$OUTPUT->help_icon('totalsessiontime', 'report_trainingsessions').'</b></td>';
	$str .= '<td></td>';
	$str .= '<td><br/>'.format_time($totalelapsed).'</td>';
	$str .= '</tr>';

	$str .= '</table>';

	$str .= '<p>(*) '.get_string('elapsedadvice', 'report_trainingsessions').'</p>';
}

function training_reports_print_total_site_html($dataobject){
	$str = '';
	
	$elapsedstr = get_string('elapsed', 'report_trainingsessions');
	$hitsstr = get_string('hits', 'report_trainingsessions');
    $str .= '<br/>';
    $str .= '<b>'.$elapsedstr.':</b> ';
    $str .= training_reports_format_time(0 + $dataobject->elapsed, 'html');
    $str .= helpbutton('totalsitetime', get_string('totalsitetime', 'report_trainingsessions'), 'report_trainingsessions', true, false, '', true);
    $str .= '<br/>';
    $str .= '<b>'.$hitsstr.':</b> ';
    $str .= 0 + @$dataobject->events;
    
    return $str;
}

function reports_print_pager($maxsize, $offset, $pagesize, $url, $contextparms){

	if (is_array($contextparms)){
		$parmsarr = array();
		foreach($contextparms as $key => $value){
			$parmsarr[] = "$key=".urlencode($value);
		}
		$contextparmsstr = implode('&', $parmsarr);
	} else {
		$contextparmsstr = $contextparms;
	}
	
	if (!empty($contextparmsstr)){
		if (strstr($url, '?') === false){
			$url = $url.'?';
		} else {
			$url = $url.'&';
		}
	}

	$str = '';	
	for($i = 0; $i < $maxsize / $pagesize ; $i++){
		if ($offset == $pagesize * $i){
			$str .= ' <b>'.($i + 1).'</b> ';
		} else {
			$useroffset = $i * $pagesize;
			$str .= " <a href=\"{$url}{$contextparmsstr}&useroffset=$useroffset\">".($i + 1).'</a> ';
		}
	}
	return $str;
}

function training_reports_print_completionbar($items, $done, $width){

	global $CFG;
	$str = '';

    if (!empty($items)){
        $completed = $done / $items;
    } else {
        $completed = 0;
    }
    $remaining = 1 - $completed;
    $remainingitems = $items - $done;
    $completedpc = ceil($completed * 100)."% $done/$items";
    $remainingpc = floor(100 * $remaining)."% $remainingitems/$items";
    $completedwidth = floor($width * $completed);
    $remainingwidth = floor($width * $remaining);

    $str .= '<p class="completionbar">';
    $str .= '<b>'.get_string('done', 'report_trainingsessions').'</b>';
    
    $str .= "<img src=\"{$CFG->wwwroot}/course/report/trainingsessions/pix/green.gif\" style=\"width:{$completedwidth}px\" class=\"donebar\" align=\"top\" title=\"{$completedpc}\" />";
    $str .= "<img src=\"{$CFG->wwwroot}/course/report/trainingsessions/pix/blue.gif\" style=\"width:{$remainingwidth}px\" class=\"remainingbar\" align=\"top\"  title=\"{$remainingpc}\" />";

	return $str;
}