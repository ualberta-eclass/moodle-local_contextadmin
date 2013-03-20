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
 * Interface for anything relying on category level settings/configurations
 *
 * The interface that is implemented by anything that appears in the category level of customization.
 * It forces inheriting classes to define a method for checking user permissions
 * and methods for setting and getting the category that the class related to.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface part_of_category
{

    public function check_access();

    /**
     * Show we display Save button at the page bottom?
     * @return bool
     */
    public function show_save();
}


/**
 * Used to group a number of category_setting objects into a page.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_settingpage implements part_of_category
{

    /** @var string An internal name for this external page. Must be unique amongst ALL part_of_admin_tree objects */
    public $name;

    /** @var string The displayed name for this external page. Usually obtained through get_string(). */
    public $visiblename;

    /** @var mixed An array of admin_setting objects that are part of this setting page. */
    public $settings;

    /** @var string The role capability/permission a user must have to access this external page. */
    public $req_capability;

    /** @var object The context in which capability/permission should be checked, default is site context. */
    public $context;

    /** @var mixed string of paths or array of strings of paths */
    public $path;

    /** @var array list of visible names of page parents */
    public $visiblepath;

    /** @var int representing the category id */
    private $category;

    /**
     * see category_settingpage for details of this function
     *
     * @param string $name The internal name for this external page. Must be unique amongst ALL part_of_admin_tree objects.
     * @param string $visiblename The displayed name for this external page. Usually obtained through get_string().
     * @param mixed $req_capability The role capability/permission a user must have to access this external page. Defaults to
     * 'moodle/site:config'.
     * @param boolean $hidden Is this external page hidden in admin tree block? Default false.
     * @param stdClass $context The context the page relates to. Not sure what happens
     *      if you specify something other than system or front page. Defaults to system.
     */
    public function __construct($name, $visiblename, $req_capability = 'moodle/site:config', $context = null) {
        global $PAGE;
        $this->visiblename = '';
        $this->settings    = new stdClass();
        $this->name        = $name;

        // Display the category name in the header, gives context to the settings.
        $category = $PAGE->category;
        $this->visiblename .= $category->name . ': ';

        $this->visiblename .= ucfirst($visiblename);
        if (is_array($req_capability)) {
            $this->req_capability = $req_capability;
        } else {
            $this->req_capability = array($req_capability);
        }
        $this->context = $context;
    }

    /**
     * Adds a category_setting to this category_settingpage
     *
     * not the same as add for admin_category. adds an admin_setting to this admin_settingpage. settings appear (on the settingpage)
     * in the order in which they're added.
     * n.b. each admin_setting in an admin_settingpage must have a unique internal name
     *
     * @param object $setting is the admin_setting object you want to add
     * @return bool true if successful, false if not
     */
    public function add($setting) {
        if (!($setting instanceof admin_setting)) {
            debugging('error - not a category setting instance');
            return false;
        }
        $this->settings->{$setting->name} = $setting;
        return true;
    }

    /**
     * see admin_externalpage
     *
     * @return bool Returns true for yes false for no
     */
    public function check_access() {
        // This should fail for non site admins if context is not set higher or equal to course category.
        // Not even category managers have access to categories when in the system context.
        $context = empty($this->context) ? get_context_instance(CONTEXT_SYSTEM) : $this->context;
        foreach ($this->req_capability as $cap) {
            if (has_capability($cap, $context)) {
                return true;
            }
        }
        return false;
    }

    /**
     * outputs this page as html in a table (suitable for inclusion in an admin pagetype)
     * @return string Returns an XHTML string
     */
    public function output_html() {
        $return = '<fieldset>' . "\n" . '<div class="clearer"><!-- --></div>' . "\n";
        foreach ($this->settings as $setting) {
            $fullname = $setting->get_full_name();
            $data = $setting->get_setting();
            $return .= $setting->output_html($data);
        }
        $return .= '</fieldset>';
        return $return;
    }

    /**
     * Show we display Save button at the page bottom?
     * @return bool
     */
    public function show_save() {
        foreach ($this->settings as $setting) {
            if (empty($setting->nosave)) {
                return true;
            }
        }
        return false;
    }

    public function get_settings() {
        return $this->settings;
    }
}


/**
 * Store changed settings, this function updates the errors variable in $ADMIN
 *
 * @param object $formdata from form
 * @return int number of changed settings
 */
function cat_write_settings($formdata, $settingspage) {
    global $CFG, $SITE, $DB;

    $olddbsessions = !empty($CFG->dbsessions);
    $formdata      = (array)$formdata;

    $data = array();
    foreach ($formdata as $fullname => $value) {
        if (strpos($fullname, 's_') !== 0) {
            continue; // NHot a config value.
        }
        $data[$fullname] = $value;
    }

    $settings = array();

    foreach ($settingspage->settings as $setting) {
        $fullname = $setting->get_full_name();
        if (array_key_exists($fullname, $data)) {
            $settings[$fullname] = $setting;
        }
    }

    $count = 0;
    foreach ($settings as $fullname => $setting) {
        $error = $setting->write_setting($data[$fullname]);
        if ($error == '') {

            $count++;
            $callbackfunction = $setting->updatedcallback;
            if (function_exists($callbackfunction)) {
                $callbackfunction($fullname);
            }
        }
    }

    if ($olddbsessions != !empty($CFG->dbsessions)) {
        require_logout();
    }

    // Now update $SITE - just update the fields, in case other people have a
    // a reference to it (e.g. $PAGE, $COURSE).
    $newsite = $DB->get_record('course', array('id' => $SITE->id));
    foreach (get_object_vars($newsite) as $field => $value) {
        $SITE->$field = $value;
    }

    return $count;
}
