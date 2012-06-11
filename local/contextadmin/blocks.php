<?php

// Allows the category manager to configure blocks (hide/show)
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/contextadmin/locallib.php');
require_once($CFG->libdir.'/tablelib.php');

require_login();

$contextid = required_param("contextid", PARAM_INT);
$catid = required_param("catid",PARAM_INT);
$confirm  = optional_param('confirm', 0, PARAM_BOOL);
$hide     = optional_param('hide', 0, PARAM_INT);
$show     = optional_param('show', 0, PARAM_INT);

// Get the context that was passed (verify it is in a context beneath or equal to category
$context = get_context_instance_by_id($contextid, MUST_EXIST);
//TODO: Exit cleanly...fix this later, check valid context

// Setup Page (not admin setup)
$PAGE->set_url("/local/contextadmin/blocks.php", array("contextid" => $contextid, "catid" => $catid));
$PAGE->set_category_by_id($catid);

/// Print headings
$strmanageblocks = get_string('manageblocks');
$strhide = get_string('hide');
$strshow = get_string('show');
$strsettings = get_string('settings');
$strname = get_string('name');
$strshowblockcourse = get_string('showblockcourse');

/// If data submitted, then process and store.
if (!empty($hide) && confirm_sesskey()) {
    if (!$block = $DB->get_record('block', array('id'=>$hide))) {
        print_error('blockdoesnotexist', 'error');
    }
    else {
        set_context_block_settings($catid,$block->name,array('visible'=>'0'));
    }
}

if (!empty($show) && confirm_sesskey() ) {
    if (!$block = $DB->get_record('block', array('id'=>$show))) {
        print_error('blockdoesnotexist', 'error');
    }
    else {
        set_context_block_settings($catid,$block->name,array('visible'=>'1'));
    }
}

// Setup the PAGE object
$category = $PAGE->category;
$site = get_site();
$PAGE->set_title("$site->shortname: $category->name");
$PAGE->set_heading($site->fullname);
$PAGE->set_pagelayout('coursecategory');

echo $OUTPUT->header();
echo $OUTPUT->heading($category->name.': '.$strmanageblocks);

/// Main display starts here
/// Get and sort the existing blocks
if (!$blocks = $DB->get_records('block', array(), 'name ASC')) {
    print_error('noblocks', 'error');  // Should never happen
}

$incompatible = array();

/// Print the table of all blocks
$table = new flexible_table('admin-blocks-compatible');
//TODO: tying the capability to hide/show blocks to the same one for hide/show modules. Might need it's own in the future.
if(has_capability('mod/contextadmin:editowncatsettings', $context)) {
    $table->define_columns(array('name', 'hideshow', 'settings'));
    $table->define_headers(array($strname, $strhide.'/'.$strshow, $strsettings));
}
// User can not edit settings for modules but can hide/show
else if(has_capability('mod/contextadmin:changevisibilty', $context)){
    $table->define_columns(array('name', 'hideshow'));
    $table->define_headers(array($strname, $strhide.'/'.$strshow));
}
else {
    $table->define_columns(array('name'));
    $table->define_headers(array($strname));
}

$table->define_baseurl($CFG->wwwroot.'/'.$CFG->admin.'/blocks.php');
$table->set_attribute('class', 'compatibleblockstable blockstable generaltable');
$table->setup();
$tablerows = array();

foreach ($blocks as $blockid=>$blockraw) {
    // Get current block settings (hidden/shown, etc..) based off category table cascaded.
    $block = get_context_block_settings($catid,$blockraw->name);
    $blockname = $block->name;

    if (!file_exists("$CFG->dirroot/blocks/$blockname/block_$blockname.php")) {
        $blockobject  = false;
        $strblockname = '<span class="notifyproblem">'.$blockname.' ('.get_string('missingfromdisk').')</span>';
        $plugin = new stdClass();
    } else {
        $plugin = new stdClass();

        if (file_exists("$CFG->dirroot/blocks/$blockname/version.php")) {
            include("$CFG->dirroot/blocks/$blockname/version.php");
        }

        if (!$blockobject  = block_instance($block->name)) {
            $incompatible[] = $block;
            continue;
        }
        $strblockname = get_string('pluginname', 'block_'.$blockname);
    }

    $settings = ''; // By default, no configuration
    //TODO: add this later for site admins  or those with the capability to edit settings
//    if ($blockobject and $blockobject->has_config()) {
//        $blocksettings = admin_get_root()->locate('blocksetting' . $block->name);
//
//        if ($blocksettings instanceof admin_externalpage) {
//            $settings = '<a href="' . $blocksettings->url .  '">' . get_string('settings') . '</a>';
//        } else if ($blocksettings instanceof admin_settingpage) {
//            $settings = '<a href="'.$CFG->wwwroot.'/'.$CFG->admin.'/settings.php?section=blocksetting'.$block->name.'">'.$strsettings.'</a>';
//        } else {
//            $settings = '<a href="block.php?block='.$blockid.'">'.$strsettings.'</a>';
//        }
//    }

    // MDL-11167, blocks can be placed on mymoodle, or the blogs page
    // and it should not show up on course search page

//    $totalcount = $DB->count_records('block_instances', array('blockname'=>$blockname));
//    $count = $DB->count_records('block_instances', array('blockname'=>$blockname, 'pagetypepattern'=>'course-view-*'));
//
//    if ($count>0) {
//        $blocklist = "<a href=\"{$CFG->wwwroot}/course/search.php?blocklist=$blockid&amp;sesskey=".sesskey()."\" ";
//        $blocklist .= "title=\"$strshowblockcourse\" >$totalcount</a>";
//    }
//    else {
//        $blocklist = "$totalcount";
//    }
    $class = ''; // Nothing fancy, by default

    if (!$blockobject || !has_capability('mod/contextadmin:changevisibilty', $context)) {
        // ignore
        $visible = '';
    } else if ($block->visible) {
        $visible = '<a href="blocks.php?contextid='.$contextid.'&catid='.$catid.'&hide='.$blockid.'&amp;sesskey='.sesskey().'" title="'.$strhide.'">'.
            '<img src="'.$OUTPUT->pix_url('i/hide') . '" class="icon" alt="'.$strhide.'" /></a>';
    } else {
        $visible = '<a href="blocks.php?contextid='.$contextid.'&catid='.$catid.'&show='.$blockid.'&amp;sesskey='.sesskey().'" title="'.$strshow.'">'.
            '<img src="'.$OUTPUT->pix_url('i/show') . '" class="icon" alt="'.$strshow.'" /></a>';
        $class = ' class="dimmed_text"'; // Leading space required!
    }

    $row =  array('<span'.$class.'>'.$strblockname.'</span>');
    if(!empty($visible)) {
        $row[] = $visible;
    }
    if(!empty($settings)) {
        $row[] = $settings;
    }

    $tablerows[] = array(strip_tags($strblockname), $row); // first element will be used for sorting
}

collatorlib::asort($tablerows);
foreach ($tablerows as $row) {
    $table->add_data($row[1]);
}

//TODO: Deprecated function
$table->print_html();
echo $OUTPUT->footer();