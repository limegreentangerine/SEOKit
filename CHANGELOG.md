# Changelog

Notable changes to this project are documented here.

## 0.2.0

### Added

- `seo_schema` page attribute type (SEO Kit set): choose a schema type and fill
  in its fields in the page attributes form instead of writing JSON. Seven
  types: Article / Blog post, FAQ, Product, Event, Person, Local business
  (branch or location page) and How-to.
- Fields that Concrete already knows (name, description, dates) fill in from the
  page and can be overridden.
- The form warns, as you type, which required fields are still empty. Concrete's
  page attributes panel does not validate attributes, so the warning does not
  block saving: a type with a required field missing is not output, and the
  reason is logged.
- The chosen type's node is added to the page's `@graph`, linked to the WebPage
  and Organization nodes. Custom JSON-LD can still replace it by `@id`.

### Changed

- PHPUnit now uses `tests/bootstrap.php`, which stubs Concrete's `t()` and `h()`.

## 0.1.4

### Added

- `seo_robots` page attribute (SEO Kit set): `nofollow`, `noarchive`,
  `nosnippet` and `noimageindex`, output as a robots meta tag. `noindex` stays
  with core's "Exclude From Search Index" attribute, and the canonical tag stays
  with core.

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
