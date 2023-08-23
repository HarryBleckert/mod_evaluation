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
 * prints the overview of all evaluations included into the current course
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_evaluation
 */

require_once("../../config.php");
require_once("lib.php");

$id = optional_param('id', SITEID, PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}

$context = context_course::instance($course->id);

require_login($course);
$PAGE->set_pagelayout('incourse');

if (!is_siteadmin()) {    // Trigger instances list viewed event.
    $event = \mod_evaluation\event\course_module_instance_list_viewed::create(array('context' => $context));
    $event->add_record_snapshot('course', $course);
    $event->trigger();
}

/// Print the page header
$strevaluations = get_string("modulenameplural", "evaluation");
$strevaluation = get_string("modulename", "evaluation");

$url = new moodle_url('/mod/evaluation/', array('id' => $id));
$PAGE->set_url($url);
//$PAGE->navbar->add($strevaluations,$url);
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('modulename', 'evaluation')); //.' '.get_string('activities'));
echo $OUTPUT->header();
// hide settings menu in Moodle 4
evHideSettings();

$icon = '<img src="pix/icon120.png" height="30" alt="' . $strevaluation . '">';
echo $OUTPUT->heading($icon . "&nbsp;" . $strevaluations);

/// Get all the appropriate data

if (!$evaluations = get_all_instances_in_course("evaluation", $course)) {
    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    notice(get_string('thereareno', 'moodle', $strevaluations), $url);
    die;
}

$usesections = course_format_uses_sections($course->format);

/// Print the list of instances (your module will probably extend this)

$timenow = time();
$strname = get_string("name");
$strresponses = get_string('responses', 'evaluation');

$table = new html_table();

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_' . $course->format);
    if (1 or has_capability('mod/evaluation:viewreports', $context)) {
        $table->head = array($strsectionname, $strname, get_string('evaluation_period', 'evaluation'),
                $strresponses, '<span style="font-size:20px;"> &#248;</span> ' . get_string("day"));
        $table->align = array("center", "left", "center", 'right', 'right');
    } else {
        $table->head = array($strsectionname, $strname);
        $table->align = array("center", "left", 'right');
    }
} else {
    if (1 or has_capability('mod/evaluation:viewreports', $context)) {
        $table->head = array($strname, get_string('evaluation_period', 'evaluation'),
                $strresponses, '<span style="font-size:20px;"> &#248;</span> ' . get_string("day"));
        $table->align = array("left", "center", "right", "right");
    } else {
        $table->head = array($strname, get_string('evaluation_period', 'evaluation'));
        $table->align = array("left", "left", "left");
    }
}

if (count($evaluations) > 1) {
    usort($evaluations, function($a, $b) {
        if ($a->timeopen == $b->timeopen) {
            return 0;
        }
        return ($a->timeopen < $b->timeopen) ? 1 : -1;
    });
}

// unset $_SESSION["EvaluationsName"] to reset stored evaluation data
unset($_SESSION["EvaluationsName"]);

foreach ($evaluations as $evaluation) {
    //get the responses of each evaluation

    // ignore Evaluations with word "test" inside
    if (!$evaluation->show_on_index) {
        continue;
    }

    $viewurl = new moodle_url('/mod/evaluation/view.php', array('id' => $evaluation->coursemodule));
    $evaluation_isPrivilegedUser = evaluation_isPrivilegedUser($evaluation);

    $dimmedclass = $evaluation->visible ? '' : 'class="dimmed"';
    $link = '<a ' . $dimmedclass . ' href="' . $viewurl->out() . '">' . $evaluation->name . '</a>';

    if ($usesections) {
        $tabledata = array(get_section_name($course, $evaluation->section), $link);
    } else {
        $tabledata = array($link);
    }
    $timeopen = $evaluation->timeopen ? date("d.m.Y", $evaluation->timeopen) : "";
    $timeclose = $evaluation->timeclose ? date("d.m.Y", $evaluation->timeclose) : "";
    $tabledata[] = $timeopen . ' - ' . $timeclose . " (" . total_evaluation_days($evaluation) . " " . get_string("days") . ")";

    // groups are ignored
    //print "<br>Evaluation: " . var_export($evaluation,true) . "<br>";
    //validate_evaluation_sessions( $evaluation );
    unset($_SESSION["teamteaching_courses"], $_SESSION["teamteaching_courseids"]);
    $cmid = get_evaluation_cmid_from_id($evaluation);
    list($tmp, $cm) = get_course_and_cm_from_cmid($cmid, 'evaluation');
    $evaluationstructure = new mod_evaluation_structure($evaluation, $cm);
    //$completed_evaluation_count = $evaluationstructure->count_completed_responses();
    $completed_responses = evaluation_countCourseEvaluations($evaluation);

    $days = total_evaluation_days($evaluation);
    $remaining = remaining_evaluation_days($evaluation);
    if ($remaining > 0) {
        $days = $days - $remaining;
    }
    $tabledata[] = '<span title="' . date("Y-m-d H:i") . '">' . evaluation_number_format($completed_responses) . '</span>';
    $tabledata[] = ($days ? evaluation_number_format($completed_responses / $days) : 0);

    $table->data[] = $tabledata;

}
echo "<br\n>";

echo html_writer::table($table);

/// Finish the page
echo $OUTPUT->footer();

