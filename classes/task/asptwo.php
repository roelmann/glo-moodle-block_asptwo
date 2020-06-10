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
 * A scheduled task for scripted database integrations.
 *
 * @package    local_extdb - template
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_asptwo\task;
use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * A scheduled task for Assessment Scrutiny logs.
 *
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class asptwo extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'block_asptwo');
    }

    /**
     * Run sync.
     */
    public function execute() {

        global $CFG, $DB;
        $events = array();
        $eventslog = array();

        $eventsql = "SELECT * FROM {logstore_standard_log}
                        WHERE
                            (
                                (eventname LIKE '%course_module_updated' AND action = 'updated')
                                OR
                                (eventname LIKE '%course_module_created' AND action = 'created')
                            )
                            AND
                                timecreated > (UNIX_TIMESTAMP(now())-60*60*1);";

        $events = $DB->get_records_sql($eventsql);

        echo $eventsql . '</br>';
        print_r ($events);

        $count = 0;
        foreach ($events as $k=>$v) {
            if(!$DB->record_exists('block_asptwo', array(
                'userid' => $events[$v->id]->userid,
                'contextid' => $events[$v->id]->contextinstanceid,
                'timemodified' => $events[$v->id]->timecreated))) {

                $cm = $DB->get_record('course_modules', array('id' => $events[$v->id]->contextinstanceid));
                if ($cm->module == 3) {
                    $mod = $DB->get_record('assign', array('id' => $cm->instance), $fields = 'id,course,name,introformat,alwaysshowdescription,nosubmissions,submissiondrafts,sendnotifications,sendlatenotifications,duedate,allowsubmissionsfromdate,grade,timemodified,requiresubmissionstatement,completionsubmit,cutoffdate,gradingduedate,teamsubmission,requireallteammemberssubmit,teamsubmissiongroupingid,blindmarking,hidegrader,revealidentities,attemptreopenmethod,maxattempts,markingworkflow,markingallocation,sendstudentnotifications,preventsubmissionnotingroup');
                    $modname = 'assign';
                    $duedate = date("Y-m-d H:m:s", $mod->duedate);
                    $concatsettings = implode('~#~',(array) $mod);

                } else if ($cm->module == 16) {
                    $mod = $DB->get_record('quiz', array('id' => $cm->instance));
                    $modname = 'quiz';
                    $duedate = date("Y-m-d H:m:s", $mod->timeopen);
                    $concatsettings = implode('~#~',(array) $mod);

                } else {
                    $modname = 'other';
                    $duedate = null;
                    $concatsettings = '';
                }


                $eventslog['userid'] = $events[$v->id]->userid;
                $eventslog['courseid'] = $events[$v->id]->courseid;
                $eventslog['module'] = $modname;
                $eventslog['contextid'] = $events[$v->id]->contextid;
                $eventslog['instanceid'] = $events[$v->id]->contextinstanceid;
                $eventslog['action'] = $events[$v->id]->action;
                $eventslog['timemodified'] = $events[$v->id]->timecreated;

                $eventslog['modidnumber'] = $cm->idnumber;
                $eventslog['duedate'] = $duedate;
                $eventslog['concatsettings'] = $concatsettings;

                echo 'eventslog<br>';
                print_r($eventslog);
if ($modname === 'assign') {
                $DB->insert_record('block_asptwo', $eventslog);
                echo 'Log written for ' . $eventslog['userid'] . ' at ' . $eventslog['timemodified'];
}
            }
        }


    }
}
