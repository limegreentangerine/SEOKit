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

## What is in the package

Dashboard pages (Dashboard > SEO Kit):

| Page            | What it does                                           |
| --------------- | ------------------------------------------------------ |
| Structured Data | Organization details for the JSON-LD graph             |
| Audit           | Site SEO score, problems by page, trend, CSV           |
| Sitemap         | sitemap.xml status and a bulk editor for what it lists |
| AI Crawlers     | Block AI bots in robots.txt, publish llms.txt          |
| 404 Monitor     | Missing addresses, with a button to redirect them      |

Page attributes (the **SEO Kit** attribute set):

| Attribute                | Handle               | Purpose                                       |
| ------------------------ | -------------------- | --------------------------------------------- |
| Schema                   | `seo_schema`         | A schema type for the page, with its fields   |
| Custom JSON-LD           | `seo_jsonld_custom`  | Your own JSON-LD for the page                 |
| Robots Directives        | `seo_robots`         | nofollow, noarchive, nosnippet, noimageindex  |
| Search & Social Preview  | `seo_preview`        | Preview of the search result and social cards |
| Hide from llms.txt       | `seo_llms_exclude`   | Keeps the page out of llms.txt                |
| Sitemap Priority         | `sitemap_priority`   | Read by core when it writes sitemap.xml       |
| Sitemap Change Frequency | `sitemap_changefreq` | Read by core when it writes sitemap.xml       |

Tasks (Dashboard > System & Settings > Automation): **Audit the site for search
engines**.

## JSON-LD

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

For adding JSON-LD from a single page controller, see
[docs/single-page-json-ld.md](docs/single-page-json-ld.md).

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

For setting titles, descriptions and extra meta tags from a single page
controller, see [docs/single-page-meta-tags.md](docs/single-page-meta-tags.md).
A single page that shows one record per URL sets `pageTitle`,
`pageDescription`, `pageImage` and `ogUrl` so the JSON-LD and social tags
describe the record, not the parent page.

### Search & Social Preview

Add this attribute (SEO Kit set) to a page's attributes. It shows the Google
result and the social cards, with title and description length in characters
and pixels and a list of problems, updating as you edit the page name,
description, Meta Title and Meta Description in the same form. The social cards
follow the settings above. The preview shows the saved Thumbnail, not one
picked but not yet saved. Once saved, it stays on the form for that page.

## Audit

Press **Run audit** on Dashboard > SEO Kit > Audit (it runs in the request, so on a
very large site use the task instead), or run the task **Audit the site for search
engines** from Dashboard > System & Settings > Automation, on a schedule there, or
from the command line:

```bash
./vendor/bin/concrete task:audit-the-site-for-search-engines
```

It checks every published page using data Concrete already holds, so it makes
no web requests and is quick on large sites. Results are under Dashboard > SEO
Kit > Audit. **Download CSV** names the file after the site's host, for example
`seo-audit-example.com-2026-11-12.csv`.

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

## Sitemap

Core's **Generate Sitemap** task writes `sitemap.xml`, so this package does not
write a second one. Dashboard > SEO Kit > Sitemap shows whether the file exists,
how many pages it lists and when it was generated, and lets you leave pages out
(core's `exclude_sitemapxml`) and set `sitemap_priority` (0.0 to 1.0) and
`sitemap_changefreq`, 50 pages at a time (change it with **Pages per screen**,
which applies to the whole site). Run the task from Automation afterwards to update the
file; empty values use the site defaults.

## AI crawlers

Dashboard > SEO Kit > AI Crawlers.

**robots.txt.** Until you save, the page shows defaults: the eight training and
bulk-collection bots blocked (OpenAI and Anthropic training, Google-Extended,
Apple, Common Crawl, Meta, ByteDance, Amazon) and rules keeping crawlers out of
Concrete's system folders. Tick the bots to block. Saving writes one block between
`# BEGIN SEO Kit` and `# END SEO Kit` in the web root's `robots.txt`, creating the
file if there is none (it then holds only this block, so keep the system-folder rules
under **Other rules**), and never changes anything outside the block. If the file
is not writable or the markers are damaged, nothing is written and the page shows
the text to paste. Rules typed under **Other rules** that have no `User-agent` line
are given `User-agent: *`, so they cannot attach to a bot above them.

robots.txt is a request, not enforcement. A CDN such as Cloudflare has its own bot
settings that can block a bot this page allows, and a managed robots.txt there may
add rules of its own. SEO Kit cannot see those settings.

**llms.txt.** Tick **Publish llms.txt** to serve `/llms.txt`: the site name, your
introduction and up to 200 pages (the ones core would put in the sitemap, minus
noindex pages and pages with **Hide from llms.txt** ticked). A physical `llms.txt` in
the web root is served instead; the page warns when there is one.

## 404 monitor

Dashboard > SEO Kit > 404 Monitor lists addresses visitors asked for that do not
exist, most requested first, with when they were last requested and which site
linked to them. It stores only the cleaned path, a hit count, two dates and the
referrer's host name. Entries older than 90 days are dropped.

Press **Redirect** on a row, choose the page it should go to and a 301 or 302, and
the redirect is created through the redirection tool in LGT Toolkit. That tool must
be present: it is on the toolkit's `feature/redirection-tool` branch and not in a
release yet. Without it the list works and redirects are switched off. Requests the
toolkit already redirects never reach the list.

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
