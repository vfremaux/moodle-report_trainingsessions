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
 * Contain the logic for a drawer.
 *
 * @package    report_trainingsessions
 * @copyright  2019 Valery Fremaux <valery.fremaux@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log'], function($, log) {

    var trainingsessions = {

        layoutconstraints: [],

        init: function(params) {
            // Add constraint handlers to layout and outputformat
            $('#id_reportlayout').bind('change', this.filter_formats);
            $('#id_reportlayout').trigger('change');

            this.layoutconstraints = params['layoutconstraints'];

            log.debug("AMD TrainingSessions initialized");
        },

        filter_formats: function() {
            var that = $(this);

            var formatselect = $('#id_reportformat');
            var validopts = this.layoutconstraints[that.val];
            formatselect.children('option').prop('disabled', true);
            for (var opt in validopts) {
                // invalidate all options ut those who are valid.
                formatselect.children('[value="' + opt + '"]').prop('disabled', null);
            }
        }

    };

    return trainingsessions;

});
