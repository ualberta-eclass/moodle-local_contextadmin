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
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

require_once($CFG->dirroot . '/local/contextadmin/locallib.php');

class contextadmin_locallib extends UnitTestCaseUsingDatabase
{
    /**
     * Setup function for this test suite's test cases.
     * This gets run for each test* function in this class
     */
    public function setUp() {
        parent::setUp();
        $this->switch_to_test_db();
        $this->switch_to_test_course();
        // Create copies of local contextadmin tables.
        $this->create_test_tables(array('cat_modules'), 'local/contextadmin');
        // Create copies of local contextadmin tables.
        $this->create_test_tables(array('cat_block'), 'local/contextadmin');
        // Core.
        $this->create_test_tables(array('modules', 'course_categories'), 'lib');

        // Load categories.
        $this->load_test_data('course_categories',
                              array('name', 'parent', 'sortorder', 'coursecount', 'visible', 'visibleold', 'timemodified', 'depth',
                                  'path'),
                              array(
                                  array('Cat 1', 0, 10000, 0, 1, 1, 0, 1, '/1'),
                                  array('Cat 2', 2, 20000, 0, 1, 1, 0, 2, '/1/2'),
                                  array('Cat 3', 3, 30000, 0, 1, 1, 0, 3, '/1/2/3'),
                                  array('Cat 4', 4, 40000, 0, 1, 1, 0, 4, '/1/2/3/4'),
                                  array('Cat 5', 3, 50000, 0, 1, 1, 0, 4, '/1/2/3/5')));

        // Load modules.
        $this->load_test_data('modules',
                              array('name', 'version', 'cron', 'lastcron', 'search', 'visible'),
                              array(array('assignment', 2010102600, 60, 0, '', 1),
                                  array('chat', 2010080302, 300, 0, '', 1),
                                  array('choice', 2010101301, 0, 0, '', 1),
                                  array('label', 2010080300, 0, 0, '', 1),
                                  array('forum', 2011052300, 60, 0, '', 1),
                                  array('glossary', 2010102600, 60, 0, '', 1),
                              ));
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

        $path = get_category_path(1);
        $this->assertEqual('/1', $path);
        $path = get_category_path(2);
        $this->assertEqual('/1/2', $path);
        $path = get_category_path(3);
        $this->assertEqual('/1/2/3', $path);
        $path = get_category_path(4);
        $this->assertEqual('/1/2/3/4', $path);
        $path = get_category_path(5);
        $this->assertEqual('/1/2/3/5', $path);
        $path = get_category_path(6);
        $this->assertEqual('0', $path);

    }


    public function test_get_module_settings() {
        global $COURSE;

        $this->load_test_data('cat_modules',
                              array('name', 'search', 'visible', 'category_id', 'locked', 'override'),
                              array(array('assignment', '', 1, 2, 0, 0),
                                  array('assignment', '', 0, 3, 0, 0),
                                  array('assignment', '', 1, 4, 0, 0)));

        // No setting specified for this category on this item; will use system level setting with visibility 1.
        $COURSE->category = 1;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, "1");

        $COURSE->category = 2;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, "1");

        $COURSE->category = 3;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, "0");

        $COURSE->category = 4;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, "1");

        // No setting specified for this category on this item; will use category above (3) with visability 0.
        $COURSE->category = 5;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, "0");
    }

    public function test_simple_set_and_get_module_settings() {
        global $COURSE;

        // Setup.

        // Test Set.
        $COURSE->category = 3;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 0));

        // Test Get.
        $COURSE->category = 3;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, "0");

        // Teardown.
    }

    public function test_cascade_get_and_set_module_settings() {
        global $COURSE;

        // Setup.

        // Test Set.
        $COURSE->category = 2;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 1));
        $COURSE->category = 3;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 0));
        $COURSE->category = 4;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 1));

        // Test Get.
        $COURSE->category = 2;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, "1");

        $COURSE->category = 3;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, "0");

        $COURSE->category = 4;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, "1");

        // Teardown.
    }

    public function test_cascade_override_get_and_set_module_settings() {
        global $COURSE;

        // Setup.

        // Test Set.
        $COURSE->category = 2;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 1));
        $COURSE->category = 3;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 0, 'override' => 1));
        $COURSE->category = 4;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 1));
        $COURSE->category = 5;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 1));

        // Test Get.
        $COURSE->category = 2;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, 1);

        $COURSE->category = 3;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, 0);

        // Should be overriden by category 3.
        $COURSE->category = 4;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, 0);

        // Should be overriden by category 3.
        $COURSE->category = 5;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, 0);

        // Teardown.
    }

    public function test_cascade_locked_set_module_settings() {
        global $COURSE;

        // Setup.

        // Test Set.
        $COURSE->category = 2;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 1));
        $COURSE->category = 3;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 0, 'locked' => 1));

        $COURSE->category = 2;
        $this->assertFalse(is_plugin_locked($COURSE->category, 'assignment', 'modules'));
        $COURSE->category = 3;
        $this->assertFalse(is_plugin_locked($COURSE->category, 'assignment', 'modules'));
        $COURSE->category = 4;
        $this->assertTrue(is_plugin_locked($COURSE->category, 'assignment', 'modules'));
        $COURSE->category = 5;
        $this->assertTrue(is_module_locked($COURSE->category, 'assignment'));

        // Teardown.
    }

    public function test_remove_plugin_setting() {
        global $COURSE;

        // Setup.

        // Test Set.
        $COURSE->category = 2;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 0));
        $COURSE->category = 3;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 0));

        $COURSE->category = 2;
        remove_category_plugin_values($COURSE->category, 'assignment', 'modules');
        $result = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, 1);

        $COURSE->category = 3;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, 0);

        $COURSE->category = 3;
        remove_category_plugin_values($COURSE->category, 'assignment', 'modules');
        $result = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, 1);

        // Teardown.
    }

    public function test_remove_module_setting() {
        global $COURSE;

        // Setup.

        // Test Set.
        $COURSE->category = 2;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 0));
        $COURSE->category = 3;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 0));

        $COURSE->category = 2;
        remove_category_module_values($COURSE->category, 'assignment');
        $result = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, 1);

        $COURSE->category = 3;
        $result           = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, 0);

        $COURSE->category = 3;
        remove_category_module_values($COURSE->category, 'assignment');
        $result = get_context_module_settings($COURSE->category, 'assignment');
        $this->assertEqual($result->name, 'assignment');
        $this->assertEqual($result->visible, 1);

        // Teardown.
    }

    public function test_category_module_exists() {
        global $COURSE;

        // Setup.

        // Test Set.
        $COURSE->category = 2;
        set_context_module_settings($COURSE->category, 'assignment', array('visible' => 0));

        $result = category_module_exists($COURSE->category, 'assignment', 'modules');
        $this->assertTrue($result);

        $COURSE->category = 3;
        remove_category_module_values($COURSE->category, 'assignment');
        $result = category_module_exists($COURSE->category, 'assignment', 'modules');
        $this->assertFalse($result);

        // Teardown.
    }

    /**
     * remove_category_plugin_values
     * Teardown function for this test suite's test cases.
     * This gets run for each test* function in this class
     */
    public function tearDown() {
        // Todo teardown our tables and data.
        $this->revert_to_real_course();
        $this->revert_to_real_db();
        parent::tearDown();
    }
}
