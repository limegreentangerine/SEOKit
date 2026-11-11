<?php

namespace SeoKit\JsonLd;

use Concrete\Core\File\File;
use Concrete\Core\Page\Page;
use Psr\Log\LoggerInterface;
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

    public function __construct(protected Application $app) {}

    protected function siteData(object $site, ?Section $section): array
    {
        $config = $site->getConfigRepository();
        $home = $section ?? $site->getSiteHomePageObject();

        $logoID = (int) $config->get('seo_kit.jsonld.logo_id');
        // Falls back to the toolkit's default sharing image when present.
        $logo = $this->fileUrl($logoID ? File::getByID($logoID) : null)
            ?: $this->fileUrl($site->getAttribute('opengraph_default_image'));

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
            'disabled' => (array) $config->get('seo_kit.jsonld.disabled', []),
        ];
    }

    protected function pageData(Page $page, ?Section $section): array
    {
        $crumbs = [];

        foreach (array_reverse($this->app->make('helper/navigation')->getTrailToCollection($page)) as $parent) {
            $crumbs[] = ['name' => (string) $parent->getCollectionName(), 'url' => (string) $parent->getCollectionLink()];
        }

        $url = (string) $page->getCollectionLink();
        $crumbs[] = ['name' => (string) $page->getCollectionName(), 'url' => $url];

        $modified = (string) $page->getCollectionDateLastModified();

        return [
            'url' => $url,
            'name' => (string) $page->getCollectionName(),
            'description' => (string) $page->getCollectionDescription(),
            'inLanguage' => $this->language($section),
            'dateModified' => $modified === '' ? '' : (new \DateTimeImmutable($modified))->format(DATE_ATOM),
            'breadcrumbs' => $crumbs,
        ];
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

    protected function fileUrl(mixed $file): string
    {
        if (is_object($file) && method_exists($file, 'getApprovedVersion')) {
            $file = $file->getApprovedVersion();
        }

        return is_object($file) && method_exists($file, 'getURL') ? (string) $file->getURL() : '';
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

            $graph = SchemaBuilder::graph(
                $this->siteData($site, $section),
                $this->pageData($page, $section),
                $this->customNodes($page),
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
