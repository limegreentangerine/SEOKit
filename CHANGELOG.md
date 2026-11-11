# Changelog

Notable changes to this project are documented here.

## 0.7.0

### Added

- Dashboard > SEO Kit > AI Crawlers:
    - Tick the AI bots to block from the whole site, grouped by what they do:
      training, AI search, and fetching a page for a person. Fourteen bots,
      including GPTBot, ClaudeBot, Google-Extended, PerplexityBot and CCBot.
    - Extra robots.txt rules, and a Sitemap line when `sitemap.xml` exists.
    - A preview of the resulting robots.txt and of llms.txt.
    - Notices that robots.txt only asks crawlers to behave, and that a CDN such
      as Cloudflare has bot settings of its own.
- robots.txt is edited in place, not served by the package, because sites ship
  with a physical file that the web server serves before Concrete runs. Saving
  writes one block between `# BEGIN SEO Kit` and `# END SEO Kit` and keeps every
  other byte of the file. A missing file is created with a rule that keeps
  crawlers out of the dashboard. A file that cannot be written, or whose markers
  are damaged, is left alone and the dashboard shows what to paste.
- `/llms.txt` (off until ticked): the site name, an introduction you write, and
  the pages core would put in the sitemap, up to 200, with their descriptions.
  Noindex pages and pages with the new **Hide from llms.txt** attribute
  (`seo_llms_exclude`, SEO Kit set) are left out.

## 0.6.0

### Added

- Dashboard > SEO Kit > Sitemap: whether `sitemap.xml` exists, how many pages it
  lists, when it was last generated, a warning when the canonical URL is not
  set, and a table to leave pages out and set their priority and change
  frequency, with search. It lists 50 pages at a time; a "Pages per screen"
  setting (10, 25, 50 or 100) changes that for the site. Values are validated, and only
  changed values for pages you may edit are written.
- `sitemap_priority` and `sitemap_changefreq` page attributes (SEO Kit set).
  Core reads them when it writes the sitemap but never creates them.

## 0.5.0

### Changed

- Twitter / X Cards now take their title, description and image from core's
  `social.opengraph` site settings (`field_og_title`, `field_og_description`,
  `field_og_thumbnail`), the same ones Open Graph uses, so the two always agree.
  Before, they read the Meta Title and Meta Description directly. With core's
  default settings they now use the page name and description. To share the
  meta fields, point those settings at them (see the README).
- The Search & Social Preview follows the same settings, and shows one set of
  social text for the Open Graph and X cards.

### Added

- Open Graph tags are produced by a SEO Kit subclass of core's `OpenGraph`, in
  place of core's. The tags and their order are core's, with two fixes: an
  empty source attribute falls back to the page name or description instead of
  dropping the tag, and a page with no image uses the site's Default Sharing
  Image. It is swapped in just before a page renders, because core registers its
  own class after packages start. On any error core's tags are used.
- `twitter:site`, from the Twitter or X entry in Social Links.
- Twitter Cards honour `social.opengraph.enabled`, switching off with Open
  Graph.

## 0.4.0

### Added

- SEO audit. The task "Audit the site for search engines" (Dashboard > System
  & Settings > Automation, or `task:audit-the-site-for-search-engines` on the
  command line) checks every published page using data Concrete already holds,
  with no crawling:
    - title length, and titles shared by more than one page;
    - missing, short, long and shared descriptions;
    - noindex pages that are still in the sitemap;
    - pages with no sharing image;
    - Schema types and custom JSON-LD that would not be output.
      Pages marked noindex are skipped for the title, description and image
      checks.
- Dashboard > SEO Kit > Audit: the site score, totals by problem type, the pages
  to fix first with advice for each problem, the score over the last ten audits,
  and a CSV download.
- Each page scores 100 minus 20 per error, 10 per warning and 3 per notice. The
  site score is the average over every page audited. The latest ten audits are
  kept.
- Two tables, `seoKitAuditRuns` and `seoKitAuditFindings`, dropped on uninstall
  when "purge database tables" is ticked.

### Changed

- Installing or updating clears the current user's dashboard menu cache, which
  Concrete keeps in the session, so new SEO Kit pages show without logging in
  again.

## 0.3.1

### Added

- Package `icon.png`.

### Fixed

- Event start and end times now carry the site timezone's offset for that date
  (for example `2027-07-01T19:00:00+01:00` in summer, `+00:00` in winter), as
  Google asks for. Before, they were output without a timezone. A site with no
  usable timezone keeps the time as typed.

## 0.3.0

### Added

- Twitter / X Card tags (`twitter:card`, `twitter:title`, `twitter:description`,
  `twitter:image`), added through core's `on_header_required_ready` event. The
  title and description are the page's Meta Title and Meta Description, falling
  back to the page name and description. The image is the page Thumbnail (the
  attribute core's Open Graph uses), falling back to the site's Default Sharing
  Image from `lgt_toolkit`.
- `seo_preview` page attribute (SEO Kit set), "Search & Social Preview": shows
  the Google result and the Open Graph and X cards, with title and description
  length in characters and pixels and a list of problems. It reads the page
  name, description, Meta Title and Meta Description live as they are edited,
  and adds no fields of its own.

### Changed

- Open Graph tags stay with core, which uses the page name and description
  rather than the Meta Title and Meta Description. The preview shows this.

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
