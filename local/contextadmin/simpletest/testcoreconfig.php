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


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page.
}

require_once($CFG->dirroot . '/local/contextadmin/locallib.php');

class contextadmin_coreconfig extends UnitTestCaseUsingDatabase
{
    /**
     * Setup function for this test suite's test cases.
     * This gets run for each test* function in this class
     */
    public function setUp() {
        parent::setUp();
        $this->switch_to_test_db();
        $this->switch_to_test_course();
        // Create tables.
        $this->create_test_tables(array('cat_config', 'cat_config_plugins', 'cat_config_log', 'cat_modules'), 'local/contextadmin');
        // Core.
        $this->create_test_tables(array('config', 'config_plugins', 'course_categories'), 'lib');

        // Load categories.
        $this->load_test_data('course_categories',
                              array('name', 'parent', 'sortorder', 'coursecount', 'visible', 'visibleold', 'timemodified', 'depth',
                                  'path'),
                              array(array('Cat 1', 0, 10000, 0, 1, 1, 0, 1, '/1'),
                                  array('Cat 2', 1, 20000, 0, 1, 1, 0, 2, '/1/2'),
                                  array('Cat 3', 2, 30000, 0, 1, 1, 0, 3, '/1/2/3'),
                                  array('Cat 4', 3, 30000, 0, 1, 1, 0, 4, '/1/2/3/4'),
                                  array('Cat 5', 4, 30000, 0, 1, 1, 0, 5, '/1/2/3/4/5')));
        // Load config data.
        $this->load_test_data('config', array('name', 'value'),
                              array(array('setting1', 0),
                                  array('setting2', 0),
                                  array('setting3', 0))); // Site level config.
        // Load config_plugin data.
        $this->load_test_data('config_plugins', array('plugin', 'name', 'value'),
                              array(array('plugin1', 'setting1', 0),
                                  array('plugin1', 'setting2', 0),
                                  array('plugin1', 'setting3', 0),
                                  array('plugin2', 'setting1', 0),
                                  array('plugin2', 'setting2', 0),
                                  array('plugin2', 'setting3', 0)));

    }

    public function switch_to_test_course() {
        global $COURSE;

        $this->realcourse = clone $COURSE;
    }

    public function revert_to_real_course() {
        global $COURSE;
        if (isset($this->realcourse)) {
            $COURSE = $this->realcourse;
            unset($this->realcourse);
        }

    }

    public function test_get_category_path() {

        $path = get_category_path(2);
        $this->assertEqual('/1/2', $path);
        $path = get_category_path(3);
        $this->assertEqual('/1/2/3', $path);
        $path = get_category_path(5);
        $this->assertEqual('/1/2/3/4/5', $path);

    }

    /*********************************
     * tables: config, cat_config
     *********************************/

    // Test Set.ting config for site level, no category.
    public function test_config_no_cat() {
        global $COURSE;
        // Setup..
        // Basic setup() sufficient.
        $COURSE->category = 0;
        // Test Set..
        // Set at global level (no category).
        set_config('setting1', 1);
        set_config('setting3', 3);

        // Test Get..
        // Get from global level (no category).
        $result = get_config(null, 'setting1');
        $this->assertEqual($result, 1);
        $result = get_config(null, 'setting2');
        $this->assertEqual($result, 0);
        $result = get_config(null, 'setting3');
        $this->assertEqual($result, 3);

        // Teardown..
    }

    // Test Set.ting config for site level, no category settings, for a category.
    public function test_config_with_cat() {
        global $COURSE;
        // Setup..
        // Basic setup() sufficient.
        $COURSE->category = 3;
        // Test Set..
        // Set at global level (no category).
        set_config('setting1', 1);
        set_config('setting3', 3);

        // Test Get..
        // Get from global level (no category).
        $result = get_config(null, 'setting1');
        $this->assertEqual($result, 1);
        $result = get_config(null, 'setting2');
        $this->assertEqual($result, 0);
        $result = get_config(null, 'setting3');
        $this->assertEqual($result, 3);

        // Teardown..
    }

    // Test Set.ting config for site level, at category.
    public function test_config_cat() {
        global $COURSE;
        // Setup..

        // Test Set..
        // category set to a single level (3).
        $COURSE->category = 3;
        set_config('setting1', 4);
        set_config('setting2', 5);
        set_config('setting3', 6);

        // Test Get..
        // Getting settings from a single category level (3).
        $result = get_config(null, 'setting1');
        $this->assertEqual($result, 4);
        $result = get_config(null, 'setting2');
        $this->assertEqual($result, 5);
        $result = get_config(null, 'setting3');
        $this->assertEqual($result, 6);

        // Teardown..

    }

    // Test Set.ting config at multiple category levels.
    public function test_config_cascade() {
        global $COURSE;

        // Setup..

        // Settings should like like this array after function call to set_config.

        // Test Set..
        $COURSE->category = 3;
        set_config('setting1', 7); // Set this config at cat 3 (3rd level of category).
        $COURSE->category = 2;
        set_config('setting2', 8); // Set this config at cat 2 (2nd level of category).
        $COURSE->category = 1;
        set_config('setting3', 9); // Set this config at cat 1 (1st level of category).

        $COURSE->category = 3;
        // Test Get.
        $result = get_config(null, 'setting1'); // Get config for setting1 (set at 3rd level category).
        $this->assertEqual($result, 7);
        $result = get_config(null, 'setting2'); // Get config for setting2 (set at 2nd level category).
        $this->assertEqual($result, 8);
        $result = get_config(null, 'setting3'); // Get config for setting3 (set at 1st level category).
        $this->assertEqual($result, 9);

        // Teardown..
    }

    // Test Set.ting config at multiple category levels.
    public function test_config_cascade_with_override() {
        global $COURSE;

        // Setup..

        // Settings should like like this array after function call to set_config.

        // Test Set..
        $COURSE->category = 3;
        set_config('setting1', 7); // Set this config at cat 3 (3rd level of category).
        $COURSE->category = 2;
        set_config('setting2', 8); // Set this config at cat 2 (2nd level of category).
        $COURSE->category = 1;
        set_config('setting3', 9); // Set this config at cat 1 (1st level of category).

        $COURSE->category = 3;
        // Test Get..
        $result = get_config(null, 'setting1'); // Get config for setting1 (set at 3rd level category).
        $this->assertEqual($result, 7);
        $result = get_config(null, 'setting2'); // Get config for setting2 (set at 2nd level category).
        $this->assertEqual($result, 8);
        $result = get_config(null, 'setting3'); // Get config for setting3 (set at 1st level category).
        $this->assertEqual($result, 9);

        // Teardown..
    }

    /*********************************
     * tables: config_plugins,
     *         cat_config_plugins
     *********************************/

    // Test Set.ting plugin config at category level.
    public function test_plugin_config_no_cat() {
        global $COURSE;
        // Setup.
        // Basic setup() sufficient.
        $COURSE->category = 0;
        // Test Set..
        // Set at global level (no category).
        set_config('setting1', 1, 'plugin1');
        set_config('setting2', 2, 'plugin1');
        set_config('setting3', 3, 'plugin1');

        // Test Get..
        // Get from global level (no category).
        $result = get_config('plugin1', 'setting1');
        $this->assertEqual($result, 1);
        $result = get_config('plugin1', 'setting2');
        $this->assertEqual($result, 2);
        $result = get_config('plugin1', 'setting3');
        $this->assertEqual($result, 3);

        // Teardown..
    }

    // Test Set.ting plugin config at category level.
    public function test_plugin_config_cat() {
        global $COURSE;
        // Setup..

        // Test Set..
        // category set to a single level (3).
        $COURSE->category = 3;
        set_config('setting1', 4, 'plugin1');
        set_config('setting2', 5, 'plugin1');
        set_config('setting3', 6, 'plugin1');

        // Test Get..
        // Getting settings from a single category level (3).
        $result = get_config('plugin1', 'setting1');
        $this->assertEqual($result, 4);
        $result = get_config('plugin1', 'setting2');
        $this->assertEqual($result, 5);
        $result = get_config('plugin1', 'setting3');
        $this->assertEqual($result, 6);

        // Teardown..
    }

    // Test Set.ting plugin config at multiple category levels.
    public function test_plugin_config_cascade() {
        global $COURSE;

        // Setup..

        // Settings should like like this array after function call to set_config.

        // Test Set..
        $COURSE->category = 3;
        set_config('setting1', 7, 'plugin1'); // Set this config at cat 3 (3rd level of category).
        $COURSE->category = 2;
        set_config('setting2', 8, 'plugin1'); // Set this config at cat 2 (2nd level of category).
        $COURSE->category = 1;
        set_config('setting3', 9, 'plugin1'); // Set this config at cat 1 (1st level of category).

        // Test Get..
        $COURSE->category = 3;
        $result           = get_config('plugin1', 'setting1'); // Get config for setting1 (set at 3rd level category).
        $this->assertEqual($result, 7);
        $result = get_config('plugin1', 'setting2'); // Get config for setting2 (set at 2nd level category).
        $this->assertEqual($result, 8);
        $result = get_config('plugin1', 'setting3'); // Get config for setting3 (set at 1st level category).
        $this->assertEqual($result, 9);

        // Teardown..
    }

    // Todo add module table and block table tests.

    /**
     * Teardown function for this test suite's test cases.
     * This gets run for each test* function in this class
     */
    public function tearDown() {
        $this->drop_test_tables(array('cat_config', 'cat_config_plugins', 'cat_config_log', 'cat_modules', 'config',
                                    'config_plugins', 'course_categories'));
        $this->revert_to_real_course();

        parent::tearDown();
    }


}
