<?php

namespace SeoKit\Events;

use Core;
use Illuminate\Support\Str;
use Concrete\Core\Http\Request;
use SeoKit\JsonLd\JsonLdService;
use Concrete\Core\Page\Page as ConcretePage;

/**
 * Handles page-level event hooks for SEO output.
 */
class Page
{
    /**
     * Adds the JSON-LD block to the head of public front-end pages only.
     */
    public static function addJsonLd(): void
    {
        $request = Core::make(Request::class);

        if ($request->isXmlHttpRequest() || Str::contains($request->headers->get('Accept', ''), 'application/json')) {
            return;
        }

        $page = ConcretePage::getCurrentPage();

        if (!is_object($page) || $page->isError() || $page->isAdminArea() || $page->isSystemPage() || $page->isEditMode()) {
            return;
        }

        $html = Core::make(JsonLdService::class)->render($page);

        if ($html !== null) {
            $page->getPageController()->addHeaderItem($html);
        }
    }
}
