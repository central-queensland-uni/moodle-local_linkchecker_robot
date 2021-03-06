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
 * Unit tests for link crawler robot
 *
 * @package    local_linkchecker_robot
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden');

/**
 * Unit test for scheduled task robot_cleanup.
 *
 * It sets Retention Period as 1 week and then creates sample records in
 * table {linkchecker_url} which are deliberately older then retention period.
 * Then it executes the robot_cleanup scheduled task and verifies that old records have been deleted.
 *
 * @package    local_linkchecker_robot
 * @copyright  2016 Suan Kan <suankan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_linkchecker_robot_robot_cleanup_testcase extends advanced_testcase {

    /**
     * Prepare the config options for plugin which are used for robot_cleanup task logic
     *
     * @throws coding_exception
     */
    protected function setUp() {
        global $DB;

        $this->resetAfterTest(true);
        $this->robot = new \local_linkchecker_robot\robot\crawler();
        set_config('crawlend', strtotime("16-05-2016 14:51:00"), 'local_linkchecker_robot');
        set_config('retentionperiod', 600, 'local_linkchecker_robot');

        // Add 3 test records to table {linkchecker_url}: 2 old ones and 1 item not older than configured retention period.
        $dataobjects = array(
            array(
                'url' => 'http://cqu.ubox001.com/course/index.php',
                'external' => 0,
                'createdate' => strtotime("16-05-2016 10:00:00"),
                'lastcrawled' => strtotime("16-05-2016 11:20:00"),
                'needscrawl' => strtotime("17-05-2017 10:00:00"),
                'httpcode' => 200,
                'mimetype' => 'text/html',
                'title' => 'CQU: All courses',
                'downloadduration' => 0.23,
                'filesize' => 44003,
                'redirect' => null,
                'courseid' => 1,
                'contextid' => 1,
                'cmid' => null,
                'ignoreduserid' => null,
                'ignoredtime' => null,
                'httpmsg' => 'OK'
            ),
            array(
                'url' => 'http://moodle.org/',
                'external' => 1,
                'createdate' => strtotime("15-05-2016 10:00:00"),
                'lastcrawled' => strtotime("16-05-2016 14:49:59"),
                'needscrawl' => strtotime("17-05-2017 10:00:00"),
                'httpcode' => 200,
                'mimetype' => 'text/html',
                'title' => 'Moodle - Open-source learning platform | Moodle.org',
                'downloadduration' => 1.53,
                'filesize' => 56887,
                'redirect' => 'https://moodle.org/',
                'courseid' => null,
                'contextid' => null,
                'cmid' => null,
                'ignoreduserid' => null,
                'ignoredtime' => null,
                'httpmsg' => 'Moved Permanently'
            ),
            array(
                'url' => 'http://cqu.ubox001.com/course/index.php?categoryid=1',
                'external' => 0,
                'createdate' => strtotime("16-05-2016 10:00:00"),
                'lastcrawled' => strtotime("16-05-2016 14:50:01"),
                'needscrawl' => strtotime("17-05-2017 10:00:00"),
                'httpcode' => 200,
                'mimetype' => 'text/html',
                'title' => 'CQU: Miscellaneous',
                'downloadduration' => 0.24,
                'filesize' => 45301,
                'redirect' => null,
                'courseid' => 1,
                'contextid' => 3,
                'cmid' => null,
                'ignoreduserid' => null,
                'ignoredtime' => null,
                'httpmsg' => 'OK'
            )
        );

        try {
            $DB->insert_records('linkchecker_url', $dataobjects);
        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
    }

    /**
     * Read plugin config params.
     * Execute robot_cleanup scheduled task.
     * Check if only 1 record (out of 3 configured above) is left in table {linkchecker_url}.
     */
    public function test_robot_cleanup() {
        global $DB;

        // Expect the task to cleanup 2 records and leave 1.
        $cleanuptask = new \local_linkchecker_robot\task\robot_cleanup();
        // Simulate execution of robot_cleanup task at "16-05-2016 15:00:00" by passing this time as parameter.
        $cleanuptask->execute(strtotime("16-05-2016 15:00:00"));

        $count = $DB->count_records_select('linkchecker_url', '');
        $this->assertEquals(1, $count);
    }
}
