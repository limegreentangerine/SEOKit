<?php defined('C5_EXECUTE') or die('Access Denied.');

/** @var array<int, array<string, mixed>> $rows */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var string $search */
/** @var bool $canRedirect */
/** @var int $keepDays */

$dh = Core::make('helper/date');
$when = static fn(int $time): string => $dh->formatDateTime(new DateTime('@' . $time), true);
$pageUrl = static fn(int $n): string => h((string) URL::to('/dashboard/seo_kit/not_found') . '?' . http_build_query(['q' => $search, 'page' => $n]));
?>

<p class="text-muted"><?php echo t('Addresses visitors asked for that do not exist, most requested first. Files such as images and scripts, and system paths, are not listed. Entries are dropped after %d days without a request.', $keepDays); ?></p>

<?php if (!$canRedirect) { ?>
    <div class="alert alert-warning"><?php echo t('Creating redirects needs the redirection tool in LGT Toolkit, which this site does not have yet. The list still works.'); ?></div>
<?php } else { ?>
    <form method="post" action="<?php echo h((string) $view->action('redirect')); ?>" id="seo-kit-redirect-form">
        <?php Core::make('token')->output('seo_kit_404_redirect'); ?>
        <input type="hidden" name="id" id="seo-kit-redirect-id" value="">
        <div class="modal fade" id="seo-kit-redirect-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo t('Create a redirect'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo h(t('Close')); ?>"></button>
                    </div>
                    <div class="modal-body">
                        <p><?php echo t('Redirect'); ?> <code id="seo-kit-redirect-path"></code></p>
                        <div class="mb-3">
                            <label class="form-label"><?php echo t('Send visitors to'); ?></label>
                            <?php echo Core::make('helper/form/page_selector')->selectPage('cID'); ?>
                        </div>
                        <div>
                            <label class="form-label" for="seo-kit-code"><?php echo t('Type'); ?></label>
                            <select class="form-select" name="code" id="seo-kit-code">
                                <option value="301"><?php echo t('301 Permanent'); ?></option>
                                <option value="302"><?php echo t('302 Temporary'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('Cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo t('Create redirect'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </form>
<?php } ?>

<form method="post" action="<?php echo h((string) $view->action('ignore')); ?>" id="seo-kit-ignore-form">
    <?php Core::make('token')->output('seo_kit_404_ignore'); ?>
</form>

<form method="get" action="<?php echo h((string) URL::to('/dashboard/seo_kit/not_found')); ?>" class="row g-2 mb-3">
    <div class="col-auto"><input type="search" name="q" value="<?php echo h($search); ?>" class="form-control" placeholder="<?php echo h(t('Search addresses')); ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-secondary"><?php echo t('Search'); ?></button></div>
</form>

<?php if ($rows === []) { ?>
    <p class="text-muted"><?php echo $search === '' ? t('No missing addresses have been recorded yet.') : t('No addresses match.'); ?></p>
<?php } else { ?>
    <table class="table table-sm align-middle">
        <thead>
            <tr>
                <th><?php echo t('Address'); ?></th>
                <th class="text-end"><?php echo t('Requests'); ?></th>
                <th><?php echo t('Last requested'); ?></th>
                <th><?php echo t('Linked from'); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) { ?>
                <tr>
                    <td><code><?php echo h($row['path']); ?></code></td>
                    <td class="text-end"><?php echo (int) $row['hits']; ?></td>
                    <td><?php echo h($when((int) $row['lastSeen'])); ?></td>
                    <td><?php echo h((string) $row['referrerHost']); ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($canRedirect) { ?>
                            <button type="button" class="btn btn-sm btn-primary seo-kit-pick" data-bs-toggle="modal" data-bs-target="#seo-kit-redirect-modal" data-id="<?php echo (int) $row['id']; ?>" data-path="<?php echo h($row['path']); ?>"><?php echo t('Redirect'); ?></button>
                        <?php } ?>
                        <button type="submit" form="seo-kit-ignore-form" name="id" value="<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline-secondary"><?php echo t('Ignore'); ?></button>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <?php if ($pages > 1) { ?>
        <nav class="d-flex gap-2 align-items-center">
            <?php if ($page > 1) { ?><a class="btn btn-sm btn-secondary" href="<?php echo $pageUrl($page - 1); ?>"><?php echo t('Previous'); ?></a><?php } ?>
            <span class="text-muted"><?php echo t('Page %1$d of %2$d (%3$d addresses)', $page, $pages, $total); ?></span>
            <?php if ($page < $pages) { ?><a class="btn btn-sm btn-secondary" href="<?php echo $pageUrl($page + 1); ?>"><?php echo t('Next'); ?></a><?php } ?>
        </nav>
    <?php } ?>
<?php } ?>

<script>
    (function () {
        var id = document.getElementById('seo-kit-redirect-id');
        var label = document.getElementById('seo-kit-redirect-path');

        document.querySelectorAll('.seo-kit-pick').forEach(function (button) {
            button.addEventListener('click', function () {
                id.value = button.getAttribute('data-id');
                label.textContent = button.getAttribute('data-path');
            });
        });
    })();
</script>
