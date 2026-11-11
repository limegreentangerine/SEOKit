<?php

namespace SeoKit\Events;

use Core;
use Illuminate\Support\Str;
use SeoKit\Robots\RobotsMeta;
use Concrete\Core\Http\Request;
use SeoKit\JsonLd\JsonLdService;
use Concrete\Core\Page\Page as ConcretePage;

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
}
