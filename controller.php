<?php

namespace Concrete\Package\SeoKit;

use Events;
use SeoKit\Robots\RobotsMeta;
use SeoKit\Events\Page as PageEvent;
use ClassKit\Package\PackageController;
use Doctrine\ORM\EntityManagerInterface;
use Concrete\Core\Attribute\Key\CollectionKey;
use Concrete\Core\Attribute\Type as AttributeType;
use Concrete\Core\Entity\Package as PackageEntity;
use SeoKit\Providers\JsonLd\JsonLdServiceProvider;
use ClassKit\Package\Traits\{AttributeTrait, PageTrait};
use Concrete\Core\Entity\Attribute\Value\Value\SelectValueOption;

class Controller extends PackageController
{
    use AttributeTrait;
    use PageTrait;

    protected $pkgHandle = 'seo_kit';

    protected $pkgVersion = '0.1.4';

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

        $this->setupRobotsAttribute($pkg, $seoSet);
    }

    /**
     * Multi-select of robots directives. Safe to run repeatedly: it creates what is missing and repairs the rest.
     * Built here rather than with class_kit's addSelectAttribute(), which passes plain strings to
     * Select\Controller::setOptions() (it needs option entities) and fails.
     * noindex is left to core's "Exclude From Search Index" attribute.
     */
    protected function setupRobotsAttribute(PackageEntity $pkg, object $seoSet): void
    {
        $em = $this->app->make(EntityManagerInterface::class);

        $key = CollectionKey::getByHandle('seo_robots') ?: CollectionKey::add(
            AttributeType::getByHandle('select'),
            ['akHandle' => 'seo_robots', 'akName' => t('Robots Directives'), 'akIsSearchable' => true],
            $pkg,
        );

        $settings = $key->getAttributeKeySettings();
        $settings->setAllowMultipleValues(true);
        $settings->setAllowOtherValues(false);

        $list = $settings->getOptionList();
        $existing = array_map(
            static fn($option) => $option->getSelectAttributeOptionValue(),
            $list->getOptions()->toArray(),
        );

        foreach (array_diff(RobotsMeta::DIRECTIVES, $existing) as $value) {
            $option = new SelectValueOption();
            $option->setSelectAttributeOptionValue($value);
            $option->setOptionList($list);
            $list->getOptions()->add($option);
        }

        $em->persist($settings);

        $inSet = array_filter(
            $seoSet->getAttributeKeys(),
            static fn($setKey) => $setKey->getAttributeKeyHandle() === 'seo_robots',
        );

        if ($inSet === []) {
            $seoSet->addKey($key);
            $em->persist($seoSet);
        }

        $em->flush();
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
            PageEvent::addRobots();
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
