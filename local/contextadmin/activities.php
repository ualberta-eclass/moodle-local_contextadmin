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

// Allows the admin to manage activity modules.

global $CFG;
global $PAGE;
global $OUTPUT;
global $DB;
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/contextadmin/locallib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');


// Defines.
define('MODULE_TABLE', 'module_administration_table');

require_login();

$contextid = required_param("contextid", PARAM_INT);
$catid     = required_param("catid", PARAM_INT);

// Get the context that was passed (verify it is course category or system context).
$context = get_context_instance_by_id($contextid, MUST_EXIST);


// TODO: Exit cleanly...fix this later.
// If we do not belong here.....
// Setup Page (not admin setup).
$PAGE->set_url("/local/contextadmin/activities.php", array("contextid" => $contextid, "catid" => $catid));
$PAGE->set_category_by_id($catid);

$param_visible     = optional_param('visible', null, PARAM_BOOL);
$param_clear       = optional_param('clear', null, PARAM_BOOL);
$param_delete      = optional_param('delete', null, PARAM_BOOL);
$param_confirm     = optional_param('confirm', null, PARAM_BOOL);
$param_override    = optional_param('override', null, PARAM_BOOL);
$param_locked      = optional_param('locked', null, PARAM_BOOL);
$param_module_name = optional_param('module_name', '', PARAM_SAFEDIR);


// Print headings.
$stractivities       = get_string("activities");
$strhide             = get_string("hide");
$strshow             = get_string("show");
$strclear_heading    = get_string('clear_title', 'local_contextadmin');
$stroverride_heading = get_string('override_title', 'local_contextadmin');
$strlocked_heading   = get_string('locked_title', 'local_contextadmin');
$strsettings         = get_string("settings");
$stractivities       = get_string("activities");
$stractivitymodule   = get_string("activitymodule");
$strshowmodulecourse = get_string('showmodulecourse');

// If data submitted, then process and store.
if ((!empty($param_module_name)) and confirm_sesskey() && has_capability('mod/contextadmin:changevisibilty', $context)) {

    $module = $DB->get_record("modules", array("name" => $param_module_name));

    if (!$module) {
        print_error('moduledoesnotexist', 'error');
    } else {
        if (!is_plugin_locked($catid, $param_module_name, 'modules')) {
            if ($param_visible !== null) {

                set_context_module_settings($catid, $param_module_name, array('visible' => $param_visible, 'search' => ''));
            }
            if ($param_override !== null) {
                set_context_module_settings($catid, $param_module_name, array('override' => $param_override, 'search' => ''));
            }
            if ($param_locked !== null) {
                set_context_module_settings($catid, $param_module_name, array('locked' => $param_locked, 'search' => ''));
            }
            if ($param_clear !== null) {
                remove_category_module_values($catid, $param_module_name);
            }
        } else {
            print_error('modulelocked', 'local_contextadmin');
        }
    }
}

// Category is our primary source of context.  This is important.
$category = $PAGE->category;
$site     = get_site();
$PAGE->set_title("$site->shortname: $category->name");
$PAGE->set_heading($site->fullname);
$PAGE->set_pagelayout('coursecategory');
echo $OUTPUT->header();
echo $OUTPUT->heading($category->name . ': ' . $stractivities);

// Get and sort the existing modules.

// Modules are retrieved from main mdl_modules table and NOT mdl_cat_modules since at most.

// The mdl_cat_modules is a subset of modules that exist in mdl_modules.
if (!$modules = $DB->get_records('modules', array(), 'name ASC')) {
    print_error('moduledoesnotexist', 'error');
}

// Print the table of all modules.

// Construct the flexible table ready to display.

$table = new flexible_table(MODULE_TABLE);
// User can edit settings for modules within this category.
if (has_capability('mod/contextadmin:editowncatsettings', $context)) {
    $table->define_columns(array('name', 'hideshow', 'override', 'lock', 'clear', 'settings'));
    $table->define_headers(array($stractivitymodule, "$strhide/$strshow", $stroverride_heading, $strlocked_heading,
                               $strclear_heading, $strsettings));
} else if (has_capability('mod/contextadmin:changevisibilty', $context)
) { // User can not edit settings for modules but can hide/show.
    $table->define_columns(array('name', 'hideshow', 'clear', 'override', 'lock', 'clear'));
    $table->define_headers(array($stractivitymodule, "$strhide/$strshow", $stroverride_heading, $strlocked_heading,
                               $strclear_heading));
} else {
    $table->define_columns(array('name'));
    $table->define_columns(array($stractivitymodule));
}


$table->define_baseurl($CFG->wwwroot . '/' . $CFG->admin . '/modules.php');
$table->set_attribute('id', 'modules');
$table->set_attribute('class', 'generaltable');
$table->setup();


foreach ($modules as $module) {
    $visible_td  = '';
    $clear_td    = '';
    $override_td = '';
    $locked_td   = '';
    $settings_td = '';


    // TODO: make a more efficient way to grab initial category modules instead of site level then overriding.
    $module = get_context_module_settings($catid, $module->name);
    if (!file_exists("$CFG->dirroot/mod/$module->name/lib.php")) {
        $strmodulename = '<span class="notifyproblem">' . $module->name . ' (' . get_string('missingfromdisk') . ')</span>';
        $missing       = true;
    } else {
        // Took out hspace="\10\", because it does not validate. don't know what to replace with.
        $icon          = "<img src=\"" . $OUTPUT->pix_url('icon', $module->name) . "\" class=\"icon\" alt=\"\" />";
        $strmodulename = $icon . ' ' . get_string('modulename', $module->name);
        $missing       = false;
    }

    if (file_exists("$CFG->dirroot/local/contextadmin/mod/$module->name/cat_settings.php") &&
        has_capability('mod/contextadmin:editowncatsettings',
                       $context)
    ) {
        $settings_td =
            "<a href=\"cat_settings.php?section=modsetting$module->name&name=$module->name&contextid=$contextid\">$strsettings</a>";
    } else {
        $settings_td = "";
    }


    $class = '';
    // If we can hide/show then create the icons/links.
    if (has_capability('mod/contextadmin:changevisibilty', $context)) {
        $self_path = "activities.php?contextid=$contextid&catid=$catid";

        if ($missing) {
            $visible_td = '';
        } else if ($module->visible) {
            $visible_td = create_form($OUTPUT, $module->name . "_visible_form", $self_path, $strhide, 'hide',
                                      array('module_name' => $module->name, 'visible' => 'false', 'sesskey' => sesskey()));
        } else {
            $visible_td = create_form($OUTPUT, $module->name . "_visible_form", $self_path, $strhide, 'show',
                                      array('module_name' => $module->name, 'visible' => 'true', 'sesskey' => sesskey()));
            $class      = ' class="dimmed_text"';
        }

        if (category_module_exists($catid, $module->name)) {
            $clear_td = create_form($OUTPUT, $module->name . "_clear_form", $self_path, $strhide, 'cross_red_big',
                                    array('module_name' => $module->name, 'clear' => 'true', 'sesskey' => sesskey()));

            if ($module->override) {
                $override_td = create_form($OUTPUT, $module->name . "_override_form", $self_path, $strhide, 'completion-manual-y',
                                           array('module_name' => $module->name, 'override' => 'false', 'sesskey' => sesskey()));
                $class       = '';
            } else {
                $override_td = create_form($OUTPUT, $module->name . "_override_form", $self_path, $strhide, 'completion-manual-n',
                                           array('module_name' => $module->name, 'override' => 'true', 'sesskey' => sesskey()));
            }

            if ($module->locked) {
                $locked_td = create_form($OUTPUT, $module->name . "_locked_form", $self_path, $strhide, 'completion-manual-y',
                                         array('module_name' => $module->name, 'locked' => 'false', 'sesskey' => sesskey()));
                $class     = '';
            } else {
                $locked_td = create_form($OUTPUT, $module->name . "_locked_form", $self_path, $strhide, 'completion-manual-n',
                                         array('module_name' => $module->name, 'locked' => 'true', 'sesskey' => sesskey()));
            }

        }

        if ($module->name == "forum") {
            $visible_td  = '';
            $clear_td    = '';
            $override_td = '';
            $locked_td   = '';
            $settings_td = '';
        }
    }

    $tabledata   = array('<span' . $class . '>' . $strmodulename . '</span>');
    $tabledata[] = $visible_td;
    $tabledata[] = $override_td;
    $tabledata[] = $locked_td;
    $tabledata[] = $clear_td;
    $tabledata[] = $settings_td;

    // User can not edit settings for modules but can hide/show.
    $table->add_data($tabledata);
}

$table->print_html();

echo $OUTPUT->footer();
