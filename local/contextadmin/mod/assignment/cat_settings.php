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

require_once($CFG->dirroot.'/mod/assignment/lib.php');

if (isset($CFG->maxbytes)) {
    $settings->add(new admin_setting_configselect('assignment_maxbytes', get_string('maximumsize', 'assignment'),
                                                  get_string('configmaxbytes', 'assignment'), 1048576, get_max_upload_sizes($CFG->maxbytes)));
}

$options = array(ASSIGNMENT_COUNT_WORDS   => trim(get_string('numwords', '', '?')),
                 ASSIGNMENT_COUNT_LETTERS => trim(get_string('numletters', '', '?')));
$settings->add(new admin_setting_configselect('assignment_itemstocount', get_string('itemstocount', 'assignment'),
                                              get_string('configitemstocount', 'assignment'), ASSIGNMENT_COUNT_WORDS, $options));

$settings->add(new admin_setting_configcheckbox('assignment_showrecentsubmissions', get_string('showrecentsubmissions', 'assignment'),
                                                get_string('configshowrecentsubmissions', 'assignment'),0));
