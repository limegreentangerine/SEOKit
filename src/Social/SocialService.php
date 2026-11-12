<?php

namespace SeoKit\Social;

use SeoKit\Support\Files;
use SeoKit\Support\ControllerValues;
use Concrete\Core\Page\Page;
use Concrete\Core\Entity\File\File;
use Doctrine\ORM\EntityManagerInterface;
use Concrete\Core\Application\Application;
use Concrete\Core\Entity\Sharing\SocialNetwork\Link;

/**
 * The social sharing data for a page, taken from core's social.opengraph site config the way core and your
 * application-level Open Graph classes do: field_og_title, field_og_description and field_og_thumbnail name where
 * each value comes from. Open Graph and Twitter Cards both read it here, so they always agree.
 */
class SocialService
{
    public function __construct(protected Application $app) {}

    /**
     * @param string $property "title" or "description": what to fall back to
     */
    protected function text(Page $page, object $site, string $configField, string $property): string
    {
        return SocialFields::text(
            $site->getConfigRepository()->get('social.opengraph.' . $configField),
            $property,
            static fn(string $handle): mixed => $handle === '' ? null : $page->getAttribute($handle),
            ['title' => (string) $page->getCollectionName(), 'description' => (string) $page->getCollectionDescription()],
        );
    }

    /**
     * Mirrors core's switch: Open Graph and Twitter Cards are output together or not at all.
     */
    public function isEnabled(object $site): bool
    {
        return (bool) $site->getConfigRepository()->get('social.opengraph.enabled');
    }

    public function title(Page $page, object $site): string
    {
        return ControllerValues::get($page, 'pageTitle') ?: $this->text($page, $site, 'field_og_title', 'title');
    }

    public function description(Page $page, object $site): string
    {
        return ControllerValues::get($page, 'pageDescription') ?: $this->text($page, $site, 'field_og_description', 'description');
    }

    /**
     * The page attribute the social title is read from, or null when it uses the page name or a fixed value.
     */
    public function titleAttributeHandle(object $site): ?string
    {
        return SocialFields::attributeHandle($site->getConfigRepository()->get('social.opengraph.field_og_title'));
    }

    public function descriptionAttributeHandle(object $site): ?string
    {
        return SocialFields::attributeHandle($site->getConfigRepository()->get('social.opengraph.field_og_description'));
    }

    /**
     * The sharing image: the page attribute core's Open Graph uses (thumbnail by default), then the site's Default
     * Sharing Image from lgt_toolkit.
     */
    public function imageFile(Page $page, object $site): ?File
    {
        $setting = $site->getConfigRepository()->get('social.opengraph.field_og_thumbnail');
        $handle = is_array($setting) && ($setting['value_from'] ?? '') === 'page_attribute' && !empty($setting['attribute'])
            ? (string) $setting['attribute']
            : 'thumbnail';

        $image = $page->getAttribute($handle);

        if ($image instanceof File) {
            return $image;
        }

        $default = $site->getAttribute('opengraph_default_image');

        return $default instanceof File ? $default : null;
    }

    public function imageUrl(Page $page, object $site): string
    {
        return ControllerValues::get($page, 'pageImage') ?: Files::url($this->imageFile($page, $site));
    }

    /**
     * The site's Twitter / X account handle from its Social Links, without "@", or an empty string.
     */
    public function twitterHandle(object $site): string
    {
        $links = $this->app->make(EntityManagerInterface::class)->getRepository(Link::class)->findBy(['site' => $site]);

        foreach ($links as $link) {
            if (in_array($link->getServiceHandle(), ['twitter', 'x'], true)) {
                $handle = TwitterCard::handleFromUrl((string) $link->getURL());

                if ($handle !== '') {
                    return $handle;
                }
            }
        }

        return '';
    }

    /**
     * Twitter / X Card tags for the page, keyed for core's metaTags array. Empty when social tags are switched off.
     *
     * @return array<string, string>
     */
    public function twitterTags(Page $page): array
    {
        $site = $this->app->make('site')->getSite();

        if (!$site || !$this->isEnabled($site)) {
            return [];
        }

        return TwitterCard::tags(
            $this->title($page, $site),
            $this->description($page, $site),
            $this->imageUrl($page, $site),
            $this->twitterHandle($site),
        );
    }
}
