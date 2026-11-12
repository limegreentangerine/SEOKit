<?php defined('C5_EXECUTE') or die('Access Denied.');

$fileManager = Core::make('helper/concrete/file_manager');
$logo = $logoID ? \Concrete\Core\File\File::getByID($logoID) : null;
?>

<form method="post" action="<?php echo $view->action('save'); ?>">
    <?php $token->output('seo_kit_settings'); ?>

    <fieldset>
        <legend><?php echo t('Structured data (JSON-LD)'); ?></legend>

        <div class="mb-3">
            <?php echo $form->label('type', t('Organization type')); ?>
            <?php echo $form->select('type', array_combine($orgTypes, $orgTypes), $type); ?>
        </div>

        <div class="mb-3">
            <?php echo $form->label('logo_id', t('Logo')); ?>
            <?php echo $fileManager->image('seo-kit-logo', 'logo_id', t('Choose logo'), $logo ?: null); ?>
            <div class="form-text"><?php echo t('Falls back to the Default Sharing Image site attribute from LGT Toolkit.'); ?></div>
        </div>

        <div class="mb-3">
            <?php echo $form->label('', t('Social profiles')); ?>
            <div class="form-text">
                <?php echo t('Output as sameAs from the site\'s Social Links. Manage them under %s.', '<a href="' . h((string) URL::to('/dashboard/system/social/social_links')) . '">' . t('Social Links') . '</a>'); ?>
            </div>
        </div>

        <div class="mb-3">
            <?php echo $form->label('opening_hours', t('Opening hours (Local Business only, one per line)')); ?>
            <?php echo $form->textarea('opening_hours', $openingHours, ['rows' => 4, 'placeholder' => 'Mo-Fr 09:00-17:00']); ?>
        </div>

        <div class="mb-3">
            <?php echo $form->label('', t('Do not output these nodes')); ?>
            <?php foreach ($toggleable as $node) { ?>
                <div class="form-check">
                    <?php echo $form->checkbox('disabled[]', $node, in_array($node, $disabled, true), ['id' => 'disabled-' . $node]); ?>
                    <label class="form-check-label" for="disabled-<?php echo h($node); ?>"><?php echo h($node); ?></label>
                </div>
            <?php } ?>
            <div class="form-text"><?php echo t('Tick a node if your theme or another package already outputs it.'); ?></div>
        </div>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <button type="submit" class="btn btn-primary float-end"><?php echo t('Save'); ?></button>
        </div>
    </div>
</form>
