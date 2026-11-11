# Changelog

Notable changes to this project are documented here.

## 0.1.2

### Changed

- `lgt_toolkit` is now a required package. Organization name, phone, email,
  address and the default sharing image come from its site attributes.
- Empty custom JSON-LD (`{}`, `[]`, an empty graph) is now logged as invalid
  instead of being silently ignored.

## 0.1.1

### Fixed

- `/dashboard/seo_kit` now redirects to the Structured Data page.

## 0.1.0

### Added

- JSON-LD output: a single `@graph` with Organization/LocalBusiness, WebSite,
  WebPage and BreadcrumbList on public front-end pages.
- Use of `lgt_toolkit` site attributes for organization details.
- `sameAs` taken from the site's Social Links.
- Dashboard settings page for organization type, logo,
  opening hours and per-node opt-out.
- `seo_jsonld_custom` page attribute for custom JSON-LD, validated before
  output.
- `on_seo_kit_jsonld_graph` event for changing the graph.
