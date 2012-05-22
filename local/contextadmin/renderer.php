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
 * Defines the renderer for the question engine upgrade helper plugin.
 *
 * @package    local
 * @subpackage contextadmin
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Renderer for the question engine upgrade helper plugin.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_contextadmin_renderer extends plugin_renderer_base {

    /**
     * Render the index page.
     * @param string $detected information about what sort of site was detected.
     * @param array $actions list of actions to show on this page.
     * @return string html to output.
     */
    public function index_page($detected, array $actions) {
        global $DB;

        if($detected == 'System Context') {
            $output = '';
            $output .= $this->output->header();
            $output .= $this->output->heading(get_string('pluginname', 'local_contextadmin'));
            $output .= $this->output->box_start('generalbox categorybox');
            $output .= $actions[0];
            $output .= $this->output->box_end();
            //$output .= html_writer::end_tag('form');
            $output .= $this->footer();
        }
        else if($detected == 'Category Context'){
            $output = '';
            $output .= $this->header();
            $output .= $this->heading(get_string('pluginname', 'local_contextadmin'));
            $output .= 'Category';
            $output .= html_writer::end_tag('form');
            $output .= $this->footer();
        }
        return $output;
    }

    /**
     * Render a page that is just a simple message.
     * @param string $message the message to display.
     * @return string html to output.
     */
    public function simple_message_page($message) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading($message);
        $output .= $this->back_to_index();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Render a link in a div, such as the 'Back to plugin main page' link.
     * @param $url the link URL.
     * @param $text the link text.
     * @return string html to output.
     */
    public function end_of_page_link($url, $text) {
        return html_writer::tag('div', html_writer::link($url ,$text),
                array('class' => 'mdl-align'));
    }

    /**
     * Output a link back to the plugin index page.
     * @return string html to output.
     */
    public function back_to_index() {
        return $this->end_of_page_link(local_contextadmin_url('index'),
                get_string('backtoindex', 'local_contextadmin'));
    }
}
