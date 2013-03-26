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

require_once('locallib.php');

/**
 * @param navigation_node $contextnode
 * @param $context context object
 * @return navigation_node contextnode passed with any modifications made. useful for chaining.
 */

function get_context_nav(navigation_node $contextnode, $context) {
    global $COURSE;
    // Default link for now, change for each node.
    $url = new moodle_url('/local/contextadmin/index.php', array('contextid' => '1'));
    // My Categories.
    $contextnode->add(get_string('mycat', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'mycat',
                      new pix_icon('i/settings', ''));

    // Add custom search page for category admins.
    $url = new moodle_url('/local/contextadmin/cat_search.php', array());
    // Category Search tool (search restricted to categories that the user has access to).
    $contextnode->add(get_string('search', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'search',
                      new pix_icon('i/settings', ''));

    if (has_capability('mod/contextadmin:changevisibilty', $context) or has_capability('mod/contextadmin:editowncatsettings',
                                                                                       $context)) {
        $catid = $COURSE->category;
        // There is a scenario where the context is at the course level and the parent category is a system context (not Category).
        // We need to catch it.
        if ($COURSE->category == 0 && $context->contextlevel != CONTEXT_COURSECAT) {
            // If we are at a system course (site course) we have no category context...so return FALSE.
            return false;
        } else {
            // Course variable has category as 0 but the context is CONTEXT_COURSECAT so we take instanceid.
            // Lower contexts can use $COURSE->category.
            if ($context->contextlevel == CONTEXT_COURSECAT) {
                $catid = $context->instanceid;
            } else if ($context->contextlevel > CONTEXT_COURSECAT) {
                $catid = $COURSE->category;
            } else {
                return false;
            }
        }

        // Todo Advanced Features.

        // Todo Courses.

        // Plugins Node.
        $pluginnode = $contextnode->add(get_string('plugins', 'local_contextadmin'), null, navigation_node::TYPE_SETTING, null,
                                        'plugins', new pix_icon('i/settings', ''));
        create_plugin_node($pluginnode, $context->id, $catid);
    }

    return $contextnode;
}

/**
 * Plugins
 *
 * @param navigation_node $pluginnode  plugin parent node for the children we need to create
 * @return navigation_node pluginnode passed with any modifications made. useful for chaining
 */
function create_plugin_node(navigation_node $pluginnode, $contextid, $catid) {
    $url = new moodle_url('/local/contextadmin/activities.php', array('contextid' => $contextid, 'catid' => $catid));

    // Todo Overview.

    // Activity modules.
    $url          = new moodle_url('/local/contextadmin/activities.php', array('contextid' => $contextid));
    $activitynode = $pluginnode->add(get_string('activity', 'local_contextadmin'), null, navigation_node::TYPE_SETTING, null,
                                     'activity', new pix_icon('i/settings', ''));
    create_activity_node($activitynode, $contextid, $catid);

    // Blocks.
    $blocksnode = $pluginnode->add(get_string('blocks', 'local_contextadmin'), null, navigation_node::TYPE_SETTING, null, 'blocks',
                                   new pix_icon('i/settings', ''));
    create_blocks_node($blocksnode, $contextid, $catid);

    // Todo Repositories.
    return $pluginnode;
}

/**
 * Acitivy Modules
 *
 * @param navigation_node $activitynode  acitivty parent node for the children we need to create
 * @return navigation_node activitynode passed with any modifications made. useful for chaining
 */
function create_activity_node(navigation_node $activitynode, $contextid, $catid) {
    $url = new moodle_url('/local/contextadmin/activities.php', array('contextid' => $contextid, 'catid' => $catid));

    // Overview.
    $activitynode->add(get_string('manage', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'manage',
                       new pix_icon('i/settings', ''));
    return $activitynode;
}

/**
 * Courses
 *
 * @param navigation_node $coursesnode  courses parent node for the children we need to create
 * @return navigation_node coursesnode passed with any modifications made. useful for chaining
 */
function create_courses_node(navigation_node $coursesnode) {

    return $coursesnode;
}

/**
 * Advanced Features
 *
 * @param navigation_node $advnode  advanced features parent node for the children we need to create
 * @return navigation_node advnode passed with any modifications made. useful for chaining
 */
function create_advanced_node(navigation_node $advnode) {

    return $advnode;
}

/**
 * Blocks
 *
 * @param navigation_node $blocksnode  blocks parent node for the children we need to create
 * @return navigation_node blocksnode passed with any modifications made. useful for chaining
 */
function create_blocks_node(navigation_node $blocksnode, $contextid, $catid) {

    $url = new moodle_url('/local/contextadmin/blocks.php', array('contextid' => $contextid, 'catid' => $catid));

    // Manage Blocks.
    $blocksnode->add(get_string('manageblocks', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'manageblocks',
                     new pix_icon('i/settings', ''));
    return $blocksnode;
}

/**
 * Repositories
 *
 * @param navigation_node $reposnode  repositories parent node for the children we need to create
 * @return navigation_node reposnode passed with any modifications made. useful for chaining
 */
function create_repositories_node(navigation_node $reposnode) {

    $url = new moodle_url('/local/contextadmin/repository.php');

    // Manage Repositories.
    $reposnode->add(get_string('managerepos', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'managerepos',
                    new pix_icon('i/settings', ''));
    return $reposnode;
}
