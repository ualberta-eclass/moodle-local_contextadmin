<?php
/**
 * Created by IntelliJ IDEA.
 * User: ggibeau
 * Date: 12-02-08
 * Time: 2:49 PM
 * To change this template use File | Settings | File Templates.
 */

require_once('locallib.php');

/**
 * @param navigation_node $contextnode
 * @param $context context object
 * @return navigation_node contextnode passed with any modifications made. useful for chaining.
 */

function get_context_nav(navigation_node $contextnode, $context) {
    global $COURSE;
    $url = new moodle_url('/local/contextadmin/index.php', array('contextid'=>'1')); // default link for now, change for each node
    // My Categories
    $contextnode->add(get_string('mycat', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'mycat', new pix_icon('i/settings', ''));

    // Add custom search page for category admins
    $url = new moodle_url('/local/contextadmin/cat_search.php', array());
    // Category Search tool (search restricted to categories that the user has access to)
    $contextnode->add(get_string('search', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'search', new pix_icon('i/settings', ''));

    if(has_capability('mod/contextadmin:changevisibilty', $context) ) {
        $catid = $COURSE->category;
        // There is a scenario where the context is at the course level and the parent category is a system context (not Category). We need to catch it.
        if($COURSE->category == 0 && $context->contextlevel != CONTEXT_COURSECAT) {
            // If we are at a system course (site course) we have no category context...so return FALSE
            return false;
        }
        else {
            //Course variable has category as 0 but the context is CONTEXT_COURSECAT so we take instanceid. (lower contexts can use $COURSE->category).
            if($context->contextlevel == CONTEXT_COURSECAT) {
                $catid = $context->instanceid;
            }
            else if($context->contextlevel > CONTEXT_COURSECAT) {
                $catid = $COURSE->category;
            }
            else {
                return false;
            }
        }

        // Advanced Features
        //$advnode = $contextnode->add(get_string('advanced', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'advanced', new pix_icon('i/settings', ''));
        //create_advanced_node($advnode);

        // Courses
        //$coursesnode = $contextnode->add(get_string('courses', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'courses', new pix_icon('i/settings', ''));
        //create_courses_node($coursesnode);

        // Plugins Node
        $pluginnode = $contextnode->add(get_string('plugins', 'local_contextadmin'), null, navigation_node::TYPE_SETTING, null, 'plugins', new pix_icon('i/settings', ''));
        create_plugin_node($pluginnode,$context->id, $catid);
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
    $url = new moodle_url('/local/contextadmin/activities.php', array('contextid'=>$contextid, 'catid'=>$catid));

    // Overview
    //$pluginnode->add(get_string('pluginsoverview', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'pluginsoverview', new pix_icon('i/settings', ''));

    // Activity modules
    $url = new moodle_url('/local/contextadmin/activities.php', array('contextid'=>$contextid));
    $activitynode = $pluginnode->add(get_string('activity', 'local_contextadmin'), null, navigation_node::TYPE_SETTING, null, 'activity', new pix_icon('i/settings', ''));
    create_activity_node($activitynode, $contextid, $catid);

    // Blocks
    $blocksnode = $pluginnode->add(get_string('blocks', 'local_contextadmin'), null, navigation_node::TYPE_SETTING, null, 'blocks', new pix_icon('i/settings', ''));
    create_blocks_node($blocksnode, $contextid, $catid);

    // Repositories
    //$reposnode = $pluginnode->add(get_string('repos', 'local_contextadmin'), null, navigation_node::TYPE_SETTING, null, 'repos', new pix_icon('i/settings', ''));
    //create_repositories_node($reposnode);
    return $pluginnode;
}

/**
 * Acitivy Modules
 *
 * @param navigation_node $activitynode  acitivty parent node for the children we need to create
 * @return navigation_node activitynode passed with any modifications made. useful for chaining
 */
function create_activity_node(navigation_node $activitynode, $contextid, $catid) {
    $url = new moodle_url('/local/contextadmin/activities.php', array('contextid'=>$contextid, 'catid'=>$catid));

    // Overview
    $activitynode->add(get_string('manage', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'manage', new pix_icon('i/settings', ''));
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

    $url = new moodle_url('/local/contextadmin/blocks.php', array('contextid'=>$contextid, 'catid'=>$catid));

    // Manage Blocks
    $blocksnode->add(get_string('manageblocks', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'manageblocks', new pix_icon('i/settings', ''));
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

    // Manage Repositories
    $reposnode->add(get_string('managerepos', 'local_contextadmin'), $url, navigation_node::TYPE_SETTING, null, 'managerepos', new pix_icon('i/settings', ''));
    return $reposnode;
}
