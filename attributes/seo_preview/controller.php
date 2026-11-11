<?php

namespace Concrete\Package\SeoKit\Attribute\SeoPreview;

use Concrete\Core\Page\Page;
use SeoKit\Social\SocialService;
use Concrete\Core\Attribute\DefaultController;
use Concrete\Core\Attribute\Key\CollectionKey;
use Concrete\Core\Attribute\FontAwesomeIconFormatter;
use Concrete\Core\Entity\Attribute\Value\Value\TextValue;

/**
 * An editor-only attribute: it shows how the page will look in Google and when shared, and updates as the
 * Meta Title, Meta Description, page name and description are edited. It has no data of its own; a marker value
 * is stored so the preview stays on the form the next time the page's attributes are opened.
 */
class Controller extends DefaultController
{
    /**
     * Rough Google desktop limits, in pixels and characters.
     */
    public const TITLE_MAX_PX = 580;

    public const TITLE_MIN_PX = 200;

    public const DESCRIPTION_MAX_PX = 920;

    public const DESCRIPTION_MIN_CHARS = 70;
    public $helpers = ['form'];

    /**
     * Everything the form's script needs: where to read the live values, the saved values to fall back on,
     * the site's title format and the sharing images.
     *
     * @return array<string, mixed>
     */
    protected function config(): array
    {
        $site = $this->app->make('site')->getSite();
        $page = Page::getCurrentPage();
        $page = is_object($page) && !$page->isError() ? $page : null;

        $fieldName = static function (string $handle): string {
            $key = CollectionKey::getByHandle($handle);

            return $key ? 'akID[' . $key->getAttributeKeyID() . '][value]' : '';
        };

        $social = $this->app->make(SocialService::class);
        $attr = static fn(string $handle): string => $page ? trim((string) $page->getAttribute($handle)) : '';

        return [
            'id' => 'seo-preview-' . bin2hex(random_bytes(4)),
            'fields' => [
                'name' => 'cName',
                'description' => 'cDescription',
                'metaTitle' => $fieldName('meta_title'),
                'metaDescription' => $fieldName('meta_description'),
            ],
            'saved' => [
                'name' => $page ? (string) $page->getCollectionName() : '',
                'description' => $page ? (string) $page->getCollectionDescription() : '',
                'metaTitle' => $attr('meta_title'),
                'metaDescription' => $attr('meta_description'),
            ],
            'url' => $page ? (string) $page->getCollectionLink() : '',
            'site' => [
                'name' => $site ? (string) $site->getSiteName() : '',
                'titleFormat' => (string) $this->app->make('config')->get('concrete.seo.title_format', '%1$s :: %2$s'),
            ],
            'images' => [
                'og' => $page && $site ? $social->pageImageUrl($page, $site) : '',
                'twitter' => $page && $site ? $social->imageUrl($page, $site) : '',
            ],
            'limits' => [
                'titleMaxPx' => self::TITLE_MAX_PX,
                'titleMinPx' => self::TITLE_MIN_PX,
                'descriptionMaxPx' => self::DESCRIPTION_MAX_PX,
                'descriptionMinChars' => self::DESCRIPTION_MIN_CHARS,
            ],
            'messages' => [
                'titleLong' => t('The title is about %s px wide; Google will probably cut it off (limit around %s px).'),
                'titleShort' => t('The title is short; there is room to describe the page.'),
                'titleDefault' => t('No Meta Title is set, so Google will use the page name with the site title format.'),
                'descriptionMissing' => t('There is no description, so Google will choose text from the page.'),
                'descriptionShort' => t('The description is short (%s characters).'),
                'descriptionLong' => t('The description is about %s px wide; Google will probably cut it off (limit around %s px).'),
                'descriptionDiffers' => t('Open Graph (Facebook, LinkedIn) uses the page description, but X uses the Meta Description.'),
                'ogNoImage' => t('Open Graph has no image: core uses only the page Thumbnail.'),
                'twitterNoImage' => t('X has no image: set a Thumbnail on the page or a Default Sharing Image site attribute.'),
                'allGood' => t('No problems found.'),
                'chars' => t('%s characters, about %s px'),
                'title' => t('Title'),
                'description' => t('Description'),
                'noImage' => t('No image'),
            ],
        ];
    }

    public function getIconFormatter()
    {
        return new FontAwesomeIconFormatter('eye');
    }

    public function createAttributeValue(mixed $value): ?TextValue
    {
        $attributeValue = new TextValue();
        $attributeValue->setValue('1');

        return $attributeValue;
    }

    public function createAttributeValueFromRequest()
    {
        return $this->createAttributeValue('1');
    }

    public function validateForm($data)
    {
        return true;
    }

    public function getDisplayValue(): string
    {
        return '';
    }

    public function form(): void
    {
        $this->set('config', $this->config());
    }

    public function composer(): void
    {
        $this->form();
    }
}
