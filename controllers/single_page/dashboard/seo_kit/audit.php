<?php

namespace Concrete\Package\SeoKit\Controller\SinglePage\Dashboard\SeoKit;

use SeoKit\Audit\AuditCsv;
use SeoKit\Audit\AuditService;
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

        // the byte order mark makes Excel read the file as UTF-8
        return new Response("\xEF\xBB\xBF" . AuditCsv::build($service->csvRows($run)), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="seo-audit-' . gmdate('Y-m-d', $run->getStartedAt()) . '.csv"',
        ]);
    }
}
