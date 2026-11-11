<?php

namespace Concrete\Package\SeoKit\Controller\SinglePage\Dashboard\SeoKit;

use SeoKit\JsonLd\{OrganizationSchema, SchemaBuilder};
use Concrete\Core\Page\Controller\DashboardPageController;

class Settings extends DashboardPageController
{
    protected $helpers = ['form'];

    protected function config()
    {
        return $this->app->make('site')->getActiveSiteForEditing()->getConfigRepository();
    }

    public function view(): void
    {
        $config = $this->config();

        $this->set('orgTypes', OrganizationSchema::TYPES);
        $this->set('toggleable', SchemaBuilder::TOGGLEABLE);
        $this->set('type', (string) $config->get('seo_kit.jsonld.type'));
        $this->set('logoID', (int) $config->get('seo_kit.jsonld.logo_id'));
        $this->set('openingHours', (string) $config->get('seo_kit.jsonld.opening_hours'));
        $this->set('disabled', (array) $config->get('seo_kit.jsonld.disabled', []));
    }

    public function save(): void
    {
        if (!$this->request->isPost() || !$this->token->validate('seo_kit_settings')) {
            $this->error->add($this->token->getErrorMessage());
            $this->view();

            return;
        }

        $post = $this->request->request;
        $type = (string) $post->get('type');

        $config = $this->config();
        $config->save('seo_kit.jsonld.type', in_array($type, OrganizationSchema::TYPES, true) ? $type : 'Organization');
        $config->save('seo_kit.jsonld.logo_id', (int) $post->get('logo_id'));
        $config->save('seo_kit.jsonld.opening_hours', trim((string) $post->get('opening_hours')));
        $config->save(
            'seo_kit.jsonld.disabled',
            array_values(array_intersect(SchemaBuilder::TOGGLEABLE, (array) $post->get('disabled'))),
        );

        $this->flash('success', t('Settings saved.'));
        $this->redirect('/dashboard/seo_kit/settings');
    }
}
