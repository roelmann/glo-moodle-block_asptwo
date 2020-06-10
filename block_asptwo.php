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
 * Main code for My mentees block.
 *
 * @package   block_asptwo
 * @copyright  2012 Nathan Robbins (https://github.com/nrobbins)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/local/extdb/classes/task/extdb.php');

class block_asptwo extends block_base {

    public function init() {
        $this->title = get_string('configtitle', 'block_asptwo');
    }

    public function has_config() {
        return false;
    }

    public function applicable_formats() {
        return array('all' => true);
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function hide_header() {
        return true;
    }

    public function get_content() {
        global $CFG, $USER, $DB, $OUTPUT, $COURSE, $PAGE;
        $pageurl = $PAGE->url;
        if (strpos($pageurl, 'course/modedit') == 0) {
            return false;
        }

        // Set up scripts to add to body classes.
        $context = context_course::instance($COURSE->id);
        $aspeditor = has_capability('block/asptwo:aspassignmentediting', $context);
        $role = "other";
        if ($aspeditor) {
            $role = "admin";
        }
        $PAGE->requires->js_call_amd('block_asptwo/asptwo', 'init', array($role));
        ?>
        <script>
        function pasteidnumber(y) {
                document.getElementById("id_cmidnumber").value = y;
        }
        </script>
        <?php

        $this->content = new stdClass;
        $this->content->text = '';

        $externaldb = new \local_extdb\extdb();
        $name = $externaldb->get_name();

        // Language files pulled from theme so only need to be maintained in one place.
        // Theme is plugin dependency in version.php.
        $externaldbtype = $externaldb->get_config('dbtype');
        $externaldbhost = $externaldb->get_config('dbhost');
        $externaldbname = $externaldb->get_config('dbname');
        $externaldbencoding = $externaldb->get_config('dbencoding');
        $externaldbsetupsql = $externaldb->get_config('dbsetupsql');
        $externaldbsybasequoting = $externaldb->get_config('dbsybasequoting');
        $externaldbdebugdb = $externaldb->get_config('dbdebugdb');
        $externaldbuser = $externaldb->get_config('dbuser');
        $externaldbpassword = $externaldb->get_config('dbpass');
        $sourcetable = get_string('sourcetable', 'block_asptwo');

        // Check connection and label Db/Table in cron output for debugging if required.
        if (!$externaldbtype) {
            echo 'Database not defined.<br>';
            return 0;
        }
        if (!$sourcetable) {
            echo 'Table not defined.<br>';
            return 0;
        }

        // Report connection error if occurs.
        if (!$extdb = $externaldb->db_init(
            $externaldbtype,
            $externaldbhost,
            $externaldbuser,
            $externaldbpassword,
            $externaldbname)) {
            echo 'Error while communicating with external database <br>';
            return 1;
        }

        // Get external table contents.
        $course = $DB->get_record('course', array('id' => $COURSE->id));
        $assessments = array();
        if ($course->idnumber) {
            $sql = 'SELECT
                        *
                    FROM ' . $sourcetable . '
                    WHERE mav_idnumber LIKE "%' . $course->idnumber . '%"
                    AND assessment_idcode NOT LIKE "%-R"';
            if ($rs = $extdb->Execute($sql)) {
                if (!$rs->EOF) {
                    while ($assess = $rs->FetchRow()) {
                        $assess = array_change_key_case($assess, CASE_LOWER);
                        $assessments[] = $assess;
                    }
                }
                $rs->Close();
            } else {
                // Report error if required.
                $extdb->Close();
                echo 'Error reading data from the external course table<br>';
                return 4;
            }
        }

        // Create block output.
        $output = '<h3>'.get_string('heading', 'block_asptwo').'</h3>';
        $output .= get_string('panelalert', 'block_asptwo');

        // If no assessments.
        if (count($assessments) == 0 ) {
            $output .= get_string('noassessments', 'block_asptwo');
        }

        // Note if ASP completed.
        $output .= '<div class="haslinkcode">';
        $output .= get_string('aspcompleted', 'block_asptwo');
        $output .= '</div>';

        // List assessments and loop through them.
        // Buttons are disabled if code already used.
        $output .= '<div class="assesslist">';
        foreach ($assessments as $a) {
            $link = "'".$a['assessment_idcode']."'";
            $relink = "'".$a['assessment_idcode']."-R'";
            $output .= '<div class="assess small">';
            $output .= '<h5>'.$a['assessment_name'].'</h5>';
            $output .= '<p><strong>Number:</strong> '.$a['assessment_number'].
                ' : <strong>Weighting:</strong>'.$a['assessment_weight'].'%<br />';
            $output .= '<strong>Type:</strong> '.$a['assessment_type'].'<br />';
            $output .= '<strong>Mark Scheme:</strong> '.$a['assessment_markscheme_code'].
                ': '.$a['assessment_markscheme_name'].'</p>';
            $output .= '<div class="aspcodebuttons">';
            $output .= '<strong>Assessment Link Code</strong>';
            if ($DB->get_record('course_modules', array('idnumber' => $a['assessment_idcode']))) {
                $output .= '<button class="aspbtn btn btn-primary disabled">' . get_string('inuse', 'block_asptwo') . '</button>';
            } else {
                $output .= '<button class="aspbtn btn btn-primary" onclick="pasteidnumber('.$link.')">'.$link.'</button>';
            }
            $output .= '<br style="clear:both">';
            $output .= '<strong>Re-Assessment Link Code</strong>';
            if ($DB->get_record('course_modules', array('idnumber' => $a['assessment_idcode'].'-R'))) {
                $output .= '<button class="aspbtn btn btn-warning disabled">' . get_string('inuse', 'block_asptwo') . '</button>';
            } else {
                $output .= '<button class="aspbtn btn btn-warning" onclick="pasteidnumber('.$relink.')">'.$relink.'</button>';
            }
            $output .= '</div>';
            $output .= '</div>';
            $output .= '<p><br></p>';
        }
        $output .= '</div>';

        // Additional button for aspeditors to clear code.
        if ($aspeditor) {
            $clearcode = "''";
            $output .= '<button class="aspbtn btn btn-danger" onclick="pasteidnumber(' .
                $clearcode . ')">' . get_string('clear', 'block_asptwo') . '</button>';
        }

        $this->content->text = $output;

        $this->content->footer = '';

        return $this->content;
    }

}
