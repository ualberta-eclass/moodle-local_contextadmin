<?php
// Allows the admin to manage activity modules

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/local/contextadmin/locallib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

// defines
define('MODULE_TABLE','module_administration_table');

//admin_externalpage_setup('managemodules');


require_login();

$contextid = required_param("contextid", PARAM_INT);
$catid = required_param("catid",PARAM_INT);

// Get the context that was passed (verify it is course category or system context).
//$context = get_context_instance(CONTEXT_COURSECAT,$catid);
$context = get_context_instance_by_id($contextid, MUST_EXIST);


//TODO: Exit cleanly...fix this later
// If we do not belong here....


// Setup Page (not admin setup)
$PAGE->set_url("/local/contextadmin/activities.php", array("contextid" => $contextid, "catid" => $catid));
$PAGE->set_category_by_id($catid);
//$PAGE->set_context($context);

$show    = optional_param('show', '', PARAM_SAFEDIR);
$hide    = optional_param('hide', '', PARAM_SAFEDIR);
$delete  = optional_param('delete', '', PARAM_SAFEDIR);
$confirm = optional_param('confirm', '', PARAM_BOOL);


/// Print headings
$stractivities = get_string("activities");
$strhide = get_string("hide");
$strshow = get_string("show");
$strsettings = get_string("settings");
$stractivities = get_string("activities");
$stractivitymodule = get_string("activitymodule");
$strshowmodulecourse = get_string('showmodulecourse');

/// If data submitted, then process and store.
if (!empty($hide) and confirm_sesskey()) {
    if (!$module = $DB->get_record("modules", array("name"=>$hide))) {
        print_error('moduledoesnotexist', 'error');
    }
    else {
        set_context_module_settings($catid,$hide,array('visible'=>0, 'search'=>''));
    }
}

if (!empty($show) and confirm_sesskey()) {
    if (!$module = $DB->get_record("modules", array("name"=>$show))) {
        print_error('moduledoesnotexist', 'error');
    }
    else {
        set_context_module_settings($catid,$show,array('visible'=>1, 'search'=>''));
    }
}

// Category is our primary source of context.  This is important.
//$PAGE->set_category_by_id($catid);
$category = $PAGE->category;
$site = get_site();
$PAGE->set_title("$site->shortname: $category->name");
$PAGE->set_heading($site->fullname);
$PAGE->set_pagelayout('coursecategory');
echo $OUTPUT->header();
echo $OUTPUT->heading($category->name.': '.$stractivities);

// Get and sort the existing modules
// Modules are retrieved from main mdl_modules table and NOT mdl_cat_modules since at most
// the mdl_cat_modules is a subset of modules that exist in mdl_modules.
if (!$modules = $DB->get_records('modules', array(), 'name ASC')) {
    print_error('moduledoesnotexist', 'error');
}

/// Print the table of all modules
// construct the flexible table ready to display
$table = new flexible_table(MODULE_TABLE);
// User can edit settings for modules within this category
if(has_capability('mod/contextadmin:editowncatsettings', $context)) {
    $table->define_columns(array('name', 'hideshow',' settings'));
    $table->define_headers(array($stractivitymodule, "$strhide/$strshow", $strsettings));
}
// User can not edit settings for modules but can hide/show
else if(has_capability('mod/contextadmin:changevisibilty', $context)){
    $table->define_columns(array('name', 'hideshow'));
    $table->define_headers(array($stractivitymodule, "$strhide/$strshow"));
}
else {
    $table->define_columns(array('name'));
    $table->define_columns(array($stractivitymodule));
}


$table->define_baseurl($CFG->wwwroot.'/'.$CFG->admin.'/modules.php');
$table->set_attribute('id', 'modules');
$table->set_attribute('class', 'generaltable');
$table->setup();

foreach ($modules as $module) {
    // TODO: make a more efficient way to grab initial category modules instead of site level then overriding
    $module = get_context_module_settings($catid,$module->name);
    if (!file_exists("$CFG->dirroot/mod/$module->name/lib.php")) {
        $strmodulename = '<span class="notifyproblem">'.$module->name.' ('.get_string('missingfromdisk').')</span>';
        $missing = true;
    } else {
        // took out hspace="\10\", because it does not validate. don't know what to replace with.
        $icon = "<img src=\"" . $OUTPUT->pix_url('icon', $module->name) . "\" class=\"icon\" alt=\"\" />";
        $strmodulename = $icon.' '.get_string('modulename', $module->name);
        $missing = false;
    }

    if (file_exists("$CFG->dirroot/local/contextadmin/mod/$module->name/cat_settings.php") && has_capability('mod/contextadmin:editowncatsettings', $context)) {
        $settings = "<a href=\"cat_settings.php?section=modsetting$module->name&name=$module->name&contextid=$contextid\">$strsettings</a>";
    } else {
        $settings = "";
    }

    // If we can hide/show then create the icons/links
    if(has_capability('mod/contextadmin:changevisibilty', $context)) {
        if ($missing) {
            $visible = '';
            $class   = '';
        } else if ($module->visible) {
            $visible = "<a href=\"activities.php?contextid=$contextid&catid=$catid&hide=$module->name&amp;sesskey=".sesskey()."\" title=\"$strhide\">".
                "<img src=\"" . $OUTPUT->pix_url('i/hide') . "\" class=\"icon\" alt=\"$strhide\" /></a>";
            $class   = '';
        } else {
            $visible = "<a href=\"activities.php?contextid=$contextid&catid=$catid&show=$module->name&amp;sesskey=".sesskey()."\" title=\"$strshow\">".
                "<img src=\"" . $OUTPUT->pix_url('i/show') . "\" class=\"icon\" alt=\"$strshow\" /></a>";
            $class =   ' class="dimmed_text"';
        }
        if ($module->name == "forum") {
            $delete = "";
            $visible = "";
            $class = "";
        }
    }

    $tabledata = array('<span'.$class.'>'.$strmodulename.'</span>');

    if(!empty($visible) || $module->name == "forum") {
        $tabledata[] = $visible;
    }
    if(!empty($settings)) {
        $tabledata[] = $settings;
    }
    // User can not edit settings for modules but can hide/show
    $table->add_data($tabledata);
}

$table->print_html();

echo $OUTPUT->footer();