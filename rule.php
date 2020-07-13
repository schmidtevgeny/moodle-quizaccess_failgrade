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
 * Implementaton of the quizaccess_failgrade plugin.
 *
 * @package quizaccess
 * @subpackage failgrade
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * A rule controlling the number of attempts allowed.
 *
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_failgrade extends quiz_access_rule_base {
    /**
     * RULE
     */
    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->failgradeenabled)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    public function description() {
        return get_string('failgradedescription', 'quizaccess_failgrade');
    }

    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        if ($this->is_finished($numprevattempts, $lastattempt)) {
            // , unformat_float($grading_info->gradepass)
            return get_string('preventmoreattempts', 'quizaccess_failgrade');
        }

        return false;
    }

    public function is_finished($numprevattempts, $lastattempt) {
        $item = grade_item::fetch([
            'courseid' => $this->quiz->course,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $this->quiz->id,
            'outcomeid' => null
        ]);

        if ($item) {
            $grades = grade_grade::fetch_users_grades($item, [$lastattempt->userid], false);

            if (!empty($grades[$lastattempt->userid])) {
                return $grades[$lastattempt->userid]->is_passed($item);
            }
        }

        return false;
    }

    /**
     * FORM
     */
    public static function add_settings_form_fields(
        mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {

        $mform->addElement('selectyesno', 'failgradeenabled', get_string('failgradeenabled', 'quizaccess_failgrade'));

        $mform->disabledIf('failgradeenabled', 'grademethod', 'eq', QUIZ_GRADEAVERAGE);

        $mform->addHelpButton('failgradeenabled', 'failgradeenabled', 'quizaccess_failgrade');
    }

    public static function save_settings($quiz) {
        global $DB;

        if (empty($quiz->failgradeenabled) || QUIZ_GRADEAVERAGE == $quiz->grademethod) {
            $DB->delete_records('quizaccess_failgrade', ['quizid' => $quiz->id]);
        } else {
            if (!$DB->record_exists('quizaccess_failgrade', ['quizid' => $quiz->id])) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->failgradeenabled = 1;
                $DB->insert_record('quizaccess_failgrade', $record);
            }
        }
    }

    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_failgrade', ['quizid' => $quiz->id]);
    }

    public static function get_settings_sql($quizid) {
        return [
            'failgradeenabled',
            'LEFT JOIN {quizaccess_failgrade} failgrade ON failgrade.quizid = quiz.id',
            []
        ];
    }
}