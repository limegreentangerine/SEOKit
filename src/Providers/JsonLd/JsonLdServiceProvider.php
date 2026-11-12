<?php

namespace SeoKit\Providers\JsonLd;

use SeoKit\JsonLd\JsonLdService;
use Concrete\Core\Foundation\Service\Provider;

/**
 * Registers the JSON-LD service within the application container.
 */
class JsonLdServiceProvider extends Provider
{
    public function register()
    {
        $this->app->singleton(JsonLdService::class);
        $this->app->alias(JsonLdService::class, 'seo_kit_jsonld');
    }
}
