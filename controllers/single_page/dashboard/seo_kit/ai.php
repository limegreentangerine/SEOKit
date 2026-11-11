<?php

namespace Concrete\Package\SeoKit\Controller\SinglePage\Dashboard\SeoKit;

use SeoKit\Robots\RobotsTxt;
use SeoKit\Robots\AiCrawlerService;
use Concrete\Core\Page\Controller\DashboardPageController;

class Ai extends DashboardPageController
{
    public function view(): void
    {
        $service = $this->app->make(AiCrawlerService::class);
        $path = $service->robotsPath();

        $this->set('settings', $service->settings());
        $this->set('bots', RobotsTxt::BOTS);
        $this->set('robotsPreview', $service->preview());
        $this->set('robotsExists', is_file($path));
        $this->set('robotsWritable', is_file($path) ? is_writable($path) : is_writable(dirname($path)));
        $this->set('llmsPreview', $service->llms());
        $this->set('llmsUrl', rtrim((string) $this->app->make('url/manager')->resolve(['llms.txt']), '/'));
        $this->set('llmsFileExists', is_file($service->llmsPath()));
        // only says how this one request reached the site, but it is a good hint that a CDN sits in front
        $this->set('behindCloudflare', $this->request->headers->has('CF-Ray'));
    }

    public function save(): void
    {
        $token = $this->app->make('token');

        if (!$token->validate('seo_kit_ai_save')) {
            $this->error->add($token->getErrorMessage());
            $this->view();

            return;
        }

        $post = $this->request->request;
        $service = $this->app->make(AiCrawlerService::class);

        $service->save(
            (array) $post->all('blocked'),
            (string) $post->get('custom', ''),
            $post->has('sitemap'),
            $post->has('llms'),
            (string) $post->get('intro', ''),
        );

        $result = $service->apply();

        if ($result === AiCrawlerService::DAMAGED) {
            $this->error->add(t('Settings saved, but robots.txt was left alone: it has an unmatched "# BEGIN SEO Kit" or "# END SEO Kit" line. Fix or remove it, then save again.'));
        } elseif ($result === AiCrawlerService::MANUAL) {
            $this->error->add(t('Settings saved, but robots.txt could not be written (check the file permissions). Copy the text under "robots.txt after saving" into the file by hand.'));
        } else {
            $this->flash('success', t('Saved. robots.txt has been updated.'));
            $this->buildRedirect('/dashboard/seo_kit/ai')->send();
        }

        $this->view();
    }
}
