# Changelog

All notable changes to this module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this module adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- "Returns" dashboard section (sortOrder 50) with descriptions, sizes and limits for the 13 return presets of
  `MageOS_AdvancedReport`; the charts exist only while `MageOS_RMA` is enabled.
- `en_US`, `it_IT`, `de_DE`, `fr_FR` and `es_ES` translations of the returns section and chart descriptions.

## 1.0.0 - 2026-11-04

### Added

- Admin dashboard block above the native dashboard, with the Charts and Personalize tabs.
- Collapsible charts: a closed chart sends no request until it is opened.
- Period selector, store scope, totals with comparison delta and link to the full Advanced Report.
- Metric format switcher for charts with metrics in different formats.
- Line charts with previous period and previous year comparisons.
- Per admin layout stored in `admin_user.extra`, with show/hide, opened state, order and Reset to Default.
- Per role default charts in Stores > Configuration > Advanced > Admin > Dashboard, with sortable rows.
- "Max Charts Opened at Load" setting, enforced on configuration save, dashboard load, saved layouts and the Personalize tab.
- Charts generated from the `report_datasets.xml` presets of `MageOS_AdvancedReport`.
- `etc/dashboard_charts.xml` to declare sections and refine, add or disable charts, merged across modules.
- ACL at section and chart level, on top of the ACL of the preset dataset.
- Server side report request built from the chart definition in the `dashboardchart/chart/data` endpoint.
- `en_US`, `it_IT`, `fr_FR`, `de_DE` and `es_ES` translations.
