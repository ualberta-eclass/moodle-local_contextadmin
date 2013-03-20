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
 * This plugin can help upgrade site with a large number of question attempts
 * from Moodle 2.0 to 2.1.
 *
 * This screen is the main entry-point to the plugin, it gives the admin a list
 * of options available to them.
 *
 * @package    local
 * @subpackage contextadmin
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

require_login();

$contextid = required_param("contextid", PARAM_INT);

// Get the context that was passed (verify it is course category or system context).
if ($contextid) {
    $context = get_context_instance_by_id($contextid, MUST_EXIST);
} else {
    $context = get_context_instance(CONTEXT_SYSTEM);
}

if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
    print_error('invalidcontext');
}

// Set the context  & setup the page.
$PAGE->set_url("/local/contextadmin/index.php", array("contextid" => $contextid));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$site = get_site();
$PAGE->set_title("$site->shortname");
$PAGE->set_heading($site->fullname);
$PAGE->set_pagelayout('coursecategory');
$renderer = $PAGE->get_renderer('local_contextadmin');

$actions = array();

// Need to load list of categories that this user has access to.
if ($context->contextlevel == CONTEXT_SYSTEM) {
    include_once($CFG->dirroot . '/local/contextadmin/locallib.php');
    include_once($CFG->dirroot . '/course/lib.php');

    $test = '';
    print_whole_category_manager_list(null, null, null, 0, false, '', false, $test);

    $actions[0] = $test;
    echo $renderer->index_page('System Context', $actions);
} else if ($context->contextlevel = CONTEXT_COURSECAT) {
    // Load category level settings/links.
    // Need to check for permission here.
    //   - 'moodle/site:config'.
    //   - 'mod/contextadmin:manage', $context, where context is a valid category context for that user.
    echo $renderer->index_page('Category Context', $actions);
}

