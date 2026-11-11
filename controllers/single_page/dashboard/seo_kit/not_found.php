<?php

namespace Concrete\Package\SeoKit\Controller\SinglePage\Dashboard\SeoKit;

use SeoKit\NotFound\NotFoundService;
use Concrete\Core\Page\Controller\DashboardPageController;

class NotFound extends DashboardPageController
{
    public function view(): void
    {
        $service = $this->app->make(NotFoundService::class);
        $search = trim((string) $this->request->query->get('q', ''));
        $page = max(1, (int) $this->request->query->get('page', 1));
        $result = $service->list($search, $page);

        $this->set('rows', $result['rows']);
        $this->set('total', $result['total']);
        $this->set('page', $page);
        $this->set('pages', max(1, (int) ceil($result['total'] / NotFoundService::PER_PAGE)));
        $this->set('search', $search);
        $this->set('canRedirect', $service->canRedirect());
        $this->set('keepDays', NotFoundService::KEEP_DAYS);
    }

    /**
     * Turns a logged 404 into a redirect, using the toolkit's redirection tool.
     */
    public function redirect(): void
    {
        $token = $this->app->make('token');

        if (!$token->validate('seo_kit_404_redirect')) {
            $this->error->add($token->getErrorMessage());
            $this->view();

            return;
        }

        $post = $this->request->request;
        $cID = (int) $post->get('cID');

        if ($cID < 1) {
            $this->error->add(t('Choose the page to redirect to.'));
            $this->view();

            return;
        }

        $message = $this->app->make(NotFoundService::class)->createRedirect((int) $post->get('id'), $cID, (string) $post->get('code', '301'));

        if ($message !== null) {
            $this->error->add($message);
            $this->view();

            return;
        }

        $this->flash('success', t('Redirect created.'));
        $this->buildRedirect('/dashboard/seo_kit/not_found')->send();
    }

    /**
     * Removes an address from the list, for ones that are not worth a redirect.
     */
    public function ignore(): void
    {
        $token = $this->app->make('token');

        if (!$token->validate('seo_kit_404_ignore')) {
            $this->error->add($token->getErrorMessage());
            $this->view();

            return;
        }

        $this->app->make(NotFoundService::class)->dismiss((int) $this->request->request->get('id'));
        $this->buildRedirect('/dashboard/seo_kit/not_found')->send();
    }
}
