<?php
/**
 * Created by IntelliJ IDEA.
 * User: ggibeau
 * Date: 12-02-27
 * Time: 3:28 PM
 * To change this template use File | Settings | File Templates.
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot . '/local/contextadmin/locallib.php');

class contextadmin_locallib extends UnitTestCaseUsingDatabase {
    /**
     * Setup function for this test suite's test cases.
     * This gets run for each test* function in this class
     */
    function setUp(){
        parent::setUp();
        $this->switch_to_test_db();
        //create copies of local contextadmin tables
        $this->create_test_tables(array('cat_modules'),'local/contextadmin');
        //core
        $this->create_test_tables(array('modules','course_categories'),'lib');

        //load categories
        $this->load_test_data('course_categories',
            array(       'id', 'name','parent','sortorder','coursecount','visible','visibleold','timemodified','depth','path'),
            array(  array(2,   'Cat 1',0,       10000,      0,            1,        1,           0,             1,     '/2'),
                array(3,   'Cat 2',2,       20000,      0,            1,        1,           0,             2,     '/2/3'),
                array(4,   'Cat 3',3,       30000,      0,            1,        1,           0,             3,     '/2/3/4')));

        //load modules
        $this->load_test_data('modules',
            array(  'id', 'name', 'version', 'cron','lastcron','search', 'visible'),
            array(  array(1,'assignment',2010102600,60,0,'',1),
                    array(2,'chat',2010080302,300,0,'',1),
                    array(3,'choice',2010101301,0,0,'',1),
                    array(4,'label',2010080300,0,0,'',1),
                    array(5,'forum',2011052300,60,0,'',1),
                    array(6,'glossary',2010102600,60,0,'',1),
            ));

    }

    function testGetContextModuleSettings(){
        //todo do tests
    }

    function testSetContextModuleSettings(){
        //todo do tests
    }
    /**
     * Teardown function for this test suite's test cases.
     * This gets run for each test* function in this class
     */
    function tearDown(){
        //todo teardown our tables and data

        parent::tearDown();
    }
}