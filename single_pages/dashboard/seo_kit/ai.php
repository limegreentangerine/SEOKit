<?php defined('C5_EXECUTE') or die('Access Denied.');

/** @var array $settings */
/** @var array<string, array> $bots */
/** @var string|null $robotsPreview */
/** @var bool $robotsExists */
/** @var bool $robotsWritable */
/** @var string $llmsPreview */
/** @var string $llmsUrl */
/** @var bool $llmsFileExists */
/** @var bool $behindCloudflare */

$uses = ['training' => t('Training'), 'search' => t('AI search'), 'user' => t('Fetching a page for a person')];
?>

<div class="alert alert-secondary">
    <p class="mb-2"><strong><?php echo t('robots.txt is a request, not a lock.'); ?></strong>
        <?php echo t('Well-behaved crawlers follow it; others ignore it. Blocking a training bot does not stop an assistant fetching a page a person asked about, which is why those are listed separately.'); ?></p>
    <p class="mb-0"><?php echo t('If the site is behind a CDN such as Cloudflare, its bot settings work separately and can block a bot this page allows. A managed robots.txt there may also add rules of its own. This page cannot see those settings.'); ?>
        <?php if ($behindCloudflare) { ?>
            <strong><?php echo t('Your request to this page came through Cloudflare.'); ?></strong>
        <?php } ?></p>
</div>

<?php if ($robotsExists && !$robotsWritable) { ?>
    <div class="alert alert-warning"><?php echo t('robots.txt exists but is not writable by the web server, so saving cannot update it. Copy the text from the preview in by hand, or fix the file permissions.'); ?></div>
<?php } elseif (!$robotsExists) { ?>
    <div class="alert alert-info"><?php echo t('There is no robots.txt yet. Saving creates one that keeps crawlers out of the dashboard and adds your rules.'); ?></div>
<?php } ?>

<form method="post" action="<?php echo h((string) $view->action('save')); ?>">
    <?php Core::make('token')->output('seo_kit_ai_save'); ?>

    <h3><?php echo t('Block AI crawlers'); ?></h3>
    <?php foreach ($uses as $use => $heading) { ?>
        <fieldset class="mb-3">
            <legend class="fs-6 fw-bold"><?php echo h($heading); ?></legend>
            <?php foreach ($bots as $handle => $bot) {
                if ($bot['use'] !== $use) {
                    continue;
                } ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="blocked[]" id="bot-<?php echo h($handle); ?>" value="<?php echo h($handle); ?>" <?php echo in_array($handle, $settings['blocked'], true) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="bot-<?php echo h($handle); ?>"><?php echo h($bot['label']); ?> <code class="small"><?php echo h(implode(', ', $bot['agents'])); ?></code></label>
                </div>
            <?php } ?>
        </fieldset>
    <?php } ?>

    <div class="mb-3">
        <label class="form-label" for="seo-kit-custom"><?php echo t('Other rules'); ?></label>
        <textarea class="form-control font-monospace" id="seo-kit-custom" name="custom" rows="5" maxlength="5000" placeholder="User-agent: *&#10;Disallow: /private/"><?php echo h($settings['custom']); ?></textarea>
        <div class="form-text"><?php echo t('Added to robots.txt as typed, after the bot rules. Lines you already have outside the SEO Kit block are never changed.'); ?></div>
    </div>

    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="sitemap" id="seo-kit-sitemap" value="1" <?php echo $settings['sitemap'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="seo-kit-sitemap"><?php echo t('Add a Sitemap line when sitemap.xml exists'); ?></label>
    </div>

    <h3><?php echo t('llms.txt'); ?></h3>
    <p class="text-muted"><?php echo t('A short Markdown summary of the site for language models, served at %s. It lists the pages core would put in the sitemap, except noindex pages and pages with "Hide from llms.txt" ticked.', '<code>/llms.txt</code>'); ?></p>
    <?php if ($llmsFileExists) { ?>
        <div class="alert alert-warning"><?php echo t('A physical llms.txt file exists in the web root, and the web server serves it instead of this one.'); ?></div>
    <?php } ?>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="llms" id="seo-kit-llms" value="1" <?php echo $settings['llms'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="seo-kit-llms"><?php echo t('Publish llms.txt'); ?><?php echo $settings['llms'] ? ' (<a href="' . h($llmsUrl) . '" target="_blank" rel="noopener">' . h($llmsUrl) . '</a>)' : ''; ?></label>
    </div>
    <div class="mb-4">
        <label class="form-label" for="seo-kit-intro"><?php echo t('Introduction'); ?></label>
        <textarea class="form-control" id="seo-kit-intro" name="intro" rows="3" maxlength="1000"><?php echo h($settings['intro']); ?></textarea>
        <div class="form-text"><?php echo t('One short paragraph about what the site is and who it is for.'); ?></div>
    </div>

    <button type="submit" class="btn btn-primary"><?php echo t('Save'); ?></button>
</form>

<h3 class="mt-5"><?php echo t('Preview'); ?></h3>
<p class="text-muted"><?php echo t('Built from the saved settings. Unsaved changes above are not shown.'); ?></p>
<h4 class="fs-6"><?php echo t('robots.txt after saving'); ?></h4>
<?php if ($robotsPreview === null) { ?>
    <div class="alert alert-warning"><?php echo t('robots.txt has an unmatched "# BEGIN SEO Kit" or "# END SEO Kit" line, so it cannot be updated safely. Fix or remove it first.'); ?></div>
<?php } else { ?>
    <pre class="border rounded p-3 overflow-auto"><?php echo h($robotsPreview); ?></pre>
<?php } ?>
<h4 class="fs-6"><?php echo t('llms.txt'); ?><?php echo $settings['llms'] ? '' : ' ' . t('(not published)'); ?></h4>
<pre class="border rounded p-3 overflow-auto"><?php echo h($llmsPreview); ?></pre>
