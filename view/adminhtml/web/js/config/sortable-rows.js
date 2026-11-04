/**
 * Sortable rows for a configuration field array (Magento_Config::system/config/form/field/array.phtml).
 *
 * The rows are saved in DOM order, so sorting only moves the <tr>: a drag handle plus "Move up" / "Move down"
 * buttons are added to the actions cell of every row, including the rows added later with the "Add" button.
 */
define([
    'jquery',
    'mage/translate',
    'jquery/ui-modules/widgets/sortable'
], function ($, $t) {
    'use strict';

    var ENHANCED = 'data-dashboardchart-sortable';

    /**
     * @param {jQuery} $row
     * @return {Boolean} The field uses the inherited/system value: its controls are disabled.
     */
    function isLocked($row) {
        return $row.find('select:disabled, input:disabled').length > 0;
    }

    /**
     * @param {String} className
     * @param {String} label
     * @param {String} text
     * @return {jQuery}
     */
    function button(className, label, text) {
        return $('<button type="button"></button>')
            .addClass('action-default ' + className)
            .attr({title: label, 'aria-label': label})
            .text(text);
    }

    /**
     * @param {jQuery} $tbody
     */
    function refresh($tbody) {
        var $rows = $tbody.children('tr');

        $rows.each(function (index) {
            var $row = $(this),
                locked = isLocked($row);

            $row.find('.dashboardchart-sort__handle').toggleClass('_locked', locked);
            $row.find('.dashboardchart-sort__up').prop('disabled', locked || index === 0);
            $row.find('.dashboardchart-sort__down').prop('disabled', locked || index === $rows.length - 1);
        });
    }

    /**
     * @param {jQuery} $row
     */
    function enhance($row) {
        var $actions = $row.children('td.col-actions');

        if ($row.attr(ENHANCED) || !$actions.length) {
            return;
        }
        $row.attr(ENHANCED, '1');
        $actions.addClass('dashboardchart-sort').prepend(
            $('<span class="dashboardchart-sort__handle" aria-hidden="true">&#8942;&#8942;</span>')
                .attr('title', $t('Drag to sort')),
            button('dashboardchart-sort__up', $t('Move up'), '↑'),
            button('dashboardchart-sort__down', $t('Move down'), '↓')
        );
    }

    /**
     * @param {Object} config {tableId}
     */
    return function (config) {
        var $tbody = $(document.getElementById(config.tableId)).children('tbody').first(),
            observer;

        if (!$tbody.length) {
            return;
        }

        /** Enhances the rows already rendered and the new ones. */
        function update() {
            $tbody.children('tr').each(function () {
                enhance($(this));
            });
            refresh($tbody);
        }

        $tbody.on('click', '.dashboardchart-sort__up, .dashboardchart-sort__down', function () {
            var $row = $(this).closest('tr');

            if (isLocked($row)) {
                return;
            }
            if ($(this).hasClass('dashboardchart-sort__up')) {
                $row.insertBefore($row.prev('tr'));
            } else {
                $row.insertAfter($row.next('tr'));
            }
            refresh($tbody);
            $(this).trigger('focus');
        });

        $tbody.sortable({
            items: '> tr',
            handle: '.dashboardchart-sort__handle',
            axis: 'y',
            tolerance: 'pointer',
            cursor: 'move',
            cancel: 'input, textarea, button, select, option, .dashboardchart-sort__handle._locked',
            helper: function (event, $row) {
                // Keeps the cell widths while dragging.
                $row.children().each(function () {
                    $(this).width($(this).width());
                });

                return $row;
            },
            start: function (event, ui) {
                ui.placeholder.height(ui.item.outerHeight());
            },
            stop: function (event, ui) {
                ui.item.children().css('width', '');
                refresh($tbody);
            }
        });

        observer = new MutationObserver(update);
        observer.observe($tbody.get(0), {childList: true});
        // "Use system value" toggles the controls without changing the rows.
        $tbody.closest('tr[id^="row_"]').on('change', 'input[type="checkbox"]', function () {
            refresh($tbody);
        });
        update();
    };
});
