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
use SeoKit\Providers\Social\SocialServiceProvider;
use ClassKit\Package\Traits\{AttributeTrait, PageTrait};
use Concrete\Core\Entity\Attribute\Value\Value\SelectValueOption;
use Concrete\Core\Application\UserInterface\Dashboard\Navigation\NavigationCache;

class Controller extends PackageController
{
    use AttributeTrait;
    use PageTrait;

    protected $pkgHandle = 'seo_kit';

    protected $pkgVersion = '0.5.0';

    protected $appVersionRequired = '9.5.0';

    protected $phpVersionRequired = '8.4';

    protected $providers = [
        JsonLdServiceProvider::class,
        SocialServiceProvider::class,
    ];

    protected $packageDependencies = [
        'class_kit' => true,
        'lgt_toolkit' => true,
    ];

    protected $pkgAutoloaderRegistries = [
        'src' => '\SeoKit',
    ];

    /**
     * Tables dropped on uninstall when "purge database tables" is ticked, children before parents.
     *
     * @var array
     */
    protected $databaseTables = [
        'seoKitAuditFindings',
        'seoKitAuditRuns',
    ];

    /**
     * Package tasks to register (handles must match tasks.xml).
     *
     * @var array
     */
    protected $tasks = [
        'seo_kit_audit' => \SeoKit\Command\Task\Controller\AuditController::class,
    ];

    protected function setupAttributes(PackageEntity $pkg): void
    {
        $seoSet = $this->addAttributeSet('collection', 'seo_kit', t('SEO Kit'), $pkg);

        $this->addAttributeType('seo_schema', t('Schema'), $pkg);
        $this->addAttribute('seo_schema', t('Schema'), 'seo_schema', CollectionKey::class, $seoSet, $pkg);
        $this->addAttribute('seo_jsonld_custom', t('Custom JSON-LD'), 'textarea', CollectionKey::class, $seoSet, $pkg);

        $this->addAttributeType('seo_preview', t('Search & Social Preview'), $pkg);
        $this->addAttribute('seo_preview', t('Search & Social Preview'), 'seo_preview', CollectionKey::class, $seoSet, $pkg);

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
            PageEvent::useOpenGraph();
            PageEvent::addJsonLd();
            PageEvent::addRobots();
        });

        // core hands over the meta tags here, before printing them
        Events::addListener('on_header_required_ready', function ($event) {
            PageEvent::addTwitterCard($event);
        });
    }

    /**
     * Idempotent: runs on both install and upgrade.
     */
    public function installOrUpgrade(PackageEntity $pkg): void
    {
        $this->installContentFile('tasks.xml');

        $this->addSinglePage('/dashboard/seo_kit', $pkg, t('SEO Kit'));
        $this->addSinglePage('/dashboard/seo_kit/settings', $pkg, t('Structured Data'), t('Organization details and JSON-LD output.'));
        $this->addSinglePage('/dashboard/seo_kit/audit', $pkg, t('Audit'), t('Check pages for SEO problems and track the score over time.'));

        $this->setupAttributes($pkg);

        // Concrete keeps each user's dashboard menu in their session, so a new page would not show until they next
        // log in. This clears it for whoever runs the install or update (a no-op from the command line).
        try {
            $this->app->make(NavigationCache::class)->clear();
        } catch (\Throwable) {
            // the menu is rebuilt at the next login anyway
        }
    }
}
