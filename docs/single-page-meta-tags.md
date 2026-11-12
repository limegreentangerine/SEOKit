# Setting meta tags from a single page controller

SEO Kit does not add title or description fields, and it has no meta tag API of
its own. It works with core, so from a single page controller you use core's
hooks. Core's `concrete/elements/header_required.php` is where the tags are
built and printed.

## What SEO Kit already does

- **Twitter / X cards:** added through core's `on_header_required_ready` event
  (`SeoKit\Events\Page::addTwitterCard`). Keys core already set win.
- **Open Graph:** `SeoKit\Social\OpenGraph` replaces core's class. It reads the
  title, description and image from core's `social.opengraph.*` site config,
  falling back to the page name, description and `thumbnail`.
- **Robots directives:** `seo_robots` attribute -> `<meta name="robots">`.

## Title and description

Core reads two controller variables:

```php
public function view()
{
    $this->set('pageTitle', 'Contact us');
    $this->set('pageDescription', 'How to reach the team.');
}
```

`pageTitle` is used as given, with no site title format applied. Without these,
core uses the `meta_title` / `meta_description` attributes, then the page name
and description. For static values you can set those attributes in Page
Properties instead.

## Extra meta tags

Core fires `on_header_required_ready` before printing. `metaTags` is an array
of **complete HTML strings** keyed by a name; core prints them with
`implode()`. Add yours there so you do not duplicate tags core already
produced:

```php
use Events;

public function view()
{
    Events::addListener('on_header_required_ready', function ($event) {
        $tags = (array) $event->getArgument('metaTags');

        // the key is only an id; the value is the whole tag
        $tags['theme-colour'] = '<meta name="theme-color" content="' . h('#1a1a1a') . '"/>';

        $event->setArgument('metaTags', $tags);
    });
}
```

- Escape every dynamic value with `h()`.
- A key that already exists is replaced. SEO Kit's own listener keeps existing
  keys, so to override a Twitter tag, set it in your listener after SEO Kit's
  (or use the attributes below).
- The event also carries `pageTitle`, `linkTags` and `alternateHreflangTags`,
  which you can change the same way.
- The listener is global, so if you only want it for one page, compare the
  current page's ID first, as in
  [the JSON-LD guide](single-page-json-ld.md).

To print one raw tag without the event, use
`$this->addHeaderItem('<meta ... />')`. That bypasses core's tag array, so core
and SEO Kit cannot see or dedupe it.

## Open Graph and Twitter cards

Do not hand-write these if you can avoid it. Set the page attribute named in the
site's `social.opengraph.field_og_title`, `field_og_description` and
`field_og_thumbnail` settings (see the README table). SEO Kit then fills in Open
Graph and Twitter cards together, so they always agree.

## Robots

Set the **Robots Directives** (`seo_robots`) attribute. A hand-written robots
tag as well as the attribute gives the page two robots tags, which search
engines combine. For `noindex`, tick core's **Exclude From Search Index**
attribute.

## Keep meta tags and JSON-LD in step

If the JSON-LD and the meta tags should show the same name, description or
image, set the page attributes once and let both read them. Setting the values
in the controller as well means keeping two copies aligned.

## Dynamic single pages (one page, many records)

A single page that takes a parameter, such as `view(?string $propID = null)` on
`/property/123`, is one Concrete page for every record. SEO Kit would describe
the parent page, so set these four variables in `view()`. The JSON-LD, Open
Graph and Twitter cards all read them. No listeners are needed.

```php
$this->set('pageTitle', $title);              // name and title
$this->set('pageDescription', $description);
$this->set('pageImage', $imageUrl);           // absolute URL
$this->set('ogUrl', $recordUrl);              // absolute URL of this record
```

| Variable          | Used for                                                              |
| ----------------- | --------------------------------------------------------------------- |
| `pageTitle`       | JSON-LD WebPage and last breadcrumb name, `og:title`, `twitter:title` |
| `pageDescription` | JSON-LD WebPage description, `og:description`, `twitter:description`  |
| `pageImage`       | `og:image:url`, `twitter:image` (also the card type)                  |
| `ogUrl`           | JSON-LD WebPage `url` and `@id`, last breadcrumb, `og:url`            |

- `pageTitle` and `pageDescription` are the variables core already reads for
  the `<title>` and meta description, so one `set()` covers all of them.
- `pageImage` and `ogUrl` must start with `http://` or `https://`; anything else
  is ignored. Pages that do not set a variable keep the page's own values.
- Values are read from what the controller set, never from the query string.
- Set them after you have loaded the record and before `view()` returns. If
  `view()` returns a redirect, nothing is rendered.
- If `pageTitle` includes the site name (for example "Flat 4 | Acme"), the
  social and JSON-LD titles will too.
- Core's canonical URL is separate: for a record, also call
  `$this->app->make(\Concrete\Core\Url\SeoCanonical::class)->setPathArguments([$propID])`.
