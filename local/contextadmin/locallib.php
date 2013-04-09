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

defined('MOODLE_INTERNAL') || die();

if (!defined("CONTEXTADMINDEBUG")) {
    define("CONTEXTADMINDEBUG", false);
}

/**
 * If no category root exists creates it and returns it. Otherwise just returns the already created root.
 * @param settings_navigation $nav
 * @return navigation_node
 */
function category_get_root(settings_navigation &$nav) {
    static $category_root = null;
    if (!isset($category_root)) {
        $category_root = $nav->add(get_string('catadmin', 'local_contextadmin')); // Root Node.
    }
    return $category_root;
}

/**
 * Returns the path string associated with a groupid
 * Returns "" if groupid not found
 * @param $groupid
 * @return string
 */
function get_category_path($catid) {
    global $DB;
    $rec = $DB->get_record('course_categories', array('id' => $catid), 'path');
    if (empty($rec)) {
        return "0";
    }
    return $rec->path;
}

/**
 * Retrieves a list of all visible blocks in the category context
 * @param $categoryid
 * @return array
 */
function get_all_blocks($categoryid) {
    return get_context_list($categoryid, 'block');
}

/**
 * Retrieves a list of all visible modules in the category context
 * @param $categoryid
 * @return array
 */
function get_context_modules($categoryid) {
    return get_context_list($categoryid, 'modules');
}

/**
 * Returns a list of all objects of type $type in the config tables.
 * The function will climb the list and return only the correct plugin values in the context hierarchy.
 * @param $categoryid
 * @param string $type
 * @return array
 */
function get_context_list($categoryid, $type = 'modules') {
    global $DB;
    if (CONTEXTADMINDEBUG) {
        echo "get_context_obj($categoryid, $type):\n";
    }
    $path        = get_category_path($categoryid);
    $path_string = ltrim($path, '/');
    $a_path      = explode('/', $path_string);
    $a_rev_path  = array_reverse($a_path);

    // Build on these.
    $site_objects = $DB->get_records("$type");
    if (!empty($categoryid)) {

        /*
        * go through the categories starting from nearest to top
        * 1. extract records for current category
        * 2. process records and collect up changes first in collection overrides later ones
        * 3. apply collected settings over the site_modules and return
        */

        $object_collection = array(); // Keys should be module names.

        foreach ($a_rev_path as $catid) {
            $a_cur = $DB->get_records("cat_$type", array('category_id' => $catid));
            foreach ($a_cur as $cur) {
                if (CONTEXTADMINDEBUG) {
                    echo "Found " . $cur->name . " value: " . $cur->visible . " in cat $catid";
                }
                if (!array_key_exists($cur->name, $object_collection) || $cur->override == true) {
                    $object_collection[$cur->name] = $cur;
                } else {
                    if (CONTEXTADMINDEBUG) {
                        echo " (preceded by earlier category)";
                    }
                }
                if (CONTEXTADMINDEBUG) {
                    echo "\n";
                }
            }
        }
        foreach ($site_objects as $smod) {
            if (array_key_exists($smod->name, $object_collection)) {
                $smod->visible = $object_collection[$smod->name]->visible;
                if ($type == 'modules') {
                    $smod->search = $object_collection[$smod->name]->search;
                }

            }
        }
    }
    return $site_objects;
}


/**
 * Returns the content of the 'value' field for the desired plugin setting.
 * Climbs the tree of the categories searching for correct record.
 * @param $categoryid
 * @param $settingname
 * @param null $pluginname
 * @return mixed|null
 */
function get_context_config_field($categoryid, $settingname, $pluginname = null) {
    global $DB;
    if (CONTEXTADMINDEBUG) {
        echo "get_context_config_field($categoryid,$settingname,$pluginname):\n";
    }
    $path        = get_category_path($categoryid);
    $path_string = ltrim($path, '/');
    $a_path      = explode('/', $path_string);
    $a_rev_path  = array_reverse($a_path);

    $return_value = null;
    if (!empty($pluginname)) {
        foreach ($a_rev_path as $catid) {
            $cur = $DB->get_record("cat_config_plugins",
                                   array('category_id' => $catid, 'plugin' => $pluginname, 'name' => $settingname),
                                   "value,override");
            // Must go through whole category list in case overridden above.
            // **NOTE** -> Since checkboxes return 0, we need !== instead of != and we also cannot use empty as a comparator....
            if ($cur !== false && (empty($return_value) || $cur->override)) {
                $return_value = $cur->value;
            }
        }

        if (empty($return_value)) {
            if (CONTEXTADMINDEBUG) {
                echo "Used system level config name: $settingname\n";
            }
            return $DB->get_field('config_plugins', 'value', array('plugin' => $pluginname, 'name' => $settingname));
        }
        return $return_value;
    } else {
        foreach ($a_rev_path as $catid) {
            $cur = $DB->get_record("cat_config", array('category_id' => $catid, 'name' => $settingname), "value,override");
            // Must go through whole category list in case overridden above.
            // **NOTE** -> Since checkboxes return 0, we need !== instead of != and we also cannot use empty as a comparator....
            if ($cur !== false && (empty($return_value) || $cur->override)) {
                $return_value = $cur->value;
            }
        }

        if (empty($return_value)) {
            if (CONTEXTADMINDEBUG) {
                echo "Used system level config name: $settingname\n";
            }
            return $DB->get_field('config', 'value', array('name' => $settingname));
        }
        return $return_value;
    }
}


/**
 * This function works in a way similar to get_config except it cycles through the context path to gather settings from
 * multiple categories up the tree to the global setting.
 * If no settings are set at a category level it will fall back to the settings at the global level.
 *
 * @param $categoryid
 * @param null $pluginname
 * @return array
 */
function get_context_config($categoryid, $pluginname = null) {
    global $DB;
    if (CONTEXTADMINDEBUG) {
        echo "get_context_config($categoryid,$pluginname):\n";
    }

    $path        = get_category_path($categoryid);
    $path_string = ltrim($path, '/');
    $a_path      = explode('/', $path_string);
    $a_rev_path  = array_reverse($a_path);

    /*
    * go through the categories starting from nearest to top
    * 1. extract records for current category
    * 2. process records and collect up changes first in collection overrides later ones
    * 3. apply collected settings over the site_modules and return
    */

    $set_collection = array(); // Keys should be setting names.
    if (!empty($pluginname)) {
        // Build on these.
        $site_settings = $DB->get_records_menu('config_plugins', array('plugin' => $pluginname), '', 'name,value');
        foreach ($a_rev_path as $catid) {
            $a_cur = $DB->get_records("cat_config_plugins", array('category_id' => $catid, 'plugin' => $pluginname));
            foreach ($a_cur as $cur) {
                eclass_debug(
                    "Found config plugin: " . $pluginname . ": " . $cur->name . " value: " . $cur->value . " in cat $catid");
                if (!array_key_exists($cur->name, $set_collection) || $cur->override) {
                    $set_collection[$cur->name] = $cur;
                } else {
                    eclass_debug(" (preceded by earlier cat)");
                }
                eclass_debug("\n");
            }
        }
        return array_merge($site_settings, $set_collection);
    } else {
        // Core plugin settings.
        // Build on these.
        $site_settings = $DB->get_records_menu('config', array(), '', 'name,value');
        foreach ($a_rev_path as $catid) {
            $a_cur = $DB->get_records("cat_config", array('category_id' => $catid));
            foreach ($a_cur as $cur) {
                eclass_debug("Found config: " . $cur->name . " value: " . $cur->value . " in cat $catid");

                if (!array_key_exists($cur->name, $set_collection) || $cur->override) {
                    $set_collection[$cur->name] = $cur;
                } else {
                    eclass_debug(" (preceded by earlier cat)");
                }
                eclass_debug("\n");
            }
        }
        // Augment the standard settings.
        return array_merge($site_settings, $set_collection);
    }
}

/**
 * Returns whether the userid has the contextadmin category view capability at the system context level.
 * @param int $userid
 */
function has_category_view_capability($userid) {
    global $DB;
    if (is_siteadmin()) {
        return true;
    }

    $sql    = "select *
            from {role_assignments} ra join {role_capabilities} rc ON(ra.roleid=rc.roleid)
            where capability = :capability and ra.userid = :userid and rc.contextid = :ctx";
    $params = array('capability' => 'mod/contextadmin:viewcategories', 'userid' => $userid, 'ctx' => 1);
    $result = $DB->get_records_sql($sql, $params, 0, 1);
    return !empty($result);
}

function eclass_debug($msg) {
    if (defined(CONTEXTADMINDEBUG)) {
        echo $msg;
    }
}

// CATEGORY DISPLAY (Functions that list the Categories that are Managed by the current User).

/**
 * Recursive function to print out all the categories in a nice format
 * with or without courses included
 *
 * @param null $category current category
 * @param null $displaylist display list
 * @param null $parentslist list of parent categories
 * @param $depth depth of the category for indentation purposes
 * @param bool $showcourses determines if we display courses and course information
 * @param string $branch current output that is stored until visibilty is true. Otherwise this branch output gets destroyed
 * @param bool $visible maintains the visibility state of the navigation branch
 * @param $output variable used to output html during recursion
 * @return mixed
 */
function print_whole_category_manager_list($category = null, $displaylist = null, $parentslist = null, $depth = -1,
                                           $showcourses = true, $branch = '', $visible = false, &$output) {
    global $CFG;

    // Note: maxcategorydepth == 0 meant no limit.
    if (!empty($CFG->maxcategorydepth) && $depth >= $CFG->maxcategorydepth) {
        return;
    }

    if (!$displaylist) {
        make_categories_manager_list($displaylist, $parentslist);
    }

    if ($category) {

        if (!has_capability('moodle/category:viewhiddencategories',
                            get_context_instance(CONTEXT_COURSECAT, $category->id)) && $visible
        ) {
            $visible = false;
            $branch  = print_category_manager_info($category, $depth, $showcourses, $visible);
        } else if ($visible) {
            $branch .= print_category_manager_info($category, $depth, $showcourses, $visible);
            $output .= $branch;
            $branch = '';
        } else {
            $branch .= print_category_manager_info($category, $depth, $showcourses, $visible);
        }

    } else {
        $category = new stdClass();
        $category->id = "0";
    }

    if ($categories = get_child_manager_categories($category->id)) { // Print all the children recursively.
        $countcats = count($categories);
        $count     = 0;
        $first     = true;
        $last      = false;

        foreach ($categories as $cat) {
            if (has_capability('moodle/category:viewhiddencategories',
                               get_context_instance(CONTEXT_COURSECAT, $cat->id)) && !$visible
            ) {
                $visible = true;
            }
            $count++;
            if ($count == $countcats) {
                $last = true;
            }
            $up    = $first ? false : true;
            $down  = $last ? false : true;
            $first = false;

            print_whole_category_manager_list($cat, $displaylist, $parentslist, $depth + 1, $showcourses, $branch, $visible,
                                              $output);
        }
    } else {
        $visible = false;
        $depth   = 0;
        $branch  = '';
    }
}

/**
 * @param $list
 * @param $parents
 * @param string $requiredcapability
 * @param int $excludeid
 * @param null $category
 * @param string $path
 * @return mixed
 */
function make_categories_manager_list(&$list, &$parents, $requiredcapability = '', $excludeid = 0, $category = null, $path = "") {
    $requiredcapability = '';

    // Initialize the arrays if needed.
    if (!is_array($list)) {
        $list = array();
    }
    if (!is_array($parents)) {
        $parents = array();
    }

    if (empty($category)) {
        // Start at the top level.
        $category     = new stdClass;
        $category->id = 0;
    } else {
        // This is the excluded category, don't include it.
        if ($excludeid > 0 && $excludeid == $category->id) {
            return;
        }

        $context      = get_context_instance(CONTEXT_COURSECAT, $category->id);
        $categoryname = format_string($category->name, true, array('context' => $context));

        // Update $path.
        if ($path) {
            $path = $path . ' / ' . $categoryname;
        } else {
            $path = $categoryname;
        }

        // Add this category to $list, if the permissions check out.
        if (empty($requiredcapability)) {
            $list[$category->id] = $path;

        } else {
            $requiredcapability = (array)$requiredcapability;
            if (has_all_capabilities($requiredcapability, $context)) {
                $list[$category->id] = $path;
            }
        }
    }

    // Add all the children recursively, while updating the parents array.
    if ($categories = get_child_manager_categories($category->id)) {
        foreach ($categories as $cat) {
            if (!empty($category->id)) {
                if (isset($parents[$category->id])) {
                    $parents[$cat->id] = $parents[$category->id];
                }
                $parents[$cat->id][] = $category->id;
            }
            make_categories_manager_list($list, $parents, $requiredcapability, $excludeid, $cat, $path);
        }
    }
}


/**
 * Get the children categories of a given parent
 *
 * @param $parentid
 * @return array
 */
function get_child_manager_categories($parentid) {
    static $allcategories = null;

    // Only fill in this variable the first time.
    if (null == $allcategories) {
        $allcategories = array();

        $categories = get_manager_categories();
        foreach ($categories as $category) {
            if (empty($allcategories[$category->parent])) {
                $allcategories[$category->parent] = array();
            }
            $allcategories[$category->parent][] = $category;
        }
    }

    if (empty($allcategories[$parentid])) {
        return array();
    } else {
        return $allcategories[$parentid];
    }
}

/**
 * Be careful with this function, there is no check for the capapbility viewhiddencategory This check needs to be done
 * elsewhere if you need this kind of restriction. (This function returns all categories regardless of viewhidden capability.
 * This is needed to show the root of the category you DO have permission to see.
 * This gives manager context to where their category resides in the hierarchy if the parent category is hidden).
 *
 * @param string $parent
 * @param null $sort
 * @param bool $shallow
 * @return array
 */
function get_manager_categories($parent = 'none', $sort = null, $shallow = true) {
    global $DB;

    if ($sort === null) {
        $sort = 'ORDER BY cc.sortorder ASC';
    } else if ($sort === '') {
        $sort = '';
    } else {
        $sort = "ORDER BY $sort";
    }

    list($ccselect, $ccjoin) = context_instance_preload_sql('cc.id', CONTEXT_COURSECAT, 'ctx');

    if ($parent === 'none') {
        $sql    = "SELECT cc.* $ccselect
                  FROM {course_categories} cc
               $ccjoin
                $sort";
        $params = array();

    } else if ($shallow) {
        $sql    = "SELECT cc.* $ccselect
                  FROM {course_categories} cc
               $ccjoin
                 WHERE cc.parent=?
                $sort";
        $params = array($parent);

    } else {
        $sql    = "SELECT cc.* $ccselect
                  FROM {course_categories} cc
               $ccjoin
                  JOIN {course_categories} ccp
                       ON ((cc.parent = ccp.id) OR (cc.path LIKE " . $DB->sql_concat('ccp.path', "'/%'") . "))
                 WHERE ccp.id=?
                $sort";
        $params = array($parent);
    }
    $categories = array();

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $cat) {
        context_instance_preload($cat);
        $catcontext           = get_context_instance(CONTEXT_COURSECAT, $cat->id);
        $categories[$cat->id] = $cat;
    }
    $rs->close();
    return $categories;
}

/**
 *  Prints the category info in indented fashion
 *  There are two display possibilities.
 *    1. Display categories without courses ($showcourses = false)
 *    2. Display categories with courses ($showcategories = true)
 *
 *  This function is only used by print_whole_manager_category_list() above
 */
function print_category_manager_info($category, $depth = 0, $showcourses = false, $visible) {
    global $CFG, $DB, $OUTPUT;
    $output = '';

    $strsummary = get_string('summary');

    $catlinkcss = null;
    if (!$category->visible) {
        $catlinkcss = array('class' => 'dimmed');
    }
    static $coursecount = null;
    if (null === $coursecount) {
        // Only need to check this once.
        $coursecount = $DB->count_records('course') <= FRONTPAGECOURSELIMIT;
    }

    if ($visible) {
        $catimage = '<img src="' . $OUTPUT->pix_url('i/course') . '" alt="" />&nbsp;';
    } else {
        $catimage = '<img src="' . $OUTPUT->pix_url('courseclosed', 'local_contextadmin') . '" alt="" />&nbsp;';
    }

    $courses  = get_courses($category->id, 'c.sortorder ASC', 'c.id,c.sortorder,c.visible,c.fullname,c.shortname,c.summary');
    $context  = get_context_instance(CONTEXT_COURSECAT, $category->id);
    $fullname = format_string($category->name, true, array('context' => $context));

    $output .= '<div class="categorylist clearfix">';
    $cat = '';
    $cat .= html_writer::tag('div', $catimage, array('class' => 'image'));
    if ($visible) {
        $catlink = html_writer::link(new moodle_url('/course/category.php', array('id' => $category->id)), $fullname, $catlinkcss);
        $cat .= html_writer::tag('div', $catlink, array('class' => 'name'));
    } else {
        $cat .= html_writer::tag('div', $fullname, array('class' => 'name'));
    }

    $html = '';
    if ($depth > 0) {
        for ($i = 0; $i < $depth; $i++) {
            $html = html_writer::tag('div', $html . $cat, array('class' => 'indentation'));
            $cat  = '';
        }
    } else {
        $html = $cat;
    }
    $output .= html_writer::tag('div', $html, array('class' => 'category'));
    $output .= html_writer::tag('div', '', array('class' => 'clearfloat'));

    // Does the depth exceed maxcategorydepth.
    // Note: maxcategorydepth == 0 or unset meant no limit.
    $limit = !(isset($CFG->maxcategorydepth) && ($depth >= $CFG->maxcategorydepth - 1));
    if ($courses && ($limit || $CFG->maxcategorydepth == 0) && $showcourses) {
        $output .= '<br>';
        foreach ($courses as $course) {
            $linkcss = null;
            if (!$course->visible) {
                $linkcss = array('class' => 'dimmed');
            }

            $courselink = html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)),
                                            format_string($course->fullname), $linkcss);

            // Print enrol info.
            $courseicon = '';
            if ($icons = enrol_get_course_info_icons($course)) {
                foreach ($icons as $pix_icon) {
                    $courseicon = $OUTPUT->render($pix_icon) . ' ';
                }
            }

            $coursecontent = html_writer::tag('div', $courseicon . $courselink, array('class' => 'name'));

            if ($course->summary) {
                $link       = new moodle_url('/course/info.php?id=' . $course->id);
                $actionlink = $OUTPUT->action_link($link,
                                                   '<img alt="' . $strsummary . '" src="' . $OUTPUT->pix_url('i/info') . '" />',
                                                   new popup_action('click', $link, 'courseinfo', array('height' => 400,
                                                                                                        'width'  => 500)),
                                                   array('title' => $strsummary));

                $coursecontent .= html_writer::tag('div', $actionlink, array('class' => 'info'));
            }

            $html = '';
            for ($i = 0; $i <= $depth; $i++) {
                $html          = html_writer::tag('div', $html . $coursecontent, array('class' => 'indentation'));
                $coursecontent = '';
            }
            $output .= html_writer::tag('div', $html, array('class' => 'course clearfloat'));
        }
    }
    $output .= '</div>';
    return $output;
}

/**
 * Convenience method to retrieve settings for module
 * @param $categoryid
 * @param $pluginname
 * @param bool $climb
 * @return mixed|string
 */
function get_context_module_settings($categoryid, $pluginname, $climb = true) {
    return get_category_plugin_values($categoryid, $pluginname, 'modules', $climb);
}

/**
 * Convenience method to set settings for module
 * @param $categoryid
 * @param $pluginname
 * @param $values
 */
function set_context_module_settings($categoryid, $pluginname, $values) {
    set_category_plugin_values($categoryid, $pluginname, 'modules', $values);
}

/**
 * Convenience method to retrieve settings for block
 * @param $categoryid
 * @param $pluginname
 * @param bool $climb
 * @return mixed|string
 */
function get_context_block_settings($categoryid, $pluginname, $climb = true) {
    return get_category_plugin_values($categoryid, $pluginname, 'block', $climb);
}

/**
 * Convenience method to set settings for block
 * @param $categoryid
 * @param $pluginname
 * @param $values
 */
function set_context_block_settings($categoryid, $pluginname, $values) {
    set_category_plugin_values($categoryid, $pluginname, 'block', $values);
}

/**
 * Retrieves the record object for a plugin by climbing the category tree.
 * @param $categoryid
 * @param $pluginname
 * @param $plugintype
 * @return stdclass record, false if not found
 *
 */
/**
 * Retrieves the record object for a plugin by climbing the category tree.
 * If $climb is false then just returns the record of the provided category id
 * @param $categoryid
 * @param $pluginname
 * @param $plugintype
 * @param bool $climb
 * @return stdclass record, false if not found
 * @throws Exception
 */
function get_category_plugin_values($categoryid, $pluginname, $plugintype, $climb = true) {
    global $DB;
    if (CONTEXTADMINDEBUG) {
        echo "get_category_plugin_values($categoryid,$pluginname, $plugintype):\n";
    }

    if (empty($pluginname)) {
        throw new Exception("Missing pluginname in get_category_plugin_values");
    }

    $validplugins = array('modules', 'block'); // Valid types.
    if (!in_array($plugintype, $validplugins)) {
        throw new Exception("Invalid plugintype in get_category_plugin_values");
    }

    $path        = get_category_path($categoryid);
    $path_string = ltrim($path, '/');
    $a_path      = explode('/', $path_string);
    $a_rev_path  = array_reverse($a_path);

    /*
    * go through the categories starting from nearest to top
    * 1. extract records for current category
    * 2. process records and collect up changes first in collection overrides later ones
    * 3. apply collected settings over the site_modules and return
    */
    $return_value = null;

    if ($climb) {
        // Use site if no context level exists.

        foreach ($a_rev_path as $catid) {
            $a_cur = $DB->get_record("cat_" . $plugintype, array('name' => $pluginname, 'category_id' => $catid));
            if (!empty($a_cur) && ($a_cur->override || empty($return_value))) {
                $return_value = $a_cur;
            }
        }

        $site_settings = $DB->get_record($plugintype, array('name' => $pluginname));
        // Merge the objects together so that the extra fields in the site_settings record exist in the returned object.
        $return_value = (object)array_merge((array)$site_settings, (array)$return_value);
        return $return_value;
    } else {
        // Don't climb the tree, just return the record.
        $catid = array_shift($a_rev_path);
        return $DB->get_record("cat_" . $plugintype, array('name' => $pluginname, 'category_id' => $catid));

    }
}

/**
 * Sets the settings for a module or block at the category level
 * @param $categoryid
 * @param $pluginname
 * @param $plugintype string the type of plugin to set (currently valid values are: modules, blocks)
 * @param $values array settings in the modules record (visible or search)
 */
function set_category_plugin_values($categoryid, $pluginname, $plugintype, $values) {
    global $DB;

    // Todo need to check rest of tree for locks above it.

    $validplugins = array('modules', 'block'); // Valid types.
    if (!in_array($plugintype, $validplugins)) {
        debugging('Invalid plugintype passed to set_category_plugin_values in local/contextadmin/locallib.php', DEBUG_DEVELOPER);
        return;
    }

    if (CONTEXTADMINDEBUG) {
        echo "set_category_plugin_values($categoryid,$pluginname,$plugintype, $values):\n";
    }

    if (!empty($pluginname) && !empty($categoryid)) {
        if ($record = $DB->get_record('cat_' . $plugintype, array('category_id' => $categoryid, 'name' => $pluginname))) {
            // Update.
            foreach ($values as $key => $value) {
                $record->$key = $value;
            }
            // Update db.
            $DB->update_record('cat_' . $plugintype, $record);
        } else {
            // Create.
            $record              = new stdClass();
            $record->name        = $pluginname;
            $record->category_id = $categoryid;
            $record              = (object)array_merge((array)$record, (array)$values);
            // Insert into db.
            $DB->insert_record('cat_' . $plugintype, $record);
        }
    } else {
        throw new Exception("set_category_plugin_values missing arguments ($categoryid, $pluginname)");
    }
}

function remove_category_module_values($categoryid, $pluginname) {
    remove_category_plugin_values($categoryid, $pluginname, 'modules');
}

function remove_category_block_values($categoryid, $pluginname) {
    remove_category_plugin_values($categoryid, $pluginname, 'block');
}

/**
 * Removes the settings for a module or block at the category level
 * @param $categoryid
 * @param $pluginname
 * @param $plugintype string the type of plugin to set (currently valid values are: modules, blocks)
 */
function remove_category_plugin_values($categoryid, $pluginname, $plugintype) {
    global $DB;

    // Todo need to check rest of tree for locks above it.

    $validplugins = array('modules', 'block'); // Valid types.
    if (!in_array($plugintype, $validplugins)) {
        debugging('Invalid plugintype passed to set_category_plugin_values in local/contextadmin/locallib.php', DEBUG_DEVELOPER);
        return;
    }

    if (CONTEXTADMINDEBUG) {
        echo "remove_category_plugin_values($categoryid,$pluginname,$plugintype):\n";
    }

    if (!empty($pluginname) && !empty($categoryid)) {
        $DB->delete_records('cat_' . $plugintype, array('category_id' => $categoryid, 'name' => $pluginname));

    } else {
        throw new Exception("set_category_plugin_values missing arguments ($categoryid, $pluginname)");
    }
}

/**
 * Checks if there exists a record above that has the locked flag set to true
 * @param $categoryid
 * @param $pluginname
 * @return bool true if locked, false if not locked
 *
 */
function is_module_locked($categoryid, $pluginname) {
    return is_plugin_locked($categoryid, $pluginname, 'modules');
}

/**
 * Checks if there exists a record above that has the locked flag set to true
 * @param $categoryid
 * @param $pluginname
 * @return bool true if locked, false if not locked
 *
 */
function is_block_locked($categoryid, $pluginname) {
    return is_plugin_locked($categoryid, $pluginname, 'block');
}

/**
 * Checks if there exists a record above that has the locked flag set to true
 * @param $categoryid
 * @param $pluginname
 * @param $plugintype
 * @return bool true if locked, false if not locked
 *
 */
function is_plugin_locked($categoryid, $pluginname, $plugintype) {
    global $DB;
    if (CONTEXTADMINDEBUG) {
        echo "is_plugin_locked($categoryid,$pluginname, $plugintype):\n";
    }

    if (empty($pluginname)) {
        return null;
    }

    $validplugins = array('modules', 'block'); // Valid types.
    if (!in_array($plugintype, $validplugins)) {
        debugging('Invalid plugintype passed to is_plugin_locked in local/contextadmin/locallib.php', DEBUG_DEVELOPER);
        return null;
    }

    $path        = get_category_path($categoryid);
    $path_string = ltrim($path, '/');
    $a_path      = explode('/', $path_string);
    $a_rev_path  = array_reverse($a_path);

    // Remove the first element. We can't lock ourselves.
    array_shift($a_rev_path);

    /*
    * go through the categories starting from nearest to top
    * 1. extract records for current category
    * 2. process records and collect up changes first in collection overrides later ones
    * 3. apply collected settings over the site_modules and return
    */
    if (!empty($pluginname)) {
        // Use site if no context level exists.

        foreach ($a_rev_path as $catid) {

            if ($DB->get_field("cat_" . $plugintype, 'locked',
                               array('name' => $pluginname, 'category_id' => $catid, 'locked' => 1))
            ) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Checks if there exists a record above that has the override flag set to true
 * @param $categoryid
 * @param $pluginname
 * @return bool true if locked, false if not locked
 *
 */
function is_module_overridden($categoryid, $pluginname) {
    return is_plugin_overridden($categoryid, $pluginname, 'modules');
}

/**
 * Checks if there exists a record above that has the override flag set to true
 * @param $categoryid
 * @param $pluginname
 * @return bool true if locked, false if not locked
 *
 */
function is_block_overridden($categoryid, $pluginname) {
    return is_plugin_overridden($categoryid, $pluginname, 'block');
}

/**
 * Checks if there exists a record above that has the override flag set to true
 * @param $categoryid
 * @param $pluginname
 * @param $plugintype
 * @return bool true if locked, false if not locked
 *
 */
function is_plugin_overridden($categoryid, $pluginname, $plugintype) {
    global $DB;
    if (CONTEXTADMINDEBUG) {
        echo "is_plugin_locked($categoryid,$pluginname, $plugintype):\n";
    }

    if (empty($pluginname)) {
        return null;
    }

    $validplugins = array('modules', 'block'); // Valid types.
    if (!in_array($plugintype, $validplugins)) {
        debugging('Invalid plugintype passed to is_plugin_overridden in local/contextadmin/locallib.php', DEBUG_DEVELOPER);
        return null;
    }

    $path        = get_category_path($categoryid);
    $path_string = ltrim($path, '/');
    $a_path      = explode('/', $path_string);
    $a_rev_path  = array_reverse($a_path);

    // Remove the first element. We can't override ourselves.
    array_shift($a_rev_path);

    /*
    * go through the categories starting from nearest to top
    * 1. extract records for current category
    * 2. process records and collect up changes first in collection overrides later ones
    * 3. apply collected settings over the site_modules and return
    */
    if (!empty($pluginname)) {

        foreach ($a_rev_path as $catid) {

            if ($DB->get_field("cat_" . $plugintype, 'override',
                               array('name' => $pluginname, 'category_id' => $catid, 'override' => 1))
            ) {
                return true;
            }
        }
        return false;
    }
}

function category_module_exists($categoryid, $pluginname) {
    return category_plugin_exists($categoryid, $pluginname, 'modules');
}

function category_block_exists($categoryid, $pluginname) {
    return category_plugin_exists($categoryid, $pluginname, 'block');
}

/**
 * Tests for existence of a record for the module at the category level.
 * @param $categoryid
 * @param $pluginname
 * @param $plugintype
 * @return bool
 */
function category_plugin_exists($categoryid, $pluginname, $plugintype) {
    global $DB;
    if (CONTEXTADMINDEBUG) {
        echo "category_plugin_exists($categoryid,$pluginname, $plugintype):\n";
    }

    if (empty($pluginname)) {
        return false;
    }

    $validplugins = array('modules', 'block'); // Valid types.
    if (!in_array($plugintype, $validplugins)) {
        debugging('Invalid plugintype passed to category_plugin_exists in local/contextadmin/locallib.php', DEBUG_DEVELOPER);
        return false;
    }

    // Use site if no context level exists.
    return $DB->record_exists("cat_" . $plugintype, array('name' => $pluginname, 'category_id' => $categoryid));
}

function print_cat_course_search($value = "", $return = false, $format = "plain") {
    global $CFG;
    static $count = 0;

    $perpagevalues = array(10, 20, 30, 50, 100);

    $count++;

    $id = 'coursesearch';

    if ($count > 1) {
        $id .= $count;
    }

    $strsearchcourses = get_string("searchcourses");

    if ($format == 'plain') {
        $output = '<form id="' . $id . '" action="' . $CFG->wwwroot . '/local/contextadmin/cat_search.php" method="get">';
        $output .= '<fieldset class="coursesearchbox invisiblefieldset">';
        $output .= '<label for="coursesearchbox">' . $strsearchcourses . ': </label>';
        $output .= '<input type="text" id="coursesearchbox" size="30" name="search" value="' . s($value) . '" />';
        $output .= '<br><label for="perpagebox">Results per page:</label>';
        $output .= '<select name="perpage">';
        foreach ($perpagevalues as $value) {
            $output .= '<option value="' . $value . '">' . $value . '</option>';
        }
        $output .= '</select>';
        $output .= '<input type="submit" value="' . get_string('go') . '" />';
        $output .= '</fieldset></form>';
    } else if ($format == 'short') {
        $output = '<form id="' . $id . '" action="' . $CFG->wwwroot . '/local/contextadmin/cat_search.php" method="get">';
        $output .= '<fieldset class="coursesearchbox invisiblefieldset">';
        $output .= '<label for="shortsearchbox">' . $strsearchcourses . ': </label>';
        $output .= '<input type="text" id="shortsearchbox" size="12" name="search" alt="' . s($strsearchcourses) . '" value="' .
            s($value) . '" />';
        $output .= '<input type="submit" value="' . get_string('go') . '" />';
        $output .= '</fieldset></form>';
    } else if ($format == 'navbar') {
        $output = '<form id="coursesearchnavbar" action="' . $CFG->wwwroot . '/local/contextadmin/cat_search.php" method="get">';
        $output .= '<fieldset class="coursesearchbox invisiblefieldset">';
        $output .= '<label for="navsearchbox">' . $strsearchcourses . ': </label>';
        $output .=
            '<input type="text" id="navsearchbox" size="20" name="search" alt="' . s($strsearchcourses) . '" value="' . s($value) .
                '" />';
        $output .= '<input type="submit" value="' . get_string('go') . '" />';
        $output .= '</fieldset></form>';
    }

    if ($return) {
        return $output;
    }
    echo $output;
}

/**
 * @param $outputobject
 * @param $id
 * @param $target
 * @param $link_title
 * @param $icon
 * @param $a_inputs
 * @return string
 */
function create_form($outputobject, $id, $target, $link_title, $icon, $a_inputs) {
    $form = "<form id=\"$id\" method=\"post\" action=\"$target\">";
    foreach ($a_inputs as $name => $value) {
        $form .= "<input type='hidden' name='$name' value='$value'/>";
    }

    $form .= "<a href=\"#\" onclick='document.getElementById(\"$id\").submit();' title=\"$link_title\">" .
        "<img src=\"" . $outputobject->pix_url("i/$icon") . "\" class=\"icon\" alt=\"$link_title\" /></a>";

    $form .= '</form>';
    return $form;
}

function create_image_tag($image, $alt, $class = '') {
    return "<img src=\"" . $image . "\" class=\"$class\" alt=\"$alt\" />";
}
