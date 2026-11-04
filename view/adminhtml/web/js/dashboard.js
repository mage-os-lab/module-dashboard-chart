/**
 * Dashboard charts app: a "Charts" tab with the admin's charts (collapsible, lazily loaded when opened)
 * and a "Personalize" tab to choose, order and open/close them (saved in admin_user.extra).
 */
define([
    'jquery',
    'ko',
    'underscore',
    'mage/translate',
    'chartjs/Chart.min',
    'MageOS_AdvancedReport/js/report/lib/format',
    'MageOS_AdvancedReport/js/report/lib/chart-config',
    'text!MageOS_DashboardChart/template/dashboard.html'
], function ($, ko, _, $t, Chart, formatter, chartConfig, template) {
    'use strict';

    var SAVE_DEBOUNCE_MS = 600,
        NOTICE_MS = 4000,
        GENERIC_ERROR = $t('The chart could not be loaded.');

    /**
     * Magento's KO bootstrap applies bindings to the whole page on domReady: keep it out of the app root,
     * which is bound with its own view model.
     */
    ko.bindingHandlers.dashboardChartIsolate = {
        /**
         * @return {Object}
         */
        init: function () {
            return {controlsDescendantBindings: true};
        }
    };

    ko.bindingHandlers.dashboardChartCanvas = {
        /**
         * @param {HTMLCanvasElement} element
         */
        init: function (element) {
            ko.utils.domNodeDisposal.addDisposeCallback(element, function () {
                if (element.chart) {
                    element.chart.destroy();
                }
            });
        },

        /**
         * @param {HTMLCanvasElement} element
         * @param {Function} valueAccessor
         */
        update: function (element, valueAccessor) {
            var entry = valueAccessor(),
                result = entry.result(),
                config = result ? chartConfig.build(entry.chart, result, entry.activeGroup(), entry.locale) : null;

            if (element.chart) {
                element.chart.destroy();
                element.chart = null;
            }
            if (config) {
                element.chart = new Chart(element.getContext('2d'), config);
            }
        }
    };

    /**
     * @param {Object} chart
     * @param {Boolean} opened
     * @param {Object} app
     * @constructor
     */
    function Entry(chart, opened, app) {
        var self = this;

        this.chart = chart;
        this.id = chart.id;
        this.locale = app.locale;
        this.opened = ko.observable(opened);
        this.loading = ko.observable(false);
        this.error = ko.observable('');
        this.result = ko.observable(null);
        this.groupIndex = ko.observable(0);
        this.loadedKey = null;
        this.xhr = null;

        this.groups = ko.pureComputed(function () {
            return chartConfig.groupMetrics(self.result());
        });
        this.activeGroup = ko.pureComputed(function () {
            var groups = self.groups();

            return groups[self.groupIndex()] || groups[0] || null;
        });
        this.isEmpty = ko.pureComputed(function () {
            var result = self.result();

            return !self.loading() && !self.error() && !!result && !result.rows.length;
        });
        this.hasChart = ko.pureComputed(function () {
            var result = self.result();

            return !!result && result.rows.length > 0;
        });
        this.rangeLabel = ko.pureComputed(function () {
            var range = self.result() && self.result().range;

            if (!range) {
                return chart.dateless ? $t('Current stock') : '';
            }

            return formatter.formatDate(range.from, app.locale) + ' – ' + formatter.formatDate(range.to, app.locale);
        });
        this.totals = ko.pureComputed(function () {
            var result = self.result(),
                group = self.activeGroup(),
                context;

            if (!result || !group || !result.rows.length) {
                return [];
            }
            context = {currencyCode: result.currency_code, locale: app.locale};

            return group.columns.filter(function (column) {
                var value = result.totals.metrics[column.code];

                return value !== null && value !== undefined && value !== '';
            }).map(function (column) {
                var comparison = result.totals.comparison && result.totals.comparison[column.code],
                    ratio = comparison ? comparison.delta_pct : null;

                return {
                    label: column.label,
                    value: formatter.format(result.totals.metrics[column.code], column.format, context),
                    delta: ratio === null || ratio === undefined ? '' : formatter.formatDeltaPct(ratio, app.locale),
                    deltaClass: comparison ? formatter.deltaClass(comparison.delta, column.lowerIsBetter) : ''
                };
            });
        });
    }

    /**
     * @param {String} key Period + scope the data was loaded for.
     * @param {Object} app
     */
    Entry.prototype.load = function (key, app) {
        var self = this;

        if (!this.opened() || this.loadedKey === key) {
            return;
        }
        if (this.xhr) {
            this.xhr.abort();
        }
        this.loadedKey = key;
        this.loading(true);
        this.error('');
        this.xhr = $.ajax({
            url: app.urls.data,
            type: 'POST',
            dataType: 'json',
            data: {
                'form_key': window.FORM_KEY,
                chart: this.id,
                period: app.period(),
                store: app.scope.store || '',
                group: app.scope.group || '',
                website: app.scope.website || ''
            }
        }).done(function (body) {
            if (body && body.ajaxExpired) {
                window.location.replace(body.ajaxRedirect);

                return;
            }
            self.result(body);
        }).fail(function (jqXhr, textStatus) {
            if (textStatus === 'abort') {
                return;
            }
            self.loadedKey = null;
            self.result(null);
            self.error(jqXhr.responseJSON && jqXhr.responseJSON.message || GENERIC_ERROR);
        }).always(function (body, textStatus) {
            if (textStatus !== 'abort') {
                self.loading(false);
            }
        });
    };

    /**
     * @param {Object} config
     * @param {HTMLElement} element
     */
    return function (config, element) {
        var charts = _.indexBy(config.charts, 'id'),
            saveTimer = null,
            noticeTimer = null,
            app = {
                $t: $t,
                urls: config.urls,
                scope: config.scope || {},
                locale: config.locale,
                periods: config.periods,
                period: ko.observable(''),
                canPersonalize: !!config.canPersonalize,
                maxOpened: Number(config.maxOpened) || 0,
                personalized: ko.observable(!!config.personalized),
                tab: ko.observable('charts'),
                entries: ko.observableArray([]),
                draft: ko.observableArray([]),
                saving: ko.observable(false),
                notice: ko.observable(''),
                noticeError: ko.observable(false)
            };

        /**
         * @return {String}
         */
        function loadKey() {
            return app.period() + '|' + JSON.stringify(app.scope);
        }

        /**
         * @param {Array} layout [{id, opened}]
         */
        function setLayout(layout) {
            var current = _.indexBy(app.entries(), 'id');

            app.entries(layout.filter(function (item) {
                return !!charts[item.id];
            }).map(function (item) {
                var entry = current[item.id] || new Entry(charts[item.id], !!item.opened, app);

                entry.opened(!!item.opened);

                return entry;
            }));
            app.entries().forEach(function (entry) {
                entry.load(loadKey(), app);
            });
        }

        /**
         * @return {Array}
         */
        function currentLayout() {
            return app.entries().map(function (entry) {
                return {id: entry.id, opened: entry.opened()};
            });
        }

        /**
         * @param {String} message
         * @param {Boolean} isError
         */
        function notify(message, isError) {
            app.notice(message);
            app.noticeError(!!isError);
            clearTimeout(noticeTimer);
            noticeTimer = setTimeout(function () {
                app.notice('');
            }, NOTICE_MS);
        }

        /**
         * @param {String} url
         * @param {Object} data
         * @param {String} successMessage
         * @param {Boolean} [keepLayout] Do not apply the saved layout (silent saves from the Charts tab: the server
         *  caps the opened charts for the next load, the charts opened now stay open).
         * @return {Object} jqXHR
         */
        function post(url, data, successMessage, keepLayout) {
            app.saving(true);

            return $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: $.extend({'form_key': window.FORM_KEY}, data)
            }).done(function (body) {
                if (body && body.ajaxExpired) {
                    window.location.replace(body.ajaxRedirect);

                    return;
                }
                app.personalized(!!body.personalized);
                if (!keepLayout) {
                    setLayout(body.layout || []);
                }
                if (successMessage) {
                    notify(successMessage, false);
                }
            }).fail(function (jqXhr) {
                var message = jqXhr.responseJSON && jqXhr.responseJSON.message;

                notify(message || $t('The dashboard could not be saved.'), true);
            }).always(function () {
                app.saving(false);
            });
        }

        /**
         * Saves the Charts tab layout (open/close, remove) a moment after the last change.
         */
        function scheduleSave() {
            if (!app.canPersonalize) {
                return;
            }
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                post(config.urls.save, {layout: JSON.stringify(currentLayout())}, '', true);
            }, SAVE_DEBOUNCE_MS);
        }

        /**
         * Personalize rows: the shown charts in dashboard order, then every other allowed chart.
         */
        function buildDraft() {
            var shown = _.indexBy(currentLayout(), 'id'),
                ordered = currentLayout().map(function (item) {
                    return charts[item.id];
                }).concat(config.charts.filter(function (chart) {
                    return !shown[chart.id];
                })),
                remaining = app.maxOpened;

            app.draft(ordered.map(function (chart) {
                var opened = !!shown[chart.id] && shown[chart.id].opened && remaining-- > 0,
                    row = {
                        chart: chart,
                        shown: ko.observable(!!shown[chart.id]),
                        opened: ko.observable(opened)
                    };

                // Showing a chart flagged opened must not exceed the limit: it is shown closed instead. Counted here,
                // not with app.openedCount, which may not be re-evaluated yet when this subscriber runs.
                row.shown.subscribe(function (isShown) {
                    var openedCount = app.draft().filter(function (candidate) {
                        return candidate.shown() && candidate.opened();
                    }).length;

                    if (isShown && row.opened() && openedCount > app.maxOpened) {
                        row.opened(false);
                    }
                });
                row.canToggleOpened = ko.pureComputed(function () {
                    return row.opened() || !row.shown() || app.openedCount() < app.maxOpened;
                });

                return row;
            }));
        }

        /** Charts both shown and opened in the Personalize draft. */
        app.openedCount = ko.pureComputed(function () {
            return app.draft().filter(function (row) {
                return row.shown() && row.opened();
            }).length;
        });

        app.showCharts = function () {
            app.tab('charts');
        };
        app.showPersonalize = function () {
            buildDraft();
            app.tab('personalize');
        };

        /**
         * @param {Entry} entry
         */
        app.toggle = function (entry) {
            entry.opened(!entry.opened());
            entry.load(loadKey(), app);
            scheduleSave();
        };

        /**
         * @param {Entry} entry
         */
        app.remove = function (entry) {
            app.entries.remove(entry);
            scheduleSave();
        };

        /**
         * @param {Entry} entry
         * @param {Number} index
         */
        app.selectGroup = function (entry, index) {
            entry.groupIndex(index);
        };

        /**
         * @param {Object} row
         * @param {Number} offset -1 up, +1 down
         */
        app.move = function (row, offset) {
            var rows = app.draft(),
                from = rows.indexOf(row),
                to = from + offset;

            if (to < 0 || to >= rows.length) {
                return;
            }
            rows.splice(from, 1);
            rows.splice(to, 0, row);
            app.draft(rows);
        };

        app.save = function () {
            var layout = app.draft().filter(function (row) {
                return row.shown();
            }).map(function (row) {
                return {id: row.chart.id, opened: row.opened()};
            });

            clearTimeout(saveTimer);
            post(config.urls.save, {layout: JSON.stringify(layout)}, $t('Your dashboard charts have been saved.'))
                .done(function () {
                    app.tab('charts');
                });
        };

        app.reset = function () {
            clearTimeout(saveTimer);
            post(config.urls.reset, {}, $t('Your dashboard charts were reset to the default ones.'))
                .done(buildDraft);
        };

        app.period.subscribe(function () {
            app.entries().forEach(function (entry) {
                entry.load(loadKey(), app);
            });
        });

        $(element).attr('data-bind', 'dashboardChartIsolate: true').html(template);
        ko.applyBindings(app, element.firstElementChild);
        setLayout(config.layout || []);
    };
});
