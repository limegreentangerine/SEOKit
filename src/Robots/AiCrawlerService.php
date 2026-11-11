<?php

namespace SeoKit\Robots;

use Concrete\Core\Page\PageList;
use Concrete\Core\Application\Application;
use Concrete\Core\Page\Sitemap\PageListGenerator;

/**
 * Settings, the robots.txt file and the llms.txt content. Everything here is per site.
 */
class AiCrawlerService
{
    /**
     * Pages listed in llms.txt; a larger site gets its first pages by name, which keeps the output small.
     */
    public const LLMS_LIMIT = 200;

    public const SAVED = 'saved';

    public const MANUAL = 'manual';

    public const DAMAGED = 'damaged';

    public function __construct(protected Application $app) {}

    protected function config(): \Concrete\Core\Site\Config\Liaison
    {
        return $this->app->make('site')->getSite()->getConfigRepository();
    }

    /**
     * @return array{blocked: array<int, string>, custom: string, sitemap: bool, llms: bool, intro: string}
     */
    public function settings(): array
    {
        $config = $this->config();

        return [
            'blocked' => RobotsTxt::knownBots($config->get('seo_kit.ai.blocked', [])),
            'custom' => RobotsTxt::cleanCustom($config->get('seo_kit.ai.custom', '')),
            'sitemap' => (bool) $config->get('seo_kit.ai.sitemap', true),
            'llms' => (bool) $config->get('seo_kit.ai.llms', false),
            'intro' => LlmsTxt::line($config->get('seo_kit.ai.intro', ''), LlmsTxt::INTRO_MAX),
        ];
    }

    /**
     * @param array<int, string> $blocked
     */
    public function save(array $blocked, string $custom, bool $sitemap, bool $llms, string $intro): void
    {
        $config = $this->config();
        $config->save('seo_kit.ai.blocked', RobotsTxt::knownBots($blocked));
        $config->save('seo_kit.ai.custom', RobotsTxt::cleanCustom($custom));
        $config->save('seo_kit.ai.sitemap', $sitemap);
        $config->save('seo_kit.ai.llms', $llms);
        $config->save('seo_kit.ai.intro', LlmsTxt::line($intro, LlmsTxt::INTRO_MAX));
    }

    public function robotsPath(): string
    {
        return DIR_BASE . '/robots.txt';
    }

    public function llmsPath(): string
    {
        return DIR_BASE . '/llms.txt';
    }

    /**
     * The sitemap's address, but only when core has generated it: a Sitemap line to a missing file helps nobody.
     */
    public function sitemapUrl(): ?string
    {
        $name = (string) $this->app->make('config')->get('concrete.sitemap_xml.file', 'sitemap.xml');

        if (!is_file(DIR_BASE . '/' . $name)) {
            return null;
        }

        // the resolver adds a trailing slash, which would turn the file into a folder
        return rtrim((string) $this->app->make('url/manager')->resolve([$name]), '/');
    }

    public function block(): string
    {
        $settings = $this->settings();

        return RobotsTxt::block($settings['blocked'], $settings['custom'], $settings['sitemap'] ? $this->sitemapUrl() : null);
    }

    public function currentRobots(): ?string
    {
        $text = is_file($this->robotsPath()) ? @file_get_contents($this->robotsPath()) : null;

        return $text === false ? null : $text;
    }

    /**
     * What robots.txt would contain after saving, or null when the markers in it are damaged.
     */
    public function preview(): ?string
    {
        return RobotsTxt::merge($this->currentRobots(), $this->block());
    }

    /**
     * Writes the managed block into robots.txt. Returns SAVED, DAMAGED (file left alone) or MANUAL (not writable:
     * the editor has to paste the preview in).
     */
    public function apply(): string
    {
        $merged = $this->preview();

        if ($merged === null) {
            return self::DAMAGED;
        }

        $path = $this->robotsPath();

        if (!(is_file($path) ? is_writable($path) : is_writable(dirname($path)))) {
            return self::MANUAL;
        }

        return @file_put_contents($path, $merged, LOCK_EX) === false ? self::MANUAL : self::SAVED;
    }

    /**
     * Pages for llms.txt: what core would put in the sitemap (published, public, not excluded), minus noindex
     * pages and pages that opted out. Built the same for every visitor.
     *
     * @return array<int, array{title: string, url: string, description: string}>
     */
    public function llmsPages(): array
    {
        $generator = $this->app->make(PageListGenerator::class);
        $list = new PageList();
        $list->sortByName();
        $list->setItemsPerPage(self::LLMS_LIMIT * 2);

        $pages = [];

        foreach ($list->getResults() as $page) {
            if (count($pages) >= self::LLMS_LIMIT) {
                break;
            }

            if (!$generator->canIncludePageInSitemap($page) || $page->getAttribute('exclude_search_index') || $page->getAttribute('seo_llms_exclude')) {
                continue;
            }

            $pages[] = [
                'title' => (string) $page->getCollectionName(),
                'url' => (string) $page->getCollectionLink(true),
                'description' => (string) ($page->getAttribute('meta_description') ?: $page->getCollectionDescription()),
            ];
        }

        return $pages;
    }

    public function llms(): string
    {
        $site = $this->app->make('site')->getSite();

        return LlmsTxt::build((string) $site->getSiteName(), $this->settings()['intro'], $this->llmsPages());
    }
}
