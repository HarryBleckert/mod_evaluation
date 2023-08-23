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
 * shows an analysed view of a evaluation on the mainsite
 *
 * @author mod_evaluation: Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_evaluation
 * branch created by by Harry.Bleckert@ASH-Berlin.eu to allow course teachers view results 
 */

require_once("../../config.php");
require_once("lib.php");
global $DB, $USER;

$id = required_param('id', PARAM_INT);  //the POST dominated the GET
$courseitemfilter = optional_param('courseitemfilter', '0', PARAM_INT);
$courseitemfiltertyp = optional_param('courseitemfiltertyp', '0', PARAM_ALPHANUM);
$courseid = optional_param('courseid', false, PARAM_INT);
$course_of_studiesID = optional_param('course_of_studiesID', false, PARAM_INT);
$teacherid = optional_param('teacherid', false, PARAM_INT);
$TextOnly = optional_param('TextOnly', false, PARAM_INT);

$Chart = optional_param('Chart', false, PARAM_ALPHANUM);
$SetShowGraf = optional_param('SetShowGraf', 'verbergen', PARAM_ALPHANUM);
if ( !isset($_SESSION["Chart"]) ) {	$_SESSION["Chart"] = "bar"; }
if ( empty($Chart) ) {	$Chart = $_SESSION["Chart"]; }
else {	$_SESSION["Chart"] = $Chart; }

$urlparams = ['id' => $id];
$url = new moodle_url('/mod/evaluation/analysis_course.php', array('id'=>$id ) ); // ,'courseid' => $courseid ) );
navigation_node::override_active_url($url);


$url->param('Chart', $Chart);$urlparams = ['Chart' => $Chart];
if ($courseitemfilter !== '0') {
    $url->param('courseitemfilter', $courseitemfilter);
}
if ($courseitemfiltertyp !== '0') {
    $url->param('courseitemfiltertyp', $courseitemfiltertyp);
}

list($course, $cm) = get_course_and_cm_from_cmid($id, 'evaluation');
$context = context_module::instance($cm->id);

require_course_login($course, true, $cm); 

$evaluation = $PAGE->activityrecord;


// handle CoS privileged user
$cosPrivileged = evaluation_cosPrivileged( $evaluation );
if ( $cosPrivileged )
{	if ( $teacherid AND !ev_is_user_in_CoS( $evaluation, $teacherid ) )
	{	$teacherid = false; }
	if ( $courseid AND !ev_is_course_in_CoS( $evaluation, $courseid ) )
	{	$courseid = false;	}
	if ( $course_of_studiesID )
	{	$course_of_studies = evaluation_get_course_of_studies_from_evc( $course_of_studiesID, $evaluation ); 
		if ( !in_array( $course_of_studies, $_SESSION['CoS_privileged'][$USER->username] ) )
		{	$course_of_studiesID = false; }
	}
}


$course_of_studies = false;
if ( $course_of_studiesID )
{	$course_of_studies = evaluation_get_course_of_studies_from_evc( $course_of_studiesID, $evaluation ); 
	$url->param('course_of_studiesID', $course_of_studiesID); $urlparams['course_of_studiesID'] = $course_of_studiesID; 
}

if ( $courseid AND $evaluation->course == SITEID )
{	$url->param('courseid', $courseid); $urlparams['courseid'] = $courseid; }
if ($teacherid) {	$url->param('teacherid', $teacherid); $urlparams['teacherid'] = $teacherid; }


// set PAGE layout and print the page header
$evurl = new moodle_url('/mod/evaluation/analysis_course.php', array('id'=>$id ) ); //,'courseid' => $courseid ) );
evSetPage( $url , $evurl, get_string("analysis","evaluation") );

// handle CoS priveleged user
if ( !empty($_SESSION['CoS_privileged'][$USER->username]) )
{	print "Auswertungen der Studiengänge: " . '<span style="font-weight:600;white-space:pre-line;">'
							.implode(", ", $_SESSION['CoS_privileged'][$USER->username]) . "</span><br>\n";
}


$icon = '<img src="pix/icon120.png" height="30" alt="'.$evaluation->name.'">';
echo $OUTPUT->heading( $icon. "&nbsp;" .format_string($evaluation->name) );

list($isPermitted, $CourseTitle, $CourseName, $SiteEvaluation) = evaluation_check_Roles_and_Permissions( $courseid, $evaluation, $cm, true );
if ( !isset( $_SESSION['myEvaluations'] ) )
{	$_SESSION["myEvaluations"] = get_evaluation_participants($evaluation, $USER->id ); $_SESSION["myEvaluationsName"]  = $evaluation->name; }

$evaluationstructure = new mod_evaluation_structure($evaluation, $PAGE->cm, $courseid, null, 0, $teacherid, $course_of_studies, $course_of_studiesID);


$completed_responses = $evaluationstructure->count_completed_responses();
$minresults = evaluation_min_results($evaluation);
$minresultsText = min_results_text($evaluation);
$minresultsPriv = min_results_priv($evaluation);
if ( defined('EVALUATION_OWNER') AND !evaluation_cosPrivileged( $evaluation ) )
{	$minresults = $minresultsText = $minresultsPriv; }

$numTextQ = evaluation_count_qtype( $evaluation, "textarea" );
$is_open 	= evaluation_is_open($evaluation);


$isTeacher = defined('isTeacher');
$isStudent = defined('isStudent');
if ( !empty($_SESSION["myEvaluations"]) ) 
{	if ( !$isTeacher )
	{	$isTeacher = evaluation_is_teacher( $evaluation, $_SESSION["myEvaluations"] ); }
	if ( !$isStudent ) 
	{	$isStudent = evaluation_is_student( $evaluation, $_SESSION["myEvaluations"] ); }
}

/*$Teacher 	= ( ( defined('EVALUATION_OWNER') OR defined("isStudent")) ? false 
			: evaluation_is_teacher( $evaluation, $_SESSION["myEvaluations"], $courseid ));*/
			//echo "Teacher: $Teacher - SESSION['myEvaluations']: ".nl2br(var_export($_SESSION["myEvaluations"],true));
$Teacher 	= evaluation_is_teacher( $evaluation, $_SESSION["myEvaluations"], $courseid );
			
$showUnmatched_minResults 	= false;
if ( $Teacher ) 			
{	$showUnmatched_minResults 	= ($completed_responses >= $minresults AND $completed_responses < $minresultsText); }
elseif ( defined('EVALUATION_OWNER') AND !evaluation_cosPrivileged( $evaluation ))
{	$showUnmatched_minResults 	= ($completed_responses < $minresultsPriv); }

/// print the tabs
$current_tab = 'analysis';
if ( $Teacher AND !$courseid )
{	if ($teacherid ) { $current_tab = 'analysisTeacher'; }
	else { $current_tab = 'analysisASH'; }
}
elseif ( (!$isPermitted AND !$courseid) AND !$is_open )
{	$current_tab = 'analysisASH'; }
require('tabs.php');


if ( $SiteEvaluation AND !$courseid AND (!defined('EVALUATION_OWNER') ?true :!$cosPrivileged) )
{	$CourseTitle = "\n<span style=\"font-size:12pt;font-weight:bold;display:inline;\">".get_string("all_courses","evaluation")."</span>"; }

$Studiengang = ""; 
$numTeachers = 0;
if ($courseid AND $courseid !== SITEID )
{	$Studiengang = evaluation_get_course_of_studies($courseid,true);  // get Studiengang with link
	$semester 	 = evaluation_get_course_of_studies($courseid,true,true);  // get Semester with link
	if ( !empty($Studiengang) )	
	{	$Studiengang = get_string("course_of_studies","evaluation").": <span style=\"font-size:12pt;font-weight:bold;display:inline;\">"
						.$Studiengang.(empty($semester) ?"" :" <span style=\"font-size:10pt;font-weight:normal;\">(".$semester.")</span>") . "</span><br>\n"; 
	}
	echo $Studiengang . $CourseTitle;
	if ( $courseid AND defined( "showTeachers") ) 
	{	echo showTeachers; }
	$numTeachers = safeCount($_SESSION["allteachers"][$courseid]);
}

//if ( $is_open AND $Teacher )
if ( $Teacher ) // and !$courseid ) 
{	$Teacher = !evaluation_is_student( $evaluation, $_SESSION["myEvaluations"], $courseid ); }
if ( $Teacher )
{	//if ( !defined( "isTeacher") ) { define( "isTeacher", true ); }
	
	if ( ($is_open ) AND $teacherid != $USER->id ) // OR $courseid
	{	$teacherid = $USER->id; 
		redirect(new moodle_url($url,['teacherid' => $teacherid, 'courseid' => $courseid] ));
	}
	else
	{	if ( $teacherid == $USER->id AND $numTeachers>1) // $evaluation->teamteaching )
		{	echo '<br><span style="font-size:12pt;font-weight:bold;display:inline;color:blue;">'
				."Evaluationen für ".get_string('teacher','evaluation').': '.$USER->firstname. ' ' .$USER->lastname."</span><br>\n"; 
		}
		elseif ( false )
		{	if ( $teacherid AND $courseid AND safeCount($_SESSION["allteachers"][$courseid]) >1 )
			{	echo "TeamTeaching war in dieser Evaluation nicht aktiviert und es gab mehr als eine Dozent_in in diesem Kurs. 
					  Daher können Sie die Kursauswertung nicht einsehen!<br>\n"; 
			}
			elseif ( !$courseid )
			{	echo "TeamTeaching war in dieser Evaluation nicht aktiviert. Daher werden die Auswertungen von Kursen, 
					  die mehr als eine Dozent_in hatten hier ignoriert!";
			}
		}
	}
}
if ( $numTextQ AND $showUnmatched_minResults )
{	echo '<br><b style="color:red;">Für diese Auswertung wurden weniger als '.($minresultsText)
			   . " Abgaben gemacht. Daher können Sie keine Textantworten einsehen!</b><br>\n"; 
}



//get the groupid
//lstgroupid is the choosen id
$mygroupid = false;

/* testing combined form:
$studyselectform = new mod_evaluation_filters_select_form($url, $evaluationstructure, $evaluation->course == SITEID);
if ( $data = $studyselectform->get_data() ) {
	redirect(new moodle_url($url, ['course_of_studies' => $data->course_of_studies]));
}
echo "\n".'<div style="display:inline;float:left;" class="d-print-none">'; // do not print
$studyselectform->display(); 
*/

// Output to printer only / Print Only (Hide on screen only)
if ( !$courseid )
{	print '<div class="d-none d-print-block">';
	print  get_string("course_of_studies","evaluation").': <span style="font-size:12pt;font-weight:bold;display:inline;">';
	if ( $course_of_studies )
	{	print $course_of_studies; } else {	print get_string('fulllistofstudies','evaluation'); }
	print "</span><br>\n";
	print  get_string("teacher","evaluation").': <span style="font-size:12pt;font-weight:bold;display:inline;">';
	if ( $teacherid )
	{	print evaluation_get_user_field( $teacherid, 'fullname' ); } else {	print get_string('fulllistofteachers','evaluation'); }
	print '</span></div>';
}	

// show loading spinner
evaluation_showLoading();  


// set filter forms
if ( has_capability('mod/evaluation:viewreports', $context) || defined('EVALUATION_OWNER')) 
{ 	
	echo "\n".'<div style="display:none;" id="evFilters" class="d-print-none">';
	if ( is_siteadmin() ) {	echo '<span id="evFiltersMsg"></span>'; } //<b>'.EVALUATION_OWNER.'</b>
	if ( $SiteEvaluation AND !$courseid )
	{	// Process course of studies select form.
		$studyselectform = new mod_evaluation_course_of_studies_select_form($url, $evaluationstructure, $evaluation->course == SITEID);
		if ( $data = $studyselectform->get_data() ) 
		{	evaluation_spinnerJS( false );
			redirect(new moodle_url($url, ['course_of_studiesID' => $data->course_of_studiesID]),"",0);
		}
		echo "\n".'<div style="display:inline;float:left;" class="d-print-none">'; // do not print
		$studyselectform->display(); 
		echo "</div>\n";
	}
	if ( $SiteEvaluation ) 
	{	// Process course select form.
		$courseselectform = new mod_evaluation_course_select_form($url, $evaluationstructure, $evaluation->course == SITEID);
		if ( $data = $courseselectform->get_data() )
		{	evaluation_spinnerJS( false );
			redirect(new moodle_url($url, ['courseid' => $data->courseid]),"",0); // );
		}
		echo "\n".'<div style="display:inline;float:left;" class="d-print-none">'; // do not print
		$courseselectform->display(); 
		echo "</div>\n";
	}	
	//print safeCount($_SESSION["allteachers"][$courseid]);nl2br(var_export($_SESSION["allteachers"][$courseid]));
	if ( !$courseid OR safeCount($_SESSION["allteachers"][$courseid]) >1 )  // ( $evaluation->teamteaching ) )
	{	// Process teachers select form.	
		$teacherselectform = new mod_evaluation_teachers_select_form($url, $evaluationstructure, $evaluation->course == SITEID);
		if ( $data = $teacherselectform->get_data() ) 
		{	evaluation_spinnerJS( false );
			redirect(new moodle_url($url, ['teacherid' => $data->teacherid]),"",0); //  );
		}
		echo "\n".'<div style="display:inline;float:left;" class="d-print-none">'; // do not print
		$teacherselectform->display(); 
		echo "</div>\n";
	}
	echo '</div><div style="display:block;clear:both;">&nbsp;</div>'."\n";
}

echo "\n".'<div id="evButtons" style="display:none;" class="d-print-none">';   // no printing


// do not show results if less than minimum required evaluations
// before July 28,2022: !defined('EVALUATION_OWNER') ?true :$cosPrivileged 

if ( !$completed_responses )
{	evaluation_spinnerJS(false);
	$teacherTxt = ($Teacher AND $teacherid) ?" für Sie." :"";
	echo "</div>". $OUTPUT->notification(get_string('no_responses_yet', 'mod_evaluation') .$teacherTxt); 
	echo $OUTPUT->footer(); exit; 
}
if ( $completed_responses < $minresults )
{	evaluation_spinnerJS(false);
	$teacherTxt = ($Teacher AND $teacherid) ?" Es werden nur die Abgaben für Sie ausgewertet." :"";
	echo "</div><p style=\"color:red;font-weight:bold;align:center;\">".get_string('min_results', 'evaluation',$minresults)   .$teacherTxt."</p>"; 
	if ( !is_siteadmin() )
	{	echo $OUTPUT->footer(); exit; }
}

$evaluation_has_user_participated = evaluation_has_user_participated($evaluation, $USER->id, $courseid );
$non_participated_student = ($courseid AND defined('isStudent') AND !$evaluation_has_user_participated);
// check access rights
if ( !defined('EVALUATION_OWNER') AND !$Teacher )  
{	if	( 	( !$courseid AND $is_open ) 
			OR ( !defined('isStudent') AND ($teacherid OR $course_of_studies OR $courseid) )
			OR $non_participated_student
		) 
	{	evaluation_spinnerJS(false); 
		$txt = "";
		if ($non_participated_student){
			$txt = "Sie haben für diesen Kurs NICHT an der Evaluation teilgenommen. ";
		}
		
		print '<br><h2 style="font-weight:bold;color:darkred;background-color:white;">'
				.$txt.get_string('no_permission_analysis', 'evaluation') ."</h2><br>"; 
		print $OUTPUT->continue_button("/mod/evaluation/view.php?id=$id");
		print $OUTPUT->footer(); exit; 
	}
}


$buttonStyle = 'margin: 3px 5px;font-weight:bold;color:white;background-color:teal;';


// Button Auswertung drucken
echo '<div style="float:left;">';
echo evPrintButton();
echo '</div>';

// show Evaluations per course to privileged persons - moved to view.php
if ( false AND defined('EVALUATION_OWNER') )
{	echo '<div style="float:left;'.$buttonStyle.'">';
	print html_writer::tag( 'a', "Abgaben/Kurs", array('style'=>$buttonStyle, 
								'href' => 'print.php?id='.$id.'&courseid='.$courseid.'&showResults=6&goBack=analysis_course'));
	echo '</div>';
}

// compare Evaluation results of filter with complete results to teachers and privileged persons	
if ( false ) //true OR $Teacher OR defined('EVALUATION_OWNER') )
{	$stats = get_string("statistics", "evaluation") . " mit Vergleich"; // . (($courseid OR $course_of_studiesID OR $teacherid) ?" mit Vergleich" :"");
	?>
	<div style="float:left;">
<form style="display:inline;" method="POST" action="print.php">
<button name="showCompare" style="<?php echo $buttonStyle;?>" value="1" onclick="this.form.submit();"><?php echo $stats;?></button>
<input type="hidden" name="id" value="<?php echo $id;?>">
<input type="hidden" name="courseid" value="<?php echo $courseid;?>">
<input type="hidden" name="course_of_studiesID" value="<?php echo$course_of_studiesID;?>">
<input type="hidden" name="teacherid" value="<?php echo $teacherid;?>">
</form>
</div>
<?php
}

// Button "Export to excel".
//if ( ($isPermitted OR has_capability('mod/evaluation:viewreports', $context)) AND $evaluationstructure->count_completed_responses()) {
if ( ($isPermitted OR ($Teacher AND $teacherid)) AND $evaluationstructure->count_completed_responses()) {
    echo '<div style="float:left;'.$buttonStyle.'">';
	print html_writer::tag( 'a', get_string('export_to_excel', 'evaluation'), array('style'=>$buttonStyle, 
							'href' => 'analysis_to_excel.php?sesskey='.sesskey().'&id='.$id.'&courseid='.(int)$courseid.
							'&teacherid='.(int)$teacherid.'&course_of_studiesID='.(int)$course_of_studiesID));
							//'params' => ['sesskey' => sesskey(), 'id' => $id, 'courseid' => (int)$courseid]));
	echo '</div>';
}


// show / print only text
if ( $numTextQ AND ((( !$showUnmatched_minResults AND ($cosPrivileged OR $Teacher )) ) OR ( defined('EVALUATION_OWNER') ?!$cosPrivileged :false ) ) )
{
?>
<div style="float:left;">
<form style="display:inline;" method="POST">
<button name="TextOnly" style="<?php echo $buttonStyle;?>" value="1" onclick="this.form.submit();">Nur Text</button>
</form>
</div>
<?php
}


$showGraf = (strstr($SetShowGraf,"anzeigen") ?"true" :"false");
$sGstatus = ($showGraf == "true" ?"verbergen" :"anzeigen");
// select chart types
?>
<div style="float:left;">
<form style="display:inline;" method="POST">
&nbsp;<input name="SetShowGraf" id="SetShowGraf" type="submit" style="<?php echo $buttonStyle;?>" value="Grafikdaten <?php echo $sGstatus;?>">
&nbsp;<input type="submit" style="<?php echo $buttonStyle;?>" value="Grafik:">
<select name="Chart" style="<?php echo $buttonStyle;?>" onchange="this.form.submit();">
<?php
$charts = array ("bar"=>"Balken -horizontal","stacked"=>"Balken -vertikal","line"=>"Liniendiagramm","linesmooth"=>"Liniendiagramm -gerundet",
				"pie"=>"Kreisdiagramm","doughnut"=>"Kreisdiagramm -Donut");
foreach ( $charts AS $chart => $label )
{	$selected = "";
	if ( $chart == $Chart )
	{	$selected = ' selected="'.$selected.'" '; }
	print '<option value="'.$chart.'"'.$selected.'>'.$label."</option>\n";
}
?>
</select>
</form>
</div>
<?php

print '</div><div style="clear:both;"></div>';



// Get the items of the evaluation.
$items = $evaluationstructure->get_items(true);
$itemsCounted = 0;
foreach ($items as $item) 
{    // export only rateable items
	if ( !in_array($item->typ, array("numeric","multichoice","multichoicerated") ) )
	{	continue; }
	$itemsCounted++;
}
$itemsText = safeCount( $items ) - $itemsCounted;

// Show the summary.
echo "<b>".get_string('completed_evaluations',"evaluation")."</b>: ".$completed_responses ."<br>\n"; 
echo "<b>".get_string("questions","evaluation")."</b>: " .safeCount($items). " ($itemsCounted numerisch ausgewertete Fragen)<br>\n";

// show div evCharts after pageload
echo "\n".'<div  class="d-print-block" id="evCharts" style="display:none;">';   // no display before onload



// if (preg_match('/rated$/i', $item->typ))
if ($courseitemfilter > 0) {
    $sumvalue = 'SUM(' . $DB->sql_cast_char2real('value', true) . ')';
    $sql = "SELECT fv.courseid, c.shortname, $sumvalue AS sumvalue, COUNT(value) as countvalue
            FROM {evaluation_value} fv, {course} c, {evaluation_item} fi
            WHERE fv.courseid = c.id AND fi.id = fv.item AND fi.typ = ? AND fv.item = ?
            GROUP BY courseid, shortname
            ORDER BY sumvalue desc";

    if ($courses = $DB->get_records_sql($sql, array($courseitemfiltertyp, $courseitemfilter))) {
        $item = $DB->get_record('evaluation_item', array('id'=>$courseitemfilter));
        echo '<h4>'.$item->name.'</h4>';
        echo '<div class="clearfix"></div>';
        echo '<table>';
        echo '<tr><th>Course</th><th>Average</th></tr>';

        foreach ($courses as $c) {
            $coursecontext = context_course::instance($c->courseid);
            $shortname = format_string($c->shortname, true, array('context' => $coursecontext));

            echo '<tr>';
            echo '<td>'.$shortname.'</td>';
            echo '<td align="right">';
            echo format_float(($c->sumvalue / $c->countvalue), 2);
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>'.get_string('noresults').'</p>';
    }
    echo '<p><a href="analysis_course.php?id=' . $id . '&courseid='.$courseid.'">';
    echo get_string('back');
    echo '</a></p>';
} else 
{


	//  get all results to compare if single course selected
	if ( false AND is_siteadmin() AND $courseid >0 )
	{	//mod_evaluation_structure($evaluation, $PAGE->cm, $courseid, null, 0, $teacherid, $course_of_studies);
		$evaluationstructure2 = new mod_evaluation_structure($evaluation, $PAGE->cm, false);
		//$items2 = $evaluationstructure2->get_items(true);
		//print print_r($items);
		//print "<br><hr><br>";
		//print print_r($items2);
	}
	// new feature to compare results -- not working, need print - function to compare average values
    $compare = false; // is_siteadmin() AND ( $courseid OR $course_of_studiesID OR $teacherid);
	//echo "<br>Studiengang: $course_of_studies<br>";	

	$byTeacher = ( ( ($Teacher AND $teacherid == $USER->id) OR defined('EVALUATION_OWNER') ) AND  $completed_responses >= $minresultsText );
	echo "<br>\n"; 
	// Print the items in an analysed form.
    foreach ($items as $key => $item) 
	{
		// filter data display by privileges
		// before: ( !defined('EVALUATION_OWNER') ?true :$cosPrivileged )
		if ( !is_siteadmin() AND defined( "SiteEvaluation") ) 
		{	if ( ( !$byTeacher AND !in_array($item->typ, array("numeric","multichoice","multichoicerated"))) OR 
				($courseid AND 
				(stripos($item->name,"geschlecht")!== false OR stripos($item->name,"semester")!== false OR stripos($item->name,"studiengang")!== false ) )
			   )
			{	continue; }
		}
		
		// show text replies only
		if ( $TextOnly AND in_array($item->typ, array("numeric","multichoice","multichoicerated")) )
		{	continue; }
		
		if ( ( !empty($course_of_studiesID) OR $courseid) AND  stripos($item->name,get_string("course_of_studies","evaluation")) !== false )
		{	continue; }
		echo '<table style="width:100%;">';
		//echo nl2br(var_export($key,true)).nl2br(var_export($item,true)); 
        $itemobj = evaluation_get_item_class($item->typ);
        $printnr = ($evaluation->autonumbering && $item->itemnr) ? ($item->itemnr . '.') : '';
		echo "<tr><td>\n";
		if ( in_array($item->typ, array("multichoice","multichoicerated")) )
        {	$itemobj->print_analysed($item, $printnr, $mygroupid, $evaluationstructure->get_courseid(), 
				$evaluationstructure->get_teacherid(), $evaluationstructure->get_course_of_studies(), $Chart, $compare  ); 
		}
		else
		{	$itemobj->print_analysed($item, $printnr, $mygroupid, $evaluationstructure->get_courseid(), 
				$evaluationstructure->get_teacherid(), $evaluationstructure->get_course_of_studies() ); 
		}
		if ( false AND is_siteadmin() AND $courseid )
		{	if ( $course->id == SITEID AND defined( "SiteEvaluation") AND 
				( defined('EVALUATION_OWNER') || in_array($item->typ, array("multichoice","multichoicerated")) ) )
			{	
				$itemobj->print_analysed($item, $printnr, $mygroupid, $evaluationstructure2->get_courseid(), 
					$evaluationstructure2->get_teacherid(), $evaluationstructure2->get_course_of_studies(), $Chart ); 
			}
		}
		echo '</td></tr>';		
        if (preg_match('/rated$/i', $item->typ)) {
            $url = new moodle_url('/mod/evaluation/analysis_course.php', array('id' => $id,
                'courseitemfilter' => $item->id, 'courseitemfiltertyp' => $item->typ));
            $anker = html_writer::link($url, get_string('sort_by_course', 'evaluation'));

			echo '<tr><td colspan="2">'.$anker.'</td></tr>';
        }
		echo '</table>';
    }

}

// display graphs once page is loaded
print "\n</div> <!-- end evCharts-->\n"; 

//echo nl2br(var_export($GLOBALS['CFG'],true));
//echo "<br>evaluationstructure: ".nl2br(var_export($evaluationstructure,true));
//echo "<br>teacherid: ".$evaluationstructure->get_teacherid();
//echo " - course_of_studies: ".$evaluationstructure->get_course_of_studies();



// js code for closing spinner
evaluation_spinnerJS();	
// logging
evaluation_trigger_module_analysed( $evaluation, $cm, $courseid );


echo $OUTPUT->footer();

// load js to handle printing
$printWidth = "126vw";
require_once("print.js.php");
