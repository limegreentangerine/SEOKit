<?php

namespace SeoKit\Social;

use SeoKit\Support\Files;
use Concrete\Core\Page\Page;
use Concrete\Core\Application\Application;

/**
 * Gathers the social sharing data for a page the same way core does for Open Graph, so the two agree on the image.
 */
class SocialService
{
    public function __construct(protected Application $app) {}

    /**
     * Twitter / X Card tags for the page, keyed for core's metaTags array. Empty when the site is unknown.
     *
     * @return array<string, string>
     */
    public function twitterTags(Page $page): array
    {
        $site = $this->app->make('site')->getSite();

        if (!$site) {
            return [];
        }

        $title = trim((string) $page->getAttribute('meta_title')) ?: (string) $page->getCollectionName();
        $description = trim((string) $page->getAttribute('meta_description')) ?: (string) $page->getCollectionDescription();

        return TwitterCard::tags($title, $description, $this->imageUrl($page, $site));
    }

    /**
     * The page's sharing image: the attribute core's Open Graph uses (thumbnail by default),
     * then the site's Default Sharing Image from lgt_toolkit.
     */
    public function imageUrl(Page $page, object $site): string
    {
        return $this->pageImageUrl($page, $site) ?: Files::url($site->getAttribute('opengraph_default_image'));
    }

    /**
     * Only the page's own image, as core's Open Graph tags would use it.
     */
    public function pageImageUrl(Page $page, object $site): string
    {
        $setting = $site->getConfigRepository()->get('social.opengraph.field_og_thumbnail');
        $handle = is_array($setting) && ($setting['value_from'] ?? '') === 'page_attribute' && !empty($setting['attribute'])
            ? (string) $setting['attribute']
            : 'thumbnail';

        return Files::url($page->getAttribute($handle));
    }
}
