<?php defined('C5_EXECUTE') or die('Access Denied.');

use SeoKit\Audit\Issues;

/** @var \SeoKit\Entity\AuditRun|null $latest */
/** @var array|null $report */
/** @var array<int, \SeoKit\Entity\AuditRun> $runs */

$dh = Core::make('helper/date');
$tone = static fn(int $score): string => $score >= 90 ? 'success' : ($score >= 70 ? 'warning' : 'danger');
$severityTone = ['error' => 'danger', 'warning' => 'warning', 'notice' => 'secondary'];
$when = static fn(int $time): string => $dh->formatDateTime((new DateTime('@' . $time)), true);
?>

<form method="post" action="<?php echo h((string) $view->action('run')); ?>" class="mb-4">
    <?php Core::make('token')->output('seo_kit_audit_run'); ?>
    <button type="submit" class="btn btn-primary" onclick="this.disabled = true; this.form.submit();"><?php echo t('Run audit'); ?></button>
    <span class="text-muted ms-2"><?php echo t('Audits every published page now. This can take a while on large sites.'); ?></span>
</form>

<?php if ($latest === null) { ?>
    <div class="alert alert-info">
        <p class="mb-2"><strong><?php echo t('No audit has run yet.'); ?></strong></p>
        <p class="mb-0">
            <?php echo t('Run the "Audit the site for search engines" task from %s, or from the command line with %s. Results appear here when it finishes.', '<a href="' . h((string) URL::to('/dashboard/system/automation/tasks')) . '">' . t('Automation') . '</a>', '<code>./vendor/bin/concrete task:audit-the-site-for-search-engines</code>'); ?>
        </p>
    </div>
<?php } else { ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="display-3 text-<?php echo $tone($latest->getScore()); ?>"><?php echo (int) $latest->getScore(); ?></div>
                    <div class="text-muted"><?php echo t('Site score out of 100'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card h-100">
                <div class="card-body">
                    <p class="mb-2">
                        <?php echo t('Audited %s pages on %s.', (int) $latest->getPagesScanned(), h($when($latest->getFinishedAt() ?? $latest->getStartedAt()))); ?>
                    </p>
                    <p class="mb-3">
                        <span class="badge text-bg-danger"><?php echo t2('%d error', '%d errors', $latest->getErrorCount(), $latest->getErrorCount()); ?></span>
                        <span class="badge text-bg-warning"><?php echo t2('%d warning', '%d warnings', $latest->getWarningCount(), $latest->getWarningCount()); ?></span>
                        <span class="badge text-bg-secondary"><?php echo t2('%d notice', '%d notices', $latest->getNoticeCount(), $latest->getNoticeCount()); ?></span>
                    </p>
                    <a class="btn btn-secondary btn-sm" href="<?php echo h($view->action('export', $exportToken)); ?>"><?php echo t('Download CSV'); ?></a>
                </div>
            </div>
        </div>
    </div>

    <?php if (count($runs) > 1) {
        $count = count($runs);
        $points = [];
        foreach ($runs as $i => $run) {
            $points[] = round(10 + $i * (280 / ($count - 1)), 1) . ',' . round(10 + (100 - $run->getScore()) * 0.6, 1);
        } ?>
        <h3><?php echo t('Score over time'); ?></h3>
        <div class="mb-4">
            <svg viewBox="0 0 300 80" width="300" height="80" role="img" aria-label="<?php echo h(t('Site score for the last %s audits', $count)); ?>">
                <polyline fill="none" stroke="currentColor" stroke-width="2" points="<?php echo h(implode(' ', $points)); ?>" />
                <?php foreach ($points as $point) {
                    [$x, $y] = explode(',', $point); ?>
                    <circle cx="<?php echo h($x); ?>" cy="<?php echo h($y); ?>" r="3" fill="currentColor" />
                <?php } ?>
            </svg>
            <table class="table table-sm mt-2">
                <thead>
                    <tr>
                        <th><?php echo t('Run'); ?></th>
                        <th><?php echo t('Score'); ?></th>
                        <th><?php echo t('Pages'); ?></th>
                        <th><?php echo t('Errors'); ?></th>
                        <th><?php echo t('Warnings'); ?></th>
                        <th><?php echo t('Notices'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($runs) as $run) { ?>
                        <tr>
                            <td><?php echo h($when($run->getStartedAt())); ?><?php echo $run->getStatus() === 'complete' ? '' : ' (' . h($run->getStatus()) . ')'; ?></td>
                            <td><?php echo $run->getStatus() === 'complete' ? (int) $run->getScore() : '–'; ?></td>
                            <td><?php echo (int) $run->getPagesScanned(); ?></td>
                            <td><?php echo (int) $run->getErrorCount(); ?></td>
                            <td><?php echo (int) $run->getWarningCount(); ?></td>
                            <td><?php echo (int) $run->getNoticeCount(); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>

    <?php if ($report['byCode'] === []) { ?>
        <div class="alert alert-success"><?php echo t('No problems found.'); ?></div>
    <?php } else { ?>
        <h3><?php echo t('Problems by type'); ?></h3>
        <table class="table table-sm mb-4">
            <tbody>
                <?php foreach ($report['byCode'] as $code => $info) { ?>
                    <tr>
                        <td style="width:6rem"><span class="badge text-bg-<?php echo $severityTone[$info['severity']] ?? 'secondary'; ?>"><?php echo h(ucfirst($info['severity'])); ?></span></td>
                        <td><?php echo h(Issues::summary($code)); ?></td>
                        <td class="text-end"><?php echo (int) $info['count']; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <h3><?php echo t('Pages to fix first'); ?></h3>
        <table class="table">
            <thead>
                <tr>
                    <th><?php echo t('Page'); ?></th>
                    <th style="width:5rem"><?php echo t('Score'); ?></th>
                    <th><?php echo t('Problems'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($report['pages'], 0, $pageLimit) as $page) { ?>
                    <tr>
                        <td>
                            <a href="<?php echo h((string) URL::to($page['pagePath'])); ?>"><?php echo h($page['pageName'] !== '' ? $page['pageName'] : $page['pagePath']); ?></a>
                            <div class="text-muted small"><?php echo h($page['pagePath']); ?></div>
                        </td>
                        <td><span class="badge text-bg-<?php echo $tone($page['score']); ?>"><?php echo (int) $page['score']; ?></span></td>
                        <td>
                            <?php foreach ($page['findings'] as $finding) {
                                $text = Issues::describe($finding['code'], $finding['detail']); ?>
                                <div class="mb-1">
                                    <span class="badge text-bg-<?php echo $severityTone[$finding['severity']] ?? 'secondary'; ?>"><?php echo h(ucfirst($finding['severity'])); ?></span>
                                    <?php echo h($text['label']); ?>
                                    <div class="text-muted small"><?php echo h($text['advice']); ?></div>
                                </div>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php if (count($report['pages']) > $pageLimit) { ?>
            <p class="text-muted"><?php echo t('Showing the %s lowest scoring of %s pages with problems. The CSV has all of them.', (int) $pageLimit, count($report['pages'])); ?></p>
        <?php } ?>
    <?php } ?>
<?php } ?>
