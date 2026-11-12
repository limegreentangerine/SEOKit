<?php

namespace SeoKit\Events;

use Core;
use Illuminate\Support\Str;
use SeoKit\Robots\RobotsMeta;
use Concrete\Core\Http\Request;
use SeoKit\JsonLd\JsonLdService;
use Concrete\Core\Page\Page as ConcretePage;
use Symfony\Component\EventDispatcher\GenericEvent;
use Concrete\Core\Sharing\OpenGraph\OpenGraph as CoreOpenGraph;
use SeoKit\Social\{OpenGraph as SeoKitOpenGraph, SocialService};

/**
 * Handles page-level event hooks for SEO output.
 */
class Page
{
    /**
     * The current page when it is a public front-end HTML page, otherwise null.
     */
    protected static function publicPage(): ?ConcretePage
    {
        $request = Core::make(Request::class);

        if ($request->isXmlHttpRequest() || Str::contains($request->headers->get('Accept', ''), 'application/json')) {
            return null;
        }

        $page = ConcretePage::getCurrentPage();

        if (!is_object($page) || $page->isError() || $page->isAdminArea() || $page->isSystemPage() || $page->isEditMode()) {
            return null;
        }

        return $page;
    }

    /**
     * Adds the JSON-LD block to the head of public front-end pages only.
     */
    public static function addJsonLd(): void
    {
        $page = static::publicPage();

        if ($page === null) {
            return;
        }

        $html = Core::make(JsonLdService::class)->render($page);

        if ($html !== null) {
            $page->getPageController()->addHeaderItem($html);
        }
    }

    /**
     * Adds the page's extra robots directives (nofollow, noarchive, ...) to the head.
     */
    public static function addRobots(): void
    {
        $page = static::publicPage();

        if ($page === null) {
            return;
        }

        $tag = RobotsMeta::tag($page->getAttribute('seo_robots'));

        if ($tag !== null) {
            $page->getPageController()->addHeaderItem($tag);
        }
    }

    /**
     * Makes core's header_required.php, which asks the container for core's OpenGraph class, get the SEO Kit
     * subclass instead (see Social\OpenGraph).
     *
     * This has to happen late, from on_before_render, not when the package starts: core registers its Sharing
     * provider after packages start, and that registration would replace a binding made earlier. instance()
     * wins over any registration. Fails open: on any error core's class stays in place.
     */
    public static function useOpenGraph(): void
    {
        try {
            $app = Core::make('app');
            $app->instance(CoreOpenGraph::class, $app->make(SeoKitOpenGraph::class));
        } catch (\Throwable) {
            // core's own Open Graph tags are used
        }
    }

    /**
     * Adds Twitter / X Card tags through core's on_header_required_ready event, which hands over the meta tags
     * before they are printed. Fails open: any error leaves core's tags as they were.
     */
    public static function addTwitterCard(GenericEvent $event): void
    {
        $page = static::publicPage();

        if ($page === null) {
            return;
        }

        try {
            $tags = Core::make(SocialService::class)->twitterTags($page);
        } catch (\Throwable) {
            return;
        }

        if ($tags !== []) {
            // keys core already set win, so we never replace a tag another package added
            $event->setArgument('metaTags', (array) $event->getArgument('metaTags') + $tags);
        }
    }
}
