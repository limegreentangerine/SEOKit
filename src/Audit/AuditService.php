<?php

namespace SeoKit\Audit;

use SeoKit\Entity\AuditRun;
use Concrete\Core\Page\Page;
use SeoKit\Entity\AuditFinding;
use SeoKit\JsonLd\CustomJsonLd;
use Concrete\Core\Page\PageList;
use SeoKit\Social\SocialService;
use SeoKit\JsonLd\Types\TypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Concrete\Core\Application\Application;

/**
 * Runs an audit and reads its results. The rules and scoring live in Checks and Scorer; this class gathers each
 * page's data from Concrete, stores the findings, and keeps only the most recent runs.
 */
class AuditService
{
    /**
     * Runs kept; older ones (and their findings) are deleted when a run finishes.
     */
    public const KEEP_RUNS = 10;

    /**
     * Pages read per batch.
     */
    public const BATCH = 250;

    public function __construct(protected Application $app) {}

    /**
     * @return array<int, array> one entry per auditable page, as Checks expects
     */
    protected function collectPages(?callable $progress): array
    {
        $list = new PageList();
        $list->ignorePermissions();
        $list->setSiteTreeToAll();
        $list->setItemsPerPage(self::BATCH);

        $social = $this->app->make(SocialService::class);
        $pager = $list->getPagination();
        $pages = [];

        for ($n = 1; $n <= $pager->getNbPages(); ++$n) {
            $pager->setCurrentPage($n);

            foreach ($pager->getCurrentPageResults() as $page) {
                $snapshot = $page instanceof Page ? $this->snapshot($page, $social) : null;

                if ($snapshot !== null) {
                    $pages[] = $snapshot;
                }
            }

            if ($progress !== null) {
                $progress(t('Audited %s pages', count($pages)));
            }
        }

        return $pages;
    }

    /**
     * The data Checks needs for one page, or null when the page is not one to audit.
     *
     * @return array<string, mixed>|null
     */
    protected function snapshot(Page $page, SocialService $social): ?array
    {
        $site = $page->getSite();

        // core's docblock says getSite() always returns a Site, but pages outside a site tree give null
        // @phpstan-ignore booleanNot.alwaysFalse
        if (!$site || $page->isError() || $page->isSystemPage() || $page->isInTrash() || $page->isExternalLink() || $page->isAlias()) {
            return null;
        }

        $path = (string) $page->getCollectionPath();
        $name = (string) $page->getCollectionName();
        $description = (string) $page->getCollectionDescription();

        // the same minimal page and site data the schema attribute is checked against when a page is rendered
        $schema = TypeRegistry::nodes(
            (string) $page->getAttribute('seo_schema'),
            ['url' => $path, 'name' => $name, 'description' => $description, 'datePublished' => '', 'dateModified' => ''],
            ['url' => '/', 'disabled' => [], 'timezone' => ''],
        );

        return [
            'id' => (int) $page->getCollectionID(),
            'name' => $name,
            'path' => $path === '' ? '/' : $path,
            'siteName' => (string) $site->getSiteName(),
            'titleFormat' => (string) $this->app->make('config')->get('concrete.seo.title_format', '%1$s :: %2$s'),
            'metaTitle' => (string) $page->getAttribute('meta_title'),
            'metaDescription' => (string) $page->getAttribute('meta_description'),
            'description' => $description,
            'noindex' => (bool) $page->getAttribute('exclude_search_index'),
            'excludeSitemap' => (bool) $page->getAttribute('exclude_sitemapxml'),
            'hasSocialImage' => $social->imageUrl($page, $site) !== '',
            'schemaErrors' => $schema['errors'],
            'jsonLdError' => CustomJsonLd::parse((string) $page->getAttribute('seo_jsonld_custom'))['error'],
        ];
    }

    /**
     * Deletes runs beyond the most recent KEEP_RUNS, with their findings.
     */
    protected function prune(): void
    {
        $em = $this->em();
        $old = $em->createQuery('select r.id from ' . AuditRun::class . ' r order by r.id desc')
            ->setFirstResult(self::KEEP_RUNS)
            ->getSingleColumnResult();

        if ($old === []) {
            return;
        }

        $em->createQuery('delete from ' . AuditFinding::class . ' f where f.run in (:ids)')->execute(['ids' => $old]);
        $em->createQuery('delete from ' . AuditRun::class . ' r where r.id in (:ids)')->execute(['ids' => $old]);
    }

    protected function em(): EntityManagerInterface
    {
        return $this->app->make(EntityManagerInterface::class);
    }

    protected static function rank(string $severity): int
    {
        return ['error' => 0, 'warning' => 1, 'notice' => 2][$severity] ?? 3;
    }

    /**
     * Audits every published, public page of every site and stores the result.
     *
     * @param callable(string): void|null $progress told how far it has got
     */
    public function run(?callable $progress = null): AuditRun
    {
        $em = $this->em();
        $run = (new AuditRun())->start(time());
        $em->persist($run);
        $em->flush();

        try {
            $pages = $this->collectPages($progress);
            $findings = Checks::run($pages);
            $summary = Scorer::summarise(count($pages), $findings);
            $byId = array_column($pages, null, 'id');

            foreach (array_chunk($findings, self::BATCH) as $chunk) {
                $entities = [];

                foreach ($chunk as $finding) {
                    $page = $byId[$finding['pageId']];
                    $entities[] = (new AuditFinding())
                        ->setRun($run)
                        ->setPageID($finding['pageId'])
                        ->setPageName($page['name'])
                        ->setPagePath($page['path'])
                        ->setCode($finding['code'])
                        ->setSeverity($finding['severity'])
                        ->setDetail($finding['detail']);
                    $em->persist(end($entities));
                }

                $em->flush();

                foreach ($entities as $entity) {
                    $em->detach($entity);
                }
            }

            $run->setPagesScanned(count($pages))
                ->setScore($summary['score'])
                ->setErrorCount($summary['errors'])
                ->setWarningCount($summary['warnings'])
                ->setNoticeCount($summary['notices'])
                ->finish(AuditRun::COMPLETE, time());
            $em->flush();
        } catch (\Throwable $e) {
            $run->finish(AuditRun::FAILED, time());
            $em->flush();

            throw $e;
        }

        $this->prune();

        return $run;
    }

    public function latestComplete(): ?AuditRun
    {
        $run = $this->em()->getRepository(AuditRun::class)->findOneBy(['status' => AuditRun::COMPLETE], ['id' => 'DESC']);

        return $run instanceof AuditRun ? $run : null;
    }

    /**
     * The most recent runs, newest first.
     *
     * @return array<int, AuditRun>
     */
    public function recentRuns(int $limit = self::KEEP_RUNS): array
    {
        return $this->em()->getRepository(AuditRun::class)->findBy([], ['id' => 'DESC'], $limit);
    }

    /**
     * What the dashboard shows for a run: totals per issue, and every page with findings, worst first.
     *
     * @return array{
     *     byCode: array<string, array{severity: string, count: int}>,
     *     pages: array<int, array{pageID: int, pageName: string, pagePath: string, score: int, findings: array<int, array{code: string, severity: string, detail: string}>}>
     * }
     */
    public function report(AuditRun $run): array
    {
        $findings = $this->em()->getRepository(AuditFinding::class)->findBy(['run' => $run], ['id' => 'ASC']);
        $byCode = [];
        $pages = [];

        foreach ($findings as $finding) {
            $code = $finding->getCode();
            $byCode[$code] = ['severity' => $finding->getSeverity(), 'count' => ($byCode[$code]['count'] ?? 0) + 1];

            $pages[$finding->getPageID()] ??= [
                'pageID' => $finding->getPageID(),
                'pageName' => $finding->getPageName(),
                'pagePath' => $finding->getPagePath(),
                'score' => 100,
                'findings' => [],
            ];
            $pages[$finding->getPageID()]['findings'][] = ['code' => $code, 'severity' => $finding->getSeverity(), 'detail' => $finding->getDetail()];
        }

        foreach ($pages as &$page) {
            $page['score'] = max(0, 100 - array_sum(array_map(
                static fn(array $f): int => Scorer::PENALTIES[$f['severity']] ?? 0,
                $page['findings'],
            )));
        }

        unset($page);

        uasort($byCode, static fn(array $a, array $b): int => [self::rank($a['severity']), $b['count']] <=> [self::rank($b['severity']), $a['count']]);
        usort($pages, static fn(array $a, array $b): int => [$a['score'], $a['pageName']] <=> [$b['score'], $b['pageName']]);

        return ['byCode' => $byCode, 'pages' => $pages];
    }

    /**
     * Rows for the CSV export of a run.
     *
     * @return array<int, array{pageName: string, pagePath: string, pageScore: int, severity: string, code: string, detail: string}>
     */
    public function csvRows(AuditRun $run): array
    {
        $rows = [];

        foreach ($this->report($run)['pages'] as $page) {
            foreach ($page['findings'] as $finding) {
                $rows[] = [
                    'pageName' => $page['pageName'],
                    'pagePath' => $page['pagePath'],
                    'pageScore' => $page['score'],
                    'severity' => $finding['severity'],
                    'code' => $finding['code'],
                    'detail' => $finding['detail'],
                ];
            }
        }

        return $rows;
    }
}
