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

### Custom JSON-LD per page

Set the **Custom JSON-LD** page attribute (SEO Kit set) to a JSON object, a
list of objects, or an object with an `@graph` list. Any nested `@context` is
dropped. A custom node with the same `@id` as a generated node replaces it;
other nodes are appended. Invalid JSON is logged and skipped, never output.

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
