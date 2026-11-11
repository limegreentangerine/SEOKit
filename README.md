# SEO Kit

Structured data and SEO tools for Concrete CMS. Installed per site, released
independently of `lgt_toolkit`.

- Handle: `seo_kit`
- Requires PHP 8.4, Concrete CMS 9.5 and the `class_kit` and `lgt_toolkit`
  packages.
- The Organization node reads the toolkit's site attributes
  (`site_company_name`, `site_company_phone`, `site_company_email`,
  `site_company_address`, `opengraph_default_image`). Fill them in under
  Dashboard > System & Settings > Site Attributes.

## Phase 1: JSON-LD

On public front-end pages the package adds one
`<script type="application/ld+json">` block to the page head containing a
single `@graph`:

- `Organization` or `LocalBusiness`
- `WebSite`
- `WebPage`
- `BreadcrumbList` (omitted on the home page)

It is not output in the dashboard, edit mode, system pages, error pages or
XHR/JSON requests.

### Settings

Dashboard > SEO Kit > Structured Data (per site): organization type, logo,
opening hours and a list of nodes to switch off if your theme already outputs
them. `sameAs` is built from the site's Social Links (Dashboard > System &
Settings > Social Links).

### Schema types per page

Set the **Schema** page attribute (SEO Kit set) to add a node for what the page
is about. Pick a type, then fill in its fields:

- Article / Blog post / News article
- FAQ
- Product
- Event
- Person
- Local business (a branch or location page, linked to the site's organization)
- How-to

Name, description and dates default from the page, so most fields are optional.
Fields marked with a red asterisk are required: the form warns while one is
empty, but Concrete lets the page be saved anyway. A type with a required field
missing is not output, and the reason is written to the Concrete log. The node
is linked to the page's WebPage and the site's Organization.

### Custom JSON-LD per page

Set the **Custom JSON-LD** page attribute (SEO Kit set) to a JSON object, a
list of objects, or an object with an `@graph` list. Any nested `@context` is
dropped. A custom node with the same `@id` as a generated node replaces it;
other nodes are appended. Invalid JSON is logged and skipped, never output.

## Robots directives

The **Robots Directives** page attribute (SEO Kit set) adds a robots meta tag
for `nofollow`, `noarchive`, `nosnippet` and `noimageindex`.

Core already handles two related things, so this package does not:

- `noindex`: tick core's **Exclude From Search Index** attribute. If that and
  a directive here are both set, the page gets two robots meta tags, which
  search engines combine.
- The canonical tag: core outputs it (Dashboard > System & Settings > SEO &
  Statistics). Page titles and descriptions come from core's `meta_title` and
  `meta_description`, and sitemap exclusion from `exclude_sitemapxml`.

### Extending

Listen to `on_seo_kit_jsonld_graph` and change the graph:

```php
Events::addListener('on_seo_kit_jsonld_graph', function ($event) {
    $graph = $event->getArgument('graph');
    // ...modify $graph...
    $event->setArgument('graph', $graph);
});
```

## Development

```bash
composer install
composer test
composer format:check
```

The version is kept in `controller.php`, `package.json` and `CHANGELOG.md`.
