<?php defined('C5_EXECUTE') or die('Access Denied.');

/** @var array $sitemap */
/** @var array<int, array> $rows */
/** @var \Concrete\Core\Search\Pagination\Pagination $pagination */
/** @var string $keywords */
/** @var int $perPage */
/** @var array<int, int> $perPageChoices */
/** @var array<int, string> $frequencies */

$dh = Core::make('helper/date');
$tasks = h((string) URL::to('/dashboard/system/automation/tasks'));
?>

<div class="card mb-4">
    <div class="card-body">
        <?php if ($sitemap['exists']) { ?>
            <p class="mb-2">
                <span class="badge text-bg-success"><?php echo t('Found'); ?></span>
                <a href="<?php echo h($sitemap['url']); ?>" target="_blank" rel="noopener"><?php echo h($sitemap['file']); ?></a>
                <?php echo t2('lists %d page', 'lists %d pages', $sitemap['urls'], $sitemap['urls']); ?>,
                <?php echo t('last generated %s.', h($dh->formatDateTime(new DateTime('@' . $sitemap['modified']), true))); ?>
            </p>
        <?php } else { ?>
            <p class="mb-2"><span class="badge text-bg-danger"><?php echo t('Missing'); ?></span> <?php echo t('%s has not been generated yet.', '<code>' . h($sitemap['file']) . '</code>'); ?></p>
        <?php } ?>

        <?php if ($sitemap['canonical'] === '') { ?>
            <p class="mb-2 text-warning"><?php echo t('The canonical URL is not set (Dashboard > System & Settings > SEO & Statistics), so the sitemap may list addresses from whatever host ran the task.'); ?></p>
        <?php } ?>

        <p class="mb-0 text-muted"><?php echo t('Regenerate it by running "Generate Sitemap" from %s.', '<a href="' . $tasks . '">' . t('Automation') . '</a>'); ?></p>
    </div>
</div>

<div class="row g-2 mb-3 align-items-center">
<form method="get" action="<?php echo h((string) $view->action('view')); ?>" class="col-auto row g-2">
    <div class="col-auto"><input type="search" name="keywords" value="<?php echo h($keywords); ?>" class="form-control" placeholder="<?php echo h(t('Search pages')); ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-secondary"><?php echo t('Search'); ?></button></div>
</form>
<form method="post" action="<?php echo h((string) $view->action('perPage')); ?>" class="col-auto ms-auto d-flex align-items-center gap-2">
    <?php Core::make('token')->output('seo_kit_sitemap_per_page'); ?>
    <label for="seo-kit-per-page" class="text-nowrap"><?php echo t('Pages per screen'); ?></label>
    <select id="seo-kit-per-page" name="per_page" class="form-select" style="width: 6rem" onchange="this.form.submit()">
        <?php foreach ($perPageChoices as $choice) { ?>
            <option value="<?php echo $choice; ?>" <?php echo $choice === $perPage ? 'selected' : ''; ?>><?php echo $choice; ?></option>
        <?php } ?>
    </select>
    <noscript><button type="submit" class="btn btn-secondary"><?php echo t('Set'); ?></button></noscript>
</form>
</div>

<?php if ($rows === []) { ?>
    <p class="text-muted"><?php echo t('No pages found.'); ?></p>
<?php } else { ?>
    <form method="post" action="<?php echo h((string) $view->action('save')); ?>">
        <?php Core::make('token')->output('seo_kit_sitemap_save'); ?>
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th><?php echo t('Page'); ?></th>
                    <th><?php echo t('Left out'); ?></th>
                    <th><?php echo t('Priority'); ?></th>
                    <th><?php echo t('Change frequency'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td>
                            <input type="hidden" name="cIDs[]" value="<?php echo $row['id']; ?>">
                            <?php echo h($row['name']); ?>
                            <div class="small text-muted"><?php echo h($row['path'] === '' ? '/' : $row['path']); ?></div>
                        </td>
                        <td>
                            <input type="checkbox" class="form-check-input" name="exclude[<?php echo $row['id']; ?>]" value="1" aria-label="<?php echo h(t('Leave %s out of the sitemap', $row['name'])); ?>" <?php echo $row['excluded'] ? 'checked' : ''; ?>>
                        </td>
                        <td>
                            <input type="text" inputmode="decimal" class="form-control form-control-sm" style="width: 5rem" name="priority[<?php echo $row['id']; ?>]" value="<?php echo h($row['priority']); ?>" placeholder="0.5" aria-label="<?php echo h(t('Priority for %s', $row['name'])); ?>">
                        </td>
                        <td>
                            <select class="form-select form-select-sm" style="width: 9rem" name="changefreq[<?php echo $row['id']; ?>]" aria-label="<?php echo h(t('Change frequency for %s', $row['name'])); ?>">
                                <option value=""><?php echo t('Site default'); ?></option>
                                <?php foreach ($frequencies as $frequency) { ?>
                                    <option value="<?php echo h($frequency); ?>" <?php echo $row['changefreq'] === $frequency ? 'selected' : ''; ?>><?php echo h($frequency); ?></option>
                                <?php } ?>
                            </select>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <p class="text-muted small"><?php echo t('Priority runs from 0.0 to 1.0. Empty values use the site defaults.'); ?></p>
        <button type="submit" class="btn btn-primary"><?php echo t('Save these pages'); ?></button>
    </form>
    <div class="mt-3"><?php echo $pagination->renderDefaultView(); ?></div>
<?php } ?>
