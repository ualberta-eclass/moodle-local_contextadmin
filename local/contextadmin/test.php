<?php
/**
 * Created by IntelliJ IDEA.
 * User: tdjones
 * Date: 12-02-02
 * Time: 4:24 PM
 * To change this template use File | Settings | File Templates.
 */

define("CONTEXTADMINDEBUG",true);
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));


$cat = 3;
echo "Checking Path for cateogory $cat\n";

$ret_mod = get_context_modules($cat);
echo "Module Result:\n";
print_r($ret_mod);
$ret_configs = get_context_config($cat);
echo "Global Context Result:\n";
print_r($ret_configs);
$ret_configs_plugin_quiz = get_context_config($cat,"quiz");
echo "Quiz Context Result:\n";
print_r($ret_configs_plugin_quiz);
$ret_configs_plugin_quiz_attempts = get_context_config_field($cat,"attempts","quiz");
echo "Quiz attempts Context Result:\n";
print_r($ret_configs_plugin_quiz_attempts);

echo "\nDo you have context admin category view capability?\n";
echo has_category_view_capability($USER->id)? "YES":"NO";

