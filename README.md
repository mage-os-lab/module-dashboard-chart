# MageOS_DashboardChart

Admin dashboard charts built on the MageOS Advanced Report
presets, with per role defaults, a per admin layout and ACL aware chart definitions.

## Requirements

- Magento Open Source 2.4.7+ or Mage-OS, PHP 8.3, 8.4 or 8.5.
- `mage-os/module-advanced-report` (`MageOS_AdvancedReport`): the charts are its presets, the data endpoint runs its
  reports. It must provide the preset charts (`Preset::resolveChart()` and `js/report/lib/chart-config`).

## Installation

```bash
composer require mage-os/module-dashboard-chart
bin/magento module:enable MageOS_DashboardChart
bin/magento setup:upgrade
bin/magento cache:flush
```

No database table is created: the admin layouts are stored in `admin_user.extra`, the defaults in the configuration.

## Dashboard

A block above the native dashboard (`adminhtml_dashboard_index.xml`, before `dashboard`), shown when the setting below
and Advanced Report are enabled and the admin may see at least one chart, with two tabs:

- **Charts**: the admin's charts, collapsible (a closed chart sends no request until it is opened), with a period selector (chart default,
  today, last 7/30 days, month to date, last month, year to date; charts without dates, like stock, ignore it), the
  dashboard store switcher scope and totals with the comparison delta. A link to the full Advanced Report needs the
  ACL `MageOS_AdvancedReport::reports`. Charts with metrics in different formats
  (money / integer...) get a switcher: never two value axes. Line charts draw both the previous period and the
  previous year as dashed lines, with one report per comparison; the totals delta refers to the previous period.
  The chart type is not selectable here: it comes from the chart definition, which is the default of the preset unless
  `dashboard_charts.xml` overrides it.
- **Personalize** (ACL `MageOS_DashboardChart::personalize`): show/hide, opened, order; Save or Reset to Default. With
  this ACL the Charts tab also has a remove button, and opening, closing or removing a chart there is saved
  automatically.

Layout resolution (`Model\Dashboard\LayoutResolver`): the layout saved by the admin in `admin_user.extra`
(key `mageos_dashboard_charts`, other keys like `configState` are preserved), read only with the Personalize ACL, else
the configuration rows of the admin's role and of "All Roles" (first row of a chart wins). Charts the admin may not see
are always dropped.

## Configuration

Stores > Configuration > Advanced > Admin > Dashboard

| Path | Default | |
|---|---|---|
| `admin/dashboard/advanced_report_charts_enabled` | 1 | Show the charts block |
| `admin/dashboard/advanced_report_charts_max_opened` | 3 | Charts opened (and loaded) at dashboard load |
| `admin/dashboard/advanced_report_charts` | 6 "All Roles" rows, 3 opened | Rows of Admin User Role / Chart / Opened, in display order |

The rows of "Default Advanced Report Charts" are sortable: drag them by the handle or use the up / down arrows of the
Action column (`view/adminhtml/web/js/config/sortable-rows.js`, on top of the core field array template). They are
saved in the displayed order.

"Max Charts Opened at Load" is enforced everywhere:

- configuration: saving defaults that open more charts for a role (its rows + "All Roles" rows) is refused
  (`Model\Config\Backend\DashboardCharts`);
- dashboard load and saved layouts: only the first N opened charts stay opened (`LayoutResolver::capOpened()`, also
  applied by the save controller), so lowering the setting applies to the layouts already saved;
- Personalize tab: a counter, the other "Opened" switches are disabled once the limit is reached, and a chart shown
  while the limit is reached is shown closed. On the Charts tab more charts can be opened during the session; the
  saved layout is capped for the next load.

## Chart definitions

Every preset of `report_datasets.xml` (any module) **is already a chart**: id = preset code, section = preset area.
Type, metrics, dimension and sort come from the `<chart>` of the preset, so the dashboard and the Advanced Report page
draw the same default chart with the same renderer (`MageOS_AdvancedReport/js/report/lib/chart-config`). A preset
without `<chart>` falls back to its first dimension, its sort metric (or first metric), a line chart for a date
dimension and horizontal bars otherwise. The dashboard shows 10 rows by default, line charts have no limit.

`etc/dashboard_charts.xml` (schemas `dashboard_charts.xsd` merged / `dashboard_charts_file.xsd`
per file, merged across modules by `section/@id` and `chart/@id`) declares the sections and refines the presentation
of the generated charts on the dashboard:

```xml
<section id="catalog" label="Catalog" sortOrder="20">
    <acl resource="MageOS_DashboardChart::catalog"/>
</section>
<chart id="stock_value" limit="10" sortOrder="40">
    <description>Top 10 SKUs by stock value at cost and at retail price.</description>
</chart>
<!-- another chart on an existing preset -->
<chart id="stock_value_by_source" preset="stock_value" section="catalog" type="doughnut">
    <dimension code="source_code"/>
</chart>
<!-- remove a generated chart -->
<chart id="never_sold" disabled="true"/>
```

Another module adds its own section and charts with an `etc/dashboard_charts.xml` of its own, for example for the
presets it declares in its `report_datasets.xml`:

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:MageOS_DashboardChart:etc/dashboard_charts_file.xsd">
    <section id="recommendation" label="Recommendations" sortOrder="40">
        <acl resource="Vendor_Recommendation::report"/>
    </section>
    <chart id="recommendation_trend" section="recommendation" type="line" size="full" sortOrder="10">
        <description>Add to cart and revenue from recommendation sliders per day.</description>
        <metric code="revenue"/>
        <metric code="add_to_carts"/>
    </chart>
</config>
```

Labels and descriptions are translated with the module dictionaries (`i18n/*.csv`: `en_US`, `it_IT`, `fr_FR`, `de_DE`,
`es_ES`).

Section attributes: `id`, `label`, `sortOrder`, `disabled`; child `acl`.

Chart attributes: `preset` (default: id), `section` (default: preset area), `label`, `type`
(`line|bar|horizontalBar|doughnut|pie`), `size` (`half|full`), `limit`, `dateRange`, `compareTo`, `sortBy`,
`sortDirection`, `sortOrder`, `disabled`; children `description`, `acl`, `metric` and `dimension` (`dimension` also takes
`granularity`; `remove="true"` drops one inherited from another file).

`type`, `metric`, `dimension`, `compareTo` and `sortBy` still override the preset, for example on a second chart of the
same preset, but an override makes the dashboard differ from the Advanced Report page: declare the default in the
preset instead. `compareTo` has no effect on line charts, which always load both comparisons.

### ACL

| Level | Required resources |
|---|---|
| Section | its `<acl resource>` + `MageOS_DashboardChart::charts` unless `<acl inherit="false">` |
| Chart | its `<acl resource>` + the section resources unless `<acl inherit="false">` |
| Always | the ACL of the preset dataset in `report_datasets.xml`: module resource (`MageOS_AdvancedReport::sales`...) and core resource (`Magento_Sales::sales_order`, `Magento_Catalog::products`...) |

So an admin who must not see sales never gets order based charts (`sales_orders`, `sales_items` datasets: Sales
overview, Product performance, Sales by category...), while a catalog manager with Dashboard Charts > Catalog Charts,
Advanced Report > Catalog and Catalog > Products gets Stock value and Low stock with coverage. Dead stock and Never sold
also depend on the `sales_items` dataset, so they need its sales ACL too. The data endpoint
(`dashboardchart/chart/data`) builds the report request server side from the chart definition (the client only
sends the chart id, period and store scope), checks the chart access, and Advanced Report checks the dataset ACL again.

ACL tree: Dashboard > Dashboard Charts > Sales / Catalog / Customer Charts, Personalize Dashboard Charts. The presets of
the `carts` area get a "Carts" section that `dashboard_charts.xml` does not declare, so they only need
`MageOS_DashboardChart::charts` and the ACL of their dataset.

## Development

- The unit tests in `Test/Unit` run with the unit test suite of the Magento install
  (`dev/tests/unit/phpunit.xml.dist`).
- Prototype.js is loaded in the admin and adds enumerable methods to `Array.prototype`: never `$.extend()` AJAX params
  from an array (an empty PHP array serializes to `[]`).
- In the m137 admin theme (`MageOS/m137-admin-theme`) `.admin__control-checkbox` / `.admin__control-radio` inputs are `opacity: .01` and only the
  following `<label>` draws the box. `_module.less` overrides this inside `.dashboardchart` only (native control visible,
  label box hidden); the Personalize tab uses the admin Yes/No switch (`admin__actions-switch`).

## License

This module is licensed under the [MIT License](LICENSE).
