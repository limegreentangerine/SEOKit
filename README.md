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

## Titles, descriptions and social sharing

Core already controls the search result, so this package adds no fields for it:

- Title: the **Meta Title** attribute, otherwise the page name in the site title
  format (Dashboard > System & Settings > SEO & Statistics).
- Description: the **Meta Description** attribute, otherwise the page
  description.

### Social tags follow core's `social.opengraph` settings

Open Graph (Facebook, LinkedIn) and Twitter / X Cards both read the same site
settings, so they always agree:

| Setting                                 | Default (core)             |
| --------------------------------------- | -------------------------- |
| `social.opengraph.enabled`              | on; switches both tag sets |
| `social.opengraph.field_og_title`       | the page name              |
| `social.opengraph.field_og_description` | the page description       |
| `social.opengraph.field_og_thumbnail`   | the page `thumbnail`       |
| `social.opengraph.field_og_type`        | `website`                  |

Core has no screen for these. To share the Meta Title and Meta Description, set
them once for the site, for example from a task or `concrete c5:exec`:

```php
$config = app('site')->getSite()->getConfigRepository();
$config->save('social.opengraph.field_og_title', ['value_from' => 'page_attribute', 'attribute' => 'meta_title']);
$config->save('social.opengraph.field_og_description', ['value_from' => 'page_attribute', 'attribute' => 'meta_description']);
```

SEO Kit improves on core in three ways:

- **Open Graph fallback.** Core prints no title or description tag when the
  attribute a setting points at is empty. SEO Kit falls back to the page name or
  description, so every page gets one.
- **Default sharing image.** A page with no thumbnail of its own uses the
  site's Default Sharing Image (from `lgt_toolkit`), for Open Graph and Twitter.
- **Twitter / X Cards**, which core does not output: `twitter:card` (a large
  image card when there is an image, otherwise a summary), `twitter:title`,
  `twitter:description`, `twitter:image`, and `twitter:site` from the Twitter or
  X entry in the site's Social Links.

SEO Kit swaps core's `OpenGraph` class for its own subclass just before a page
renders, so no `header_required.php` override is needed. A site that overrides
`header_required.php` to use its own Open Graph class is unaffected.

### Search & Social Preview

Add this attribute (SEO Kit set) to a page's attributes. It shows the Google
result and the social cards, with title and description length in characters
and pixels and a list of problems, updating as you edit the page name,
description, Meta Title and Meta Description in the same form. The social cards
follow the settings above. The preview shows the saved Thumbnail, not one
picked but not yet saved. Once saved, it stays on the form for that page.

## Audit

Run the task **Audit the site for search engines** from Dashboard > System &
Settings > Automation, on a schedule there, or from the command line:

```bash
./vendor/bin/concrete task:audit-the-site-for-search-engines
```

It checks every published page using data Concrete already holds, so it makes
no web requests and is quick on large sites. Results are under Dashboard > SEO
Kit > Audit.

| Problem                                                | Severity |
| ------------------------------------------------------ | -------- |
| Schema type or custom JSON-LD that would not be output | Error    |
| Title too long (over 60 characters)                    | Warning  |
| Title shared with another page                         | Warning  |
| No description                                         | Warning  |
| Description over 160 characters                        | Warning  |
| Description shared with another page                   | Warning  |
| Noindex page that is listed in the sitemap             | Warning  |
| Title under 15 characters                              | Notice   |
| Description under 70 characters                        | Notice   |
| No sharing image (page Thumbnail or site default)      | Notice   |

The title is the Meta Title, or the page name in the site title format, as core
uses it. Pages set to noindex are skipped for the title, description and image
checks. A page scores 100 minus 20 per error, 10 per warning and 3 per notice;
the site score is the average over every page audited. The latest ten audits are
kept and shown as a trend, and the latest can be downloaded as a CSV.

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
