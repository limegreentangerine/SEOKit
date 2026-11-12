# Adding JSON-LD from a single page controller

SEO Kit already prints one `<script type="application/ld+json">` block with a
single `@graph` on every public page. From a single page controller, add your
nodes to that graph instead of printing a second block.

## How it works

`JsonLdService::render()` builds the graph and then fires the
`on_seo_kit_jsonld_graph` event (`JsonLdService::EVENT_GRAPH`). The page is the
event subject and the nodes are the `graph` argument. A listener can add,
remove or change nodes.

The JSON-LD is added on `on_before_render` (see `src/Events/Page.php`), which
fires after the controller's `view()`. A listener registered in `view()` is in
time.

## Add a node

```php
use Events;
use SeoKit\JsonLd\JsonLdService;
use SeoKit\JsonLd\SchemaBuilder;

public function view()
{
    $page = $this->getPageObject();

    Events::addListener(JsonLdService::EVENT_GRAPH, function ($event) use ($page) {
        // the listener is global, so only act for this page
        if ($event->getSubject()->getCollectionID() !== $page->getCollectionID()) {
            return;
        }

        $url = (string) $page->getCollectionLink();
        $graph = (array) $event->getArgument('graph');

        $graph[] = SchemaBuilder::clean([
            '@type' => 'FAQPage',
            '@id' => $url . '#faq',
            'mainEntityOfPage' => ['@id' => $url . '#webpage'],
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => 'Where are you based?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'In Leeds.'],
                ],
            ],
        ]);

        $event->setArgument('graph', $graph);
    });
}
```

## Conventions

- `SchemaBuilder::clean()` drops null, empty-string and empty-array values.
- Node `@id` values follow `<page url>#<slug>`: `#webpage` for the page's
  WebPage and `<site url>#organization` for the site's Organization. Point
  `mainEntityOfPage`, `publisher` and similar at those ids to link your node
  into the existing graph.
- A node with the same `@id` as a generated node replaces it. To change the
  WebPage node, find it in `$graph` by `@id` and replace that entry.
- If the Organization or WebPage node is switched off in Dashboard > SEO Kit >
  Structured Data, a reference to it points at nothing. Check
  `$site->getConfigRepository()->get('seo_kit.jsonld.disabled')` if that
  matters.

## Without code

If the content never changes, set the page's attributes in Page Properties
(SEO Kit set) instead:

- **Schema** (`seo_schema`): pick a type (Article, FAQ, Product, Event, Person,
  Local business, How-to) and fill in its fields.
- **Custom JSON-LD** (`seo_jsonld_custom`): paste a JSON object, a list of
  objects, or an object with an `@graph` list.

## Fallback: a standalone block

If SEO Kit might not be installed, or you want output outside the graph:

```php
$this->addHeaderItem(SchemaBuilder::render([$node]));
```

This prints a second, unlinked `<script>` block. `render()` escapes `<`, `>`
and `&` so data cannot break out of the tag.

## Check it

View the page source: there should be one `application/ld+json` block with
your node in the `@graph`. Run the page through Google's Rich Results Test.
Nothing is output in the dashboard, edit mode, system pages or XHR requests.

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

| Variable          | Used for                                                                 |
| ----------------- | ------------------------------------------------------------------------ |
| `pageTitle`       | JSON-LD WebPage and last breadcrumb name, `og:title`, `twitter:title`    |
| `pageDescription` | JSON-LD WebPage description, `og:description`, `twitter:description`     |
| `pageImage`       | `og:image:url`, `twitter:image` (also the card type)                     |
| `ogUrl`           | JSON-LD WebPage `url` and `@id`, last breadcrumb, `og:url`               |

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

To add a node for the record itself (a `Product`, `Event`, ...), use the
listener above with `$url` set to the same value as `ogUrl`. The generated WebPage
node already points at the record, so reference `$url . '#webpage'`.
