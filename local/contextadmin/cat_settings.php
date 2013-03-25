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

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/contextadmin/locallib.php');
require_once($CFG->dirroot . '/local/contextadmin/catlib.php');

/*
 *  Must require this library explicitly due to an incompatibility with how it is referenced by the imsenterprise plugin.
 *  This is required in order to use the admin_settings classes for our category settings.
 */
require_once($CFG->dirroot . '/enrol/imsenterprise/locallib.php');

$section      = required_param('section', PARAM_SAFEDIR);
$modname      = required_param('name', PARAM_ALPHA);
$context_id   = required_param('contextid', PARAM_INT);
$return       = optional_param('return', '', PARAM_ALPHA);
$adminediting = optional_param('adminedit', -1, PARAM_BOOL);

// No guest autologin.
require_login(0, false);
// Setup Page.
$PAGE->set_context(get_context_instance_by_id($context_id));
$PAGE->set_url('/local/contextadmin/cat_settings.php',
               array('section' => $section, 'name' => $modname, 'contextid' => $context_id));
$PAGE->set_pagetype('admin-setting-' . $section);
$PAGE->set_pagelayout('coursecategory');
$PAGE->set_category_by_id($PAGE->context->instanceid);

// CREATE SETTINGS PAGE FOR THE GIVEN MODULES / BLOCK.
if (file_exists($CFG->dirroot . '/local/contextadmin/mod/' . $modname . '/cat_settings.php')) {
    // Create a category_settingpage (much like admin_settingpage without the navigation ties.
    $settings =
        new category_settingpage($section, $modname, 'mod/contextadmin:editowncatsettings',
                                 get_context_instance_by_id($context_id));
    include($CFG->dirroot . '/local/contextadmin/mod/' . $modname . '/cat_settings.php');
    // The module loads the settings with the cat_settings.php file located in the module directory of the module being loaded.
    $settingspage = $settings;
}

// TODO: Create category manager error messages in lang file.  Using admin strings atm.
if (empty($settingspage) or !($settingspage instanceof category_settingpage)) {
    print_error('sectionerror', 'admin', "$CFG->wwwroot/$CFG->admin/");
    die;
}

if (!($settingspage->check_access())) {
    print_error('accessdenied', 'admin');
    die;
}


$statusmsg = '';
$errormsg  = '';
$focus     = '';

if ($data = data_submitted() and confirm_sesskey()) {
    if (cat_write_settings($data, $settingspage)) {
        $statusmsg = get_string('changessaved');
    }

    if (empty($adminroot->errors)) {
        switch ($return) {
            case 'site':
                redirect("$CFG->wwwroot/");
            case 'admin':
                redirect("$CFG->wwwroot/$CFG->admin/");
        }
    } else {
        $errormsg   = get_string('errorwithsettings', 'admin');
        $firsterror = reset($adminroot->errors);
        $focus      = $firsterror->id;
    }
}

if ($PAGE->user_allowed_editing() && $adminediting != -1) {
    $USER->editing = $adminediting;
}

// Print header stuff.
if (empty($SITE->fullname)) {
    $PAGE->set_title($settingspage->visiblename);
    $PAGE->set_heading($settingspage->visiblename);

    echo $OUTPUT->header();
    echo $OUTPUT->box(get_string('configintrosite', 'admin'));

    if ($errormsg !== '') {
        echo $OUTPUT->notification($errormsg);

    } else if ($statusmsg !== '') {
        echo $OUTPUT->notification($statusmsg, 'notifysuccess');
    }

    echo '<form action="cat_settings.php" method="post" id="adminsettings">';
    echo '<div class="settingsform clearfix">';
    echo html_writer::input_hidden_params($PAGE->url);
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
    echo '<input type="hidden" name="return" value="' . $return . '" />';

    echo $settingspage->output_html();

    echo '<div class="form-buttons"><input class="form-submit" type="submit" value="' . get_string('savechanges',
                                                                                                   'admin') . '" /></div>';

    echo '</div>';
    echo '</form>';

} else {
    if ($PAGE->user_allowed_editing()) {
        $url = clone($PAGE->url);
        if ($PAGE->user_is_editing()) {
            $caption = get_string('blockseditoff');
            $url->param('adminedit', 'off');
        } else {
            $caption = get_string('blocksediton');
            $url->param('adminedit', 'on');
        }
        $buttons = $OUTPUT->single_button($url, $caption, 'get');
    }

    $PAGE->set_title("$SITE->shortname: ");
    $PAGE->set_heading($SITE->fullname);
    if ($PAGE->user_allowed_editing()) {
        $PAGE->set_button($buttons);
    }
    echo $OUTPUT->header();

    if ($errormsg !== '') {
        echo $OUTPUT->notification($errormsg);

    } else if ($statusmsg !== '') {
        echo $OUTPUT->notification($statusmsg, 'notifysuccess');
    }


    echo '<form action="cat_settings.php" method="post" id="adminsettings">';
    echo '<div class="settingsform clearfix">';
    echo html_writer::input_hidden_params($PAGE->url);
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
    echo '<input type="hidden" name="return" value="' . $return . '" />';
    echo $OUTPUT->heading($settingspage->visiblename);

    echo $settingspage->output_html();

    if ($settingspage->show_save()) {
        echo '<div class="form-buttons"><input class="form-submit" type="submit" value="' . get_string('savechanges',
                                                                                                       'admin') . '" /></div>';
    }

    echo '</div>';
    echo '</form>';
}

echo $OUTPUT->footer();
