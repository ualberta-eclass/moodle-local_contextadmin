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

// Displays external information about a course.

require_once("../../config.php");
require_once("../../course/lib.php");

global $USER;

$search  = optional_param('search', '', PARAM_RAW); // Search words.
$page    = optional_param('page', 0, PARAM_INT); // Which page to show.
$perpage = optional_param('perpage', 10, PARAM_INT); // How many per page.

// List of minimum capabilities which user need to have for editing/moving course.
$capabilities = array('moodle/course:create', 'moodle/category:manage');

// List of category id's in which current user has course:create and category:manage capability.
$usercatlist = array();

// List of parent category id's.
$catparentlist = array();

// Populate usercatlist with list of category id's with required capabilities.
$categories = get_categories();

foreach ($categories as $category) {
    $context = get_context_instance(CONTEXT_COURSECAT, $category->id);
    if (has_all_capabilities($capabilities, $context)) {
        make_categories_list($usercatlist, $catparentlist, $capabilities, 0, $category);
    }
}

$search = trim(strip_tags($search)); // Trim & clean raw searched string.
if ($search) {
    $searchterms = explode(" ", $search); // Search for words independently.
    foreach ($searchterms as $key => $searchterm) {
        if (strlen($searchterm) < 2) {
            unset($searchterms[$key]);
        }
    }
    $search = trim(implode(" ", $searchterms));
}

$site = get_site();

$urlparams = array();
foreach (array('search', 'page', 'blocklist', 'modulelist', 'edit') as $param) {
    if (!empty($$param)) {
        $urlparams[$param] = $$param;
    }
}
if ($perpage != 10) {
    $urlparams['perpage'] = $perpage;
}
$PAGE->set_url('/local/contextadmin/cat_search.php', $urlparams);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

if ($CFG->forcelogin) {
    require_login();
}


$adminediting = false;
$displaylist  = array();
$parentlist   = array();

$displaylist = $usercatlist;
$parentlist  = $catparentlist;

$strcourses        = get_string("courses");
$strsearch         = get_string("search");
$strsearchresults  = get_string("searchresults");
$strcategory       = get_string("category");
$strselect         = get_string("select");
$strselectall      = get_string("selectall");
$strdeselectall    = get_string("deselectall");
$stredit           = get_string("edit");
$strfrontpage      = get_string('frontpage', 'admin');
$strnovalidcourses = get_string('novalidcourses');

if (empty($search)) {
    $PAGE->navbar->add($strcourses, new moodle_url('/course/index.php'));
    $PAGE->navbar->add($strsearch);
    $PAGE->set_title("$site->fullname : $strsearch");
    $PAGE->set_heading($site->fullname);

    echo $OUTPUT->header();
    echo $OUTPUT->box_start();
    echo "<center>";
    echo "<br />";
    print_cat_course_search("", false, "plain");
    echo "<br /><p>";
    print_string("searchhelp");
    echo "</p>";
    echo "</center>";
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

$courses = array();

if (!empty($searchterm)) { // Do not do search for empty search request.
    $courses          = get_courses_search($searchterms, "fullname ASC",
                                           $page, $perpage, $totalcount);
    $filtered_courses = array();
    foreach ($courses as $course) {
        if ($context = context_coursecat::instance($course->category)) {
            $roles = get_roles_with_caps_in_context($context, array('mod/contextadmin:viewcategories'));
            $manager = get_role_users($roles, $context);
            if (array_key_exists($USER->id, $manager)) {
                $filtered_courses[] = $course;
            } else {
                $totalcount--;
            }
        }
    }
    $courses = $filtered_courses;
}

$searchform = '';
// Turn editing should be visible if user have system or category level capability.
if (!empty($courses) && (can_edit_in_category() || !empty($usercatlist))) {
    if ($PAGE->user_is_editing()) {
        $string = get_string("turneditingoff");
        $edit   = "off";
    } else {
        $string = get_string("turneditingon");
        $edit   = "on";
    }
    $params     = array_merge($urlparams, array('sesskey' => sesskey(), 'edit' => $edit));
    $aurl       = new moodle_url("$CFG->wwwroot/local/contextadmin/cat_search.php", $params);
    $searchform = $OUTPUT->single_button($aurl, $string, 'get');
} else {
    $searchform = print_course_search($search, true, "navbar");
}

$PAGE->navbar->add($strcourses, new moodle_url('/course/index.php'));
$PAGE->navbar->add($strsearch, new moodle_url('/local/contextadmin/cat_search.php'));
if (!empty($search)) {
    $PAGE->navbar->add(s($search));
}
$PAGE->set_title("$site->fullname : $strsearchresults");
$PAGE->set_heading($site->fullname);
$PAGE->set_button($searchform);

echo $OUTPUT->header();

$lastcategory = -1;
if ($courses) {
    echo $OUTPUT->heading("$strsearchresults: $totalcount");
    $encodedsearch = urlencode($search);

    // Add the module/block parameter to the paging bar if they exists.
    $modulelink = "";
    if (!empty($modulelist) and confirm_sesskey()) {
        $modulelink = "&amp;modulelist=" . $modulelist . "&amp;sesskey=" . sesskey();
    } else if (!empty($blocklist) and confirm_sesskey()) {
        $modulelink = "&amp;blocklist=" . $blocklist . "&amp;sesskey=" . sesskey();
    }

    print_navigation_bar($totalcount, $page, $perpage, $encodedsearch, $modulelink);

    // Show list of courses.
    if (!$adminediting) { // Not editing mode.
        foreach ($courses as $course) {
            // Front page don't belong to any category and block can exist.
            if ($course->category > 0) {
                $course->summary .= "<br /><p class=\"category\">";
                $course->summary .= "$strcategory: <a href=\"/course/category.php?id=$course->category\">";
                $course->summary .= $displaylist[$course->category];
                $course->summary .= "</a></p>";
            }
            print_course($course, $search);
            echo $OUTPUT->spacer(array('height' => 5, 'width' => 5, 'br' => true)); // Should be done with CSS instead.
        }
    }

    print_navigation_bar($totalcount, $page, $perpage, $encodedsearch, $modulelink);

} else {
    if (!empty($search)) {
        echo $OUTPUT->heading(get_string("nocoursesfound", '', s($search)));
    } else {
        echo $OUTPUT->heading($strnovalidcourses);
    }
}

echo "<br /><br />";

print_cat_course_search($search);

echo $OUTPUT->footer();

/**
 * Print a list navigation bar
 * Display page numbers, and a link for displaying all entries
 * @param integer $totalcount - number of entry to display
 * @param integer $page - page number
 * @param integer $perpage - number of entry per page
 * @param string $encodedsearch
 * @param string $modulelink - module name
 */
function print_navigation_bar($totalcount, $page, $perpage, $encodedsearch, $modulelink) {
    global $OUTPUT;

    // Display.
    if ($perpage != 99999 && $totalcount > $perpage) {
        echo $OUTPUT->paging_bar($totalcount, $page, $perpage,
                                 "cat_search.php?search=$encodedsearch" . $modulelink . "&perpage=$perpage");
        echo "<center><p>";
        echo "<a href=\"cat_search.php?search=$encodedsearch" . $modulelink . "&amp;perpage=99999\">" . get_string("showall", "",
                                                                                                                   $totalcount) .
            "</a>";
        echo "</p></center>";
    } else if ($perpage === 99999 || $perpage > $totalcount) {
        $defaultperpage = 10;
        // If user has course:create or category:manage capability the show 30 records.
        $capabilities = array('moodle/course:create', 'moodle/category:manage');
        if (has_any_capability($capabilities, context_system::instance())) {
            $defaultperpage = 30;
        }

        echo "<center><p>";
        echo"<a href=\"cat_search.php?search=$encodedsearch" . $modulelink . "&amp;perpage=" . $defaultperpage . "\">" .
            get_string("showperpage",
                       "",
                       $defaultperpage) . "</a>";
        echo "</p></center>";
    }
}


