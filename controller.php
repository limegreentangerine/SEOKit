<?php

namespace Concrete\Package\SeoKit;

use Events;
use SeoKit\Events\Page as PageEvent;
use ClassKit\Package\PackageController;
use Concrete\Core\Attribute\Key\CollectionKey;
use Concrete\Core\Entity\Package as PackageEntity;
use SeoKit\Providers\JsonLd\JsonLdServiceProvider;
use ClassKit\Package\Traits\{AttributeTrait, PageTrait};

class Controller extends PackageController
{
    use AttributeTrait;
    use PageTrait;

    protected $pkgHandle = 'seo_kit';

    protected $pkgVersion = '0.1.2';

    protected $appVersionRequired = '9.5.0';

    protected $phpVersionRequired = '8.4';

    protected $providers = [
        JsonLdServiceProvider::class,
    ];

    protected $packageDependencies = [
        'class_kit' => true,
        // Organization details come from the toolkit's site attributes.
        'lgt_toolkit' => true,
    ];

    protected $pkgAutoloaderRegistries = [
        'src' => '\SeoKit',
    ];

    protected function setupAttributes(PackageEntity $pkg): void
    {
        $seoSet = $this->addAttributeSet('collection', 'seo_kit', t('SEO Kit'), $pkg);

        $this->addAttribute('seo_jsonld_custom', t('Custom JSON-LD'), 'textarea', CollectionKey::class, $seoSet, $pkg);
    }

    public function getPackageName()
    {
        return t('SEO Kit');
    }

    public function getPackageDescription()
    {
        return t('Structured data and SEO tools for Concrete CMS');
    }

    public function registerRoutes(): void {}

    public function registerEvents(): void
    {
        Events::addListener('on_before_render', function () {
            PageEvent::addJsonLd();
        });
    }

    /**
     * Idempotent: runs on both install and upgrade.
     */
    public function installOrUpgrade(PackageEntity $pkg): void
    {
        $this->addSinglePage('/dashboard/seo_kit', $pkg, t('SEO Kit'));
        $this->addSinglePage('/dashboard/seo_kit/settings', $pkg, t('Structured Data'), t('Organization details and JSON-LD output.'));

        $this->setupAttributes($pkg);
    }
}
