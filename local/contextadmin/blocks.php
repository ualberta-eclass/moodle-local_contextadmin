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

define('MODULE_TABLE', 'module_administration_table');

require_login();

$contextid = required_param("contextid", PARAM_INT);
$catid     = required_param("catid", PARAM_INT);

// Get the context that was passed (verify it is course category or system context).
$context = get_context_instance_by_id($contextid, MUST_EXIST);


// TODO: Exit cleanly...fix this later
// If we do not belong here.....

// Setup Page (not admin setup).
$PAGE->set_url("/local/contextadmin/blocks.php", array("contextid" => $contextid, "catid" => $catid));
$PAGE->set_category_by_id($catid);

$param_visible    = optional_param('visible', null, PARAM_BOOL);
$param_clear      = optional_param('clear', null, PARAM_BOOL);
$param_delete     = optional_param('delete', null, PARAM_BOOL);
$param_confirm    = optional_param('confirm', null, PARAM_BOOL);
$param_override   = optional_param('override', null, PARAM_BOOL);
$param_locked     = optional_param('locked', null, PARAM_BOOL);
$param_block_name = optional_param('block_name', '', PARAM_SAFEDIR);

// Print headings.
$strmanageblocks           = get_string('manageblocks');
$strhide                   = get_string('hide');
$strshow                   = get_string('show');
$strsettings               = get_string('settings');
$strname                   = get_string('name');
$strshowblockcourse        = get_string('showblockcourse');
$strclear_heading          = get_string('clear_title', 'local_contextadmin');
$stroverride_heading       = get_string('override_title', 'local_contextadmin');
$stroverride_value_heading = get_string('override_value_title', 'local_contextadmin');
$strlocked_heading         = get_string('locked_title', 'local_contextadmin');
$strsettings               = get_string("settings");
$strblocks                 = get_string("blocks");
$stractivitymodule         = get_string("activitymodule");
$strshowmodulecourse       = get_string('showmodulecourse');


// If data submitted, then process and store.
if ((!empty($param_block_name)) and confirm_sesskey() && has_capability('mod/contextadmin:changevisibilty', $context) &&
                                !is_plugin_locked($catid, $param_module_name,
                                'modules')) {

    if ($DB->get_record("block", array("name" => $param_block_name))) {
        if (!is_plugin_locked($catid, $param_block_name, 'block')) {
            if ($param_visible !== null) {

                set_context_block_settings($catid, $param_block_name, array('visible' => $param_visible, 'search' => ''));
            }
            if ($param_override !== null) {
                set_context_block_settings($catid, $param_block_name, array('override' => $param_override, 'search' => ''));
            }
            if ($param_locked !== null) {
                set_context_block_settings($catid, $param_block_name, array('locked' => $param_locked, 'search' => ''));
            }
            if ($param_clear !== null) {
                remove_category_block_values($catid, $param_block_name);
            }
        } else {
            print_error('blocklocked', 'local_contextadmin');
        }
    } else {
        print_error('noblocks', 'error');
    }
}

// Category is our primary source of context.  This is important.
// Setup the PAGE object.
$category = $PAGE->category;
$site     = get_site();
$PAGE->set_title("$site->shortname: $category->name");
$PAGE->set_heading($site->fullname);
$PAGE->set_pagelayout('coursecategory');
echo $OUTPUT->header();
echo $OUTPUT->heading($category->name . ': ' . $strmanageblocks);

// Main display starts here.
// Get and sort the existing blocks.
if (!$blocks = $DB->get_records('block', array(), 'name ASC')) {
    print_error('noblocks', 'error'); // Should never happen.
}

$incompatible = array();

// Print the table of all blocks.
$table = new flexible_table('admin-blocks-compatible');
// TODO: tying the capability to hide/show blocks to the same one for hide/show modules. Might need it's own in the future.
if (has_capability('mod/contextadmin:editowncatsettings', $context)) {
    $table->define_columns(array('name', 'override_value', 'hideshow', 'override', 'lock', 'clear', 'settings'));
    $table->define_headers(array($stractivitymodule, $stroverride_value_heading, "$strhide/$strshow", $stroverride_heading,
                               $strlocked_heading,
                               $strclear_heading, $strsettings));
} else if (has_capability('mod/contextadmin:changevisibilty', $context)) {
    // User can not edit settings for modules but can hide/show.
    $table->define_columns(array('name', 'override_value', 'hideshow', 'override', 'lock', 'clear'));
    $table->define_headers(array($stractivitymodule, $stroverride_value_heading, "$strhide/$strshow", $stroverride_heading,
                               $strlocked_heading,
                               $strclear_heading));
} else {
    $table->define_columns(array('name'));
    $table->define_columns(array($stractivitymodule));
}

$table->define_baseurl($CFG->wwwroot . '/' . $CFG->admin . '/blocks.php');
$table->set_attribute('class', 'compatibleblockstable blockstable generaltable');
$table->setup();
$tablerows = array();

foreach ($blocks as $blockid => $current_block) {
    $visible_td        = '';
    $clear_td          = '';
    $override_td       = '';
    $override_value_td = '';
    $locked_td         = '';
    $settings_td       = '';

    // Get current block settings (hidden/shown, etc..) for current category.
    $category_block = get_context_block_settings($catid, $current_block->name, false);
    $blockname      = $current_block->name;

    if (!file_exists("$CFG->dirroot/blocks/$blockname/block_$blockname.php")) {
        $strblockname = '<span class="notifyproblem">' . $blockname . ' (' . get_string('missingfromdisk') . ')</span>';
        $plugin       = new stdClass();
    } else {
        $strblockname = get_string('pluginname', 'block_' . $blockname);
    }

    $class = ''; // Nothing fancy, by default.

    if (has_capability('mod/contextadmin:changevisibilty', $context)) {
        $self_path        = "blocks.php?contextid=$contextid&catid=$catid";
        $is_overridden    = is_block_overridden($catid, $blockname);
        $is_locked        = is_block_locked($catid, $blockname);
        $locked_image_tag = create_image_tag($OUTPUT->pix_url('i/hierarchylock'), 'locked in parent category');
        // Representation of the block if we were to climb the tree.
        $block_representation = get_context_block_settings($catid, $blockname);

        // For each section provide a form if it is not locked. If it is locked only show icons.
        // If the block is overridden then show the overridden value in front of the category's value.
        if ($is_overridden) {
            if ($block_representation->visible) {
                $override_value_td .= create_image_tag($OUTPUT->pix_url('i/hide'), get_string('visible_alt',
                                                                                              'local_contextadmin'), 'overridden');
            } else {
                $override_value_td .= create_image_tag($OUTPUT->pix_url('i/show'), get_string('not_visible_alt',
                                                                                              'local_contextadmin'), 'overridden');
            }
        }

        // Test for existence of this category's module.
        if ($category_block) {

            if ($category_block->visible) {
                if ($is_locked) {
                    $visible_td .= create_image_tag($OUTPUT->pix_url('i/hide'), 'hidden');
                    $visible_td .= $locked_image_tag;

                } else {
                    $visible_td .= create_form($OUTPUT, $current_block->name . "_visible_form", $self_path, $strhide, 'hide',
                                               array('block_name' => $current_block->name, 'visible' => 'false',
                                                     'sesskey'    => sesskey()));
                }
            } else {
                if ($is_locked) {
                    $visible_td .= create_image_tag($OUTPUT->pix_url('i/show'), 'visible');
                    $visible_td .= $locked_image_tag;
                } else {
                    $visible_td .= create_form($OUTPUT, $current_block->name . "_visible_form", $self_path, $strhide, 'show',
                                               array('block_name' => $current_block->name, 'visible' => 'true',
                                                     'sesskey'    => sesskey()));
                    $class = ' class="dimmed_text"';
                }
            }

            if (!$is_locked) {
                $clear_td = create_form($OUTPUT, $current_block->name . "_clear_form", $self_path, $strhide, 'cross_red_big',
                                        array('block_name' => $current_block->name, 'clear' => 'true', 'sesskey' => sesskey()));
            }

            if ($category_block->override) {

                if (!$is_locked) {
                    $override_td =
                        create_form($OUTPUT, $current_block->name . "_override_form", $self_path, $strhide, 'completion-manual-y',
                                    array('block_name' => $current_block->name, 'override' => 'false', 'sesskey' => sesskey()));
                    $class       = '';
                } else {
                    $override_td = create_image_tag($OUTPUT->pix_url('i/completion-manual-y'), 'locked in parent category');
                }
            } else {
                if (!$is_locked) {
                    $override_td =
                        create_form($OUTPUT, $current_block->name . "_override_form", $self_path, $strhide, 'completion-manual-n',
                                    array('block_name' => $current_block->name, 'override' => 'true', 'sesskey' => sesskey()));
                } else {
                    $override_td = create_image_tag($OUTPUT->pix_url('i/completion-manual-n'), 'locked in parent category');
                }
            }

            if ($category_block->locked) {
                if (!$is_locked) {
                    $locked_td =
                        create_form($OUTPUT, $current_block->name . "_locked_form", $self_path, $strhide, 'completion-manual-y',
                                    array('block_name' => $current_block->name, 'locked' => 'false', 'sesskey' => sesskey()));
                    $class     = '';
                } else {
                    $locked_td = create_image_tag($OUTPUT->pix_url('i/completion-manual-y'), 'locked in parent category');
                }
            } else {
                if (!$is_locked) {
                    $locked_td =
                        create_form($OUTPUT, $current_block->name . "_locked_form", $self_path, $strhide, 'completion-manual-n',
                                    array('block_name' => $current_block->name, 'locked' => 'true', 'sesskey' => sesskey()));
                } else {
                    $locked_td = create_image_tag($OUTPUT->pix_url('i/completion-manual-n'), 'locked in parent category');
                }
            }
        } else { // Nothing set at this category so lets show the current representation instead.

            if ($block_representation->visible) {
                if ($is_locked) {
                    $visible_td .= create_image_tag($OUTPUT->pix_url('i/hide'), 'hidden');
                    $visible_td .= $locked_image_tag;

                } else {
                    $visible_td .= create_form($OUTPUT, $current_block->name . "_visible_form", $self_path, $strhide, 'hide',
                                               array('block_name' => $current_block->name, 'visible' => 'false',
                                                     'sesskey'    => sesskey()));
                }
            } else {
                if ($is_locked) {
                    $visible_td .= create_image_tag($OUTPUT->pix_url('i/show'), 'visible');
                    $visible_td .= $locked_image_tag;
                } else {
                    $visible_td .= create_form($OUTPUT, $current_block->name . "_visible_form", $self_path, $strhide, 'show',
                                               array('block_name' => $current_block->name, 'visible' => 'true',
                                                     'sesskey'    => sesskey()));
                    $class = ' class="dimmed_text"';
                }
            }
        }
    }

    $tabledata   = array('<span' . $class . '>' . $blockname . '</span>');
    $tabledata[] = $override_value_td;
    $tabledata[] = $visible_td;
    $tabledata[] = $override_td;
    $tabledata[] = $locked_td;
    $tabledata[] = $clear_td;
    $tabledata[] = $settings_td;

    $table->add_data($tabledata);
}

// TODO: Deprecated function.
$table->print_html();
echo $OUTPUT->footer();
