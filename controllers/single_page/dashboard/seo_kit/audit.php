<?php

namespace Concrete\Package\SeoKit\Controller\SinglePage\Dashboard\SeoKit;

use SeoKit\Audit\{AuditCsv, AuditService};
use Symfony\Component\HttpFoundation\Response;
use Concrete\Core\Page\Controller\DashboardPageController;

class Audit extends DashboardPageController
{
    /**
     * Pages listed on the dashboard; the CSV has all of them.
     */
    public const PAGE_LIMIT = 50;

    public function view(): void
    {
        $service = $this->app->make(AuditService::class);
        $latest = $service->latestComplete();
        $report = $latest ? $service->report($latest) : null;

        $this->set('latest', $latest);
        $this->set('report', $report);
        $this->set('pageLimit', self::PAGE_LIMIT);
        // oldest first, so the trend reads left to right
        $this->set('runs', array_reverse($service->recentRuns()));
        $this->set('exportToken', $this->app->make('token')->generate('seo_kit_audit_export'));
    }

    /**
     * Runs the same audit as the automation task, in this request.
     */
    public function run(): void
    {
        $token = $this->app->make('token');

        if (!$token->validate('seo_kit_audit_run')) {
            $this->error->add($token->getErrorMessage());
            $this->view();

            return;
        }

        try {
            // shortcut: runs in the request, so a very large site could hit the web timeout; use the Automation task then
            @set_time_limit(300);
            $run = $this->app->make(AuditService::class)->run();
        } catch (\Throwable $e) {
            try {
                $this->app->make(\Psr\Log\LoggerInterface::class)->error('SEO Kit audit failed: ' . $e->getMessage());
            } catch (\Throwable) {
                // nothing more can be done if logging fails too
            }

            $this->error->add(t('The audit could not finish. Details are in the Concrete log.'));
            $this->view();

            return;
        }

        $this->flash('success', t('Audited %1$s pages: score %2$s.', $run->getPagesScanned(), $run->getScore()));
        $this->buildRedirect('/dashboard/seo_kit/audit')->send();
    }

    /**
     * Downloads the latest audit as a CSV.
     */
    public function export($token = null)
    {
        if (!$this->app->make('token')->validate('seo_kit_audit_export', (string) $token)) {
            $this->error->add($this->app->make('token')->getErrorMessage());
            $this->view();

            return null;
        }

        $service = $this->app->make(AuditService::class);
        $run = $service->latestComplete();

        if ($run === null) {
            $this->error->add(t('There is no audit to export yet.'));
            $this->view();

            return null;
        }

        // name the file after the canonical URL's host, or the site name when no canonical URL is set
        $site = $this->app->make('site')->getSite();
        $label = (string) parse_url((string) $site->getSiteCanonicalURL(), PHP_URL_HOST) ?: (string) $site->getSiteName();
        $label = trim(preg_replace('/[^a-z0-9.]+/', '-', strtolower($label)), '-.') ?: 'site';

        // the byte order mark makes Excel read the file as UTF-8
        return new Response("\xEF\xBB\xBF" . AuditCsv::build($service->csvRows($run)), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="seo-audit-' . $label . '-' . gmdate('Y-m-d', $run->getStartedAt()) . '.csv"',
        ]);
    }
}
