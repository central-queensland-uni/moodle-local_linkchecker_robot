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
 * local_linkchecker_robot
 *
 * @package    local_linkchecker_robot
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Perform one cron 'tick' of crawl processing
 *
 * Has limits of both how many urls to crawl
 * and a soft time limit on total crawl time.
 */
function local_linkchecker_robot_crawl($verbose = false) {

    global $CFG, $DB;

    $robot = new \local_linkchecker_robot\robot\crawler();
    $config = $robot::get_config();
    $crawlstart = $config->crawlstart;
    $crawlend   = $config->crawlend;

    // Check if valid, otherwise bail quickly.

    // If we need to start a new crawl, push the seed url into the crawl queue.
    if (!$crawlstart || $crawlstart <= $crawlend) {

        $start = time();
        set_config('crawlstart', $start, 'local_linkchecker_robot');
        $robot->mark_for_crawl($CFG->wwwroot.'/', $config->seedurl);

        // Create a new history record.
        $history = new stdClass();
        $history->startcrawl = $start;
        $history->urls = 0;
        $history->links = 0;
        $history->broken = 0;
        $history->oversize = 0;
        $history->cronticks = 0;
        $history->id = $DB->insert_record('linkchecker_history', $history);
    } else {
        $history = $DB->get_record('linkchecker_history', array('startcrawl' => $crawlstart));
    }

    // While we are not exceeding the maxcron time, and the queue is not empty
    // find the next url in the queue and crawl it.

    // If the queue is empty then mark the crawl as ended.

    $cronstart = time();
    $cronstop = $cronstart + $config->maxcrontime;

    $hasmore = true;
    $hastime = true;
    while ($hasmore && $hastime) {

        $hasmore = $robot->process_queue($verbose);
        $hastime = time() < $cronstop;
        set_config('crawltick', time(), 'local_linkchecker_robot');
    }

    if ($hastime) {
        // Time left over, which means the queue is empty!
        // Mark the crawl as ended.
        $history->endcrawl = time();
        set_config('crawlend', time(), 'local_linkchecker_robot');
    }
    $history->urls = $robot->get_processed();
    $history->links = $robot->get_num_links();
    $history->broken = $robot->get_num_broken_urls();
    $history->oversize = $robot->get_num_oversize();
    $history->cronticks++;

    $DB->update_record('linkchecker_history', $history);
}

/**
 * Get summary stats about a url
 *
 * @param integer $courseid a course aid
 *
 * @return an array of summary data
 */
function local_linkchecker_robot_summary($courseid) {

    global $DB;

    $result = array();
    $result['large']  = array();
    $result['nearby'] = array();

    // Breakdown counts of status codes by 200, 300, 400, 500.
    $result['broken']   = $DB->get_records_sql("
         SELECT substr(b.httpcode,0,2) code,
                count(substr(b.httpcode,0,2))
           FROM {linkchecker_url}   b
      LEFT JOIN {linkchecker_edge}  l ON l.b  = b.id
      LEFT JOIN {linkchecker_url}   a ON l.a  = a.id
      LEFT JOIN {course}            c ON c.id = a.courseid
          WHERE a.courseid = :course
       GROUP BY substr(b.httpcode,0,2)
    ", array('course' => $courseid) );

    $e = (object) array('count' => 0);
    if (!array_key_exists('0', $result['broken'])) {
        $result['broken']['0'] = $e;
    }
    if (!array_key_exists('2', $result['broken'])) {
        $result['broken']['2'] = $e;
    }
    if (!array_key_exists('3', $result['broken'])) {
        $result['broken']['3'] = $e;
    }
    if (!array_key_exists('4', $result['broken'])) {
        $result['broken']['4'] = $e;
    }
    if (!array_key_exists('5', $result['broken'])) {
        $result['broken']['5'] = $e;
    }

    return $result;
}


