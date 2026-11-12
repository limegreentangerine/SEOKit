<?php

namespace SeoKit\Providers\Social;

use SeoKit\Social\SocialService;
use Concrete\Core\Foundation\Service\Provider;

/**
 * Registers the social service. Core's Open Graph class is swapped for the SEO Kit one later, not here: see
 * SeoKit\Events\Page::useOpenGraph().
 */
class SocialServiceProvider extends Provider
{
    public function register()
    {
        $this->app->singleton(SocialService::class);
        $this->app->alias(SocialService::class, 'seo_kit_social');
    }
}
