<?php

namespace SeoKit\Social;

use HtmlObject\Element;
use Concrete\Core\Page\Page;
use SeoKit\Support\ControllerValues;
use Concrete\Core\Sharing\OpenGraph\OpenGraph as CoreOpenGraph;

/**
 * Core's Open Graph tags with two fixes, bound over core's class (see SocialServiceProvider) so no header_required
 * override is needed. The tags and their order are core's.
 *
 * - A title or description whose source is an empty page attribute falls back to the page name or description,
 *   where core prints no tag.
 * - A page with no image of its own uses the site's Default Sharing Image.
 *
 * Fails open: if anything here throws, core's own tags are used.
 */
class OpenGraph extends CoreOpenGraph
{
    /**
     * @return array<int, Element>
     */
    protected function build(Page $page): array
    {
        $site = $this->siteService->getSite();
        $social = app(SocialService::class);
        $none = static fn(string $handle): mixed => null;

        $tags = [$this->meta(self::TAG_OG_SITE_NAME, (string) $site->getSiteName())];

        $tree = $page->getSiteTreeObject();

        if ($tree) {
            $tags[] = $this->meta(self::TAG_OG_LOCALE, (string) $tree->getLocale()->getLocale());
        }

        $candidates = [
            [self::TAG_OG_TYPE, SocialFields::text($this->config->get('social.opengraph.field_og_type'), 'type', $none, ['type' => 'website'])],
            [self::TAG_OG_TITLE, $social->title($page, $site)],
            [self::TAG_OG_DESCRIPTION, $social->description($page, $site)],
            [self::TAG_FB_APP_ID, SocialFields::text($this->config->get('social.opengraph.field_fb_app_id'), 'none', $none, [])],
        ];

        foreach ($candidates as [$property, $content]) {
            if ($content !== '') {
                $tags[] = $this->meta($property, $content);
            }
        }

        // a controller's pageImage / ogUrl (a record shown by a single page) win over the page's own values
        $url = ControllerValues::get($page, 'ogUrl');

        if ($url !== '') {
            $tags[] = $this->meta(self::TAG_OG_URL, $url);
        } elseif ($site->getSiteCanonicalURL()) {
            $tags[] = $this->meta(self::TAG_OG_URL, (string) $this->resolverManager->resolve([$page]));
        }

        $pageImage = ControllerValues::get($page, 'pageImage');
        $image = $pageImage === '' ? $social->imageFile($page, $site) : null;

        if ($pageImage !== '') {
            $tags[] = $this->meta(self::TAG_OG_IMAGE_URL, $pageImage);
        }

        if ($image !== null) {
            $tags[] = $this->meta(self::TAG_OG_IMAGE_URL, (string) $image->getURL());
            $tags[] = $this->meta(self::TAG_OG_IMAGE_TYPE, (string) $image->getMimeType());

            foreach ([self::TAG_OG_IMAGE_WIDTH => 'width', self::TAG_OG_IMAGE_HEIGHT => 'height'] as $property => $attribute) {
                $size = $image->getAttribute($attribute);

                if ($size) {
                    $tags[] = $this->meta($property, (string) $size);
                }
            }
        }

        return $tags;
    }

    /**
     * Core's createTag() is private, so this repeats it.
     */
    protected function meta(string $property, string $content): Element
    {
        $element = new Element('meta');
        $element->setIsSelfClosing(true);
        $element->setAttribute('property', $property);
        $element->setAttribute('content', htmlspecialchars($content, ENT_QUOTES, defined('APP_CHARSET') ? APP_CHARSET : 'UTF-8'));

        return $element;
    }
    /**
     * @return array<int, Element>
     */
    public function getTags(Page $page): array
    {
        try {
            return $this->build($page);
        } catch (\Throwable) {
            return parent::getTags($page);
        }
    }
}
