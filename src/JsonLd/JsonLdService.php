<?php

namespace SeoKit\JsonLd;

use SeoKit\Support\Files;
use SeoKit\Support\ControllerValues;
use Concrete\Core\File\File;
use Concrete\Core\Page\Page;
use Psr\Log\LoggerInterface;
use SeoKit\JsonLd\Types\TypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Concrete\Core\Application\Application;
use Symfony\Component\EventDispatcher\GenericEvent;
use Concrete\Core\Entity\Sharing\SocialNetwork\Link;
use Concrete\Core\Multilingual\Page\Section\Section;

/**
 * Collects site/page data from Concrete and renders the JSON-LD block. Fails open: any error logs and emits nothing.
 */
class JsonLdService
{
    public const EVENT_GRAPH = 'on_seo_kit_jsonld_graph';

    public const CUSTOM_ATTRIBUTE = 'seo_jsonld_custom';

    public const SCHEMA_ATTRIBUTE = 'seo_schema';

    public function __construct(protected Application $app) {}

    protected function siteData(object $site, ?Section $section): array
    {
        $config = $site->getConfigRepository();
        $home = $section ?? $site->getSiteHomePageObject();

        $logoID = (int) $config->get('seo_kit.jsonld.logo_id');
        // Falls back to the toolkit's default sharing image when present.
        $logo = Files::url($logoID ? File::getByID($logoID) : null)
            ?: Files::url($site->getAttribute('opengraph_default_image'));

        return [
            'url' => (string) $home->getCollectionLink(),
            'name' => (string) ($site->getAttribute('site_company_name') ?: $site->getSiteName()),
            'type' => (string) $config->get('seo_kit.jsonld.type'),
            'logo' => $logo,
            'sameAs' => $this->socialLinks($site),
            'telephone' => (string) $site->getAttribute('site_company_phone'),
            'email' => (string) $site->getAttribute('site_company_email'),
            'address' => $this->address($site->getAttribute('site_company_address')),
            'openingHours' => $this->lines((string) $config->get('seo_kit.jsonld.opening_hours')),
            'inLanguage' => $this->language($section),
            // the site's timezone (Dashboard > System & Settings > Date & Time), for times editors enter
            'timezone' => (string) $site->getTimezone(),
            'disabled' => (array) $config->get('seo_kit.jsonld.disabled', []),
        ];
    }

    protected function pageData(Page $page, ?Section $section): array
    {
        $crumbs = [];

        foreach (array_reverse($this->app->make('helper/navigation')->getTrailToCollection($page)) as $parent) {
            $crumbs[] = ['name' => (string) $parent->getCollectionName(), 'url' => (string) $parent->getCollectionLink()];
        }

        // a controller's ogUrl / pageTitle / pageDescription describe the record a single page is showing
        $url = ControllerValues::get($page, 'ogUrl') ?: (string) $page->getCollectionLink();
        $name = ControllerValues::get($page, 'pageTitle') ?: (string) $page->getCollectionName();
        $crumbs[] = ['name' => $name, 'url' => $url];

        $iso = static fn(string $date): string => $date === '' ? '' : (new \DateTimeImmutable($date))->format(DATE_ATOM);

        return [
            'url' => $url,
            'name' => $name,
            'description' => ControllerValues::get($page, 'pageDescription') ?: (string) $page->getCollectionDescription(),
            'inLanguage' => $this->language($section),
            'datePublished' => $iso((string) $page->getCollectionDatePublic()),
            'dateModified' => $iso((string) $page->getCollectionDateLastModified()),
            'breadcrumbs' => $crumbs,
        ];
    }

    /**
     * The node for the page's chosen schema type (Article, FAQ, ...), if any.
     *
     * @return array<int, array>
     */
    protected function schemaNodes(Page $page, array $pageData, array $siteData): array
    {
        $result = TypeRegistry::nodes((string) $page->getAttribute(self::SCHEMA_ATTRIBUTE), $pageData, $siteData);

        foreach ($result['errors'] as $error) {
            $this->log(sprintf('Schema type on page %d skipped: %s', $page->getCollectionID(), $error));
        }

        return $result['nodes'];
    }

    /**
     * @return array<int, array>
     */
    protected function customNodes(Page $page): array
    {
        $result = CustomJsonLd::parse((string) $page->getAttribute(self::CUSTOM_ATTRIBUTE));

        if ($result['error'] !== null) {
            $this->log(sprintf('Invalid custom JSON-LD on page %d ignored: %s', $page->getCollectionID(), $result['error']));
        }

        return $result['nodes'];
    }

    /**
     * URLs from the site's Social Links (Dashboard > System & Settings > Social Links).
     *
     * @return array<int, string>
     */
    protected function socialLinks(object $site): array
    {
        $links = $this->app->make(EntityManagerInterface::class)->getRepository(Link::class)->findBy(['site' => $site]);
        $urls = array_map(static fn(Link $link): string => trim((string) $link->getURL()), $links);

        return array_values(array_filter($urls, static fn(string $url): bool => (bool) preg_match('#^https?://#i', $url)));
    }

    protected function language(?Section $section): string
    {
        return $section ? str_replace('_', '-', (string) $section->getLocale()) : '';
    }

    /**
     * Maps Concrete's address attribute value to schema.org PostalAddress fields.
     */
    protected function address(mixed $value): array
    {
        if (!is_object($value) || !method_exists($value, 'getAddress1')) {
            return [];
        }

        return [
            'streetAddress' => trim($value->getAddress1() . ' ' . $value->getAddress2()),
            'addressLocality' => (string) $value->getCity(),
            'addressRegion' => (string) $value->getStateProvince(),
            'postalCode' => (string) $value->getPostalCode(),
            'addressCountry' => (string) $value->getCountry(),
        ];
    }

    protected function log(string $message): void
    {
        try {
            $this->app->make(LoggerInterface::class)->warning($message);
        } catch (\Throwable) {
            // logging must never break page rendering
        }
    }

    public function render(Page $page): ?string
    {
        try {
            $site = $this->app->make('site')->getSite();

            if (!$site) {
                return null;
            }

            // getBySectionOfSite() returns a falsy value on non-multilingual sites
            $section = Section::getBySectionOfSite($page) ?: null; // @phpstan-ignore ternary.alwaysTrue

            $siteData = $this->siteData($site, $section);
            $pageData = $this->pageData($page, $section);

            // Schema type nodes come before custom JSON-LD, so custom nodes can still override them by @id.
            $graph = SchemaBuilder::graph(
                $siteData,
                $pageData,
                array_merge($this->schemaNodes($page, $pageData, $siteData), $this->customNodes($page)),
            );

            // Lets other code add, remove or change nodes: $event->setArgument('graph', ...).
            $event = new GenericEvent($page, ['graph' => $graph]);
            $this->app->make('director')->dispatch(self::EVENT_GRAPH, $event);

            return SchemaBuilder::render((array) $event->getArgument('graph'));
        } catch (\Throwable $e) {
            $this->log('JSON-LD skipped: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    public function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));
    }
}
