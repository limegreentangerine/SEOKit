<?php

namespace Concrete\Package\SeoKit\Controller\SinglePage\Dashboard\SeoKit;

use Permissions;
use SeoKit\Sitemap\SitemapValues;
use Concrete\Core\Page\{Page, PageList};
use Concrete\Core\Attribute\Key\CollectionKey;
use Concrete\Core\Permission\Key\Key as PermissionKey;
use Concrete\Core\Page\Controller\DashboardPageController;

class Sitemap extends DashboardPageController
{
    public function view(): void
    {
        $file = DIR_BASE . '/' . $this->app->make('config')->get('concrete.sitemap_xml.file', 'sitemap.xml');
        $exists = is_file($file);
        $site = $this->app->make('site')->getSite();

        $keywords = trim((string) $this->request->query->get('keywords', ''));

        $list = new PageList();
        $list->sortByName();
        $perPage = SitemapValues::perPage($site->getConfigRepository()->get('seo_kit.sitemap.per_page'));
        $list->setItemsPerPage($perPage);

        if ($keywords !== '') {
            $list->filterByKeywords($keywords);
        }

        $pagination = $list->getPagination();
        $rows = [];

        foreach ($pagination->getCurrentPageResults() as $page) {
            $rows[] = [
                'id' => (int) $page->getCollectionID(),
                'name' => (string) $page->getCollectionName(),
                'path' => (string) $page->getCollectionPath(),
                'excluded' => (bool) $page->getAttribute('exclude_sitemapxml'),
                'priority' => (string) $page->getAttribute('sitemap_priority'),
                'changefreq' => (string) $page->getAttribute('sitemap_changefreq'),
            ];
        }

        $this->set('sitemap', [
            'file' => basename($file),
            'exists' => $exists,
            'modified' => $exists ? (int) filemtime($file) : null,
            'urls' => $exists ? SitemapValues::countUrls($file) : 0,
            'canonical' => (string) $site->getConfigRepository()->get('seo.canonical_url', ''),
            'url' => $exists ? (string) $this->app->make('url/manager')->resolve([basename($file)]) : '',
        ]);
        $this->set('rows', $rows);
        $this->set('pagination', $pagination);
        $this->set('keywords', $keywords);
        $this->set('perPage', $perPage);
        $this->set('perPageChoices', SitemapValues::PER_PAGE_CHOICES);
        $this->set('frequencies', SitemapValues::CHANGE_FREQUENCIES);
    }

    /**
     * Remembers how many pages to list at once, for the whole site.
     */
    public function perPage(): void
    {
        $token = $this->app->make('token');

        if (!$token->validate('seo_kit_sitemap_per_page')) {
            $this->error->add($token->getErrorMessage());
            $this->view();

            return;
        }

        $site = $this->app->make('site')->getSite();
        $site->getConfigRepository()->save('seo_kit.sitemap.per_page', SitemapValues::perPage($this->request->request->get('per_page')));

        $this->buildRedirect('/dashboard/seo_kit/sitemap')->send();
    }

    /**
     * Saves the listed pages. Only values that changed are written, and a page the user may not edit is skipped.
     */
    public function save(): void
    {
        $token = $this->app->make('token');

        if (!$token->validate('seo_kit_sitemap_save')) {
            $this->error->add($token->getErrorMessage());
            $this->view();

            return;
        }

        $post = $this->request->request;
        $exclude = (array) $post->all('exclude');
        $priority = (array) $post->all('priority');
        $changefreq = (array) $post->all('changefreq');
        $changed = 0;
        $skipped = [];
        $denied = [];

        foreach ((array) $post->all('cIDs') as $cID) {
            $page = Page::getByID((int) $cID);

            if (!is_object($page) || $page->isError() || !(new Permissions($page))->canEditPageProperties()) {
                continue;
            }

            $wantPriority = trim((string) ($priority[$cID] ?? ''));
            $wantFrequency = trim((string) ($changefreq[$cID] ?? ''));
            $cleanPriority = $wantPriority === '' ? '' : SitemapValues::priority($wantPriority);
            $cleanFrequency = $wantFrequency === '' ? '' : SitemapValues::changeFrequency($wantFrequency);

            if ($cleanPriority === null || $cleanFrequency === null) {
                $skipped[] = $page->getCollectionName();

                continue;
            }

            // edit_page_properties can be limited to certain attributes, which canEditPageProperties() does not check
            $permission = PermissionKey::getByHandle('edit_page_properties');
            $permission->setPermissionObject($page);
            $assignment = $permission->getMyAssignment();

            $now = [
                'exclude_sitemapxml' => isset($exclude[$cID]),
                'sitemap_priority' => $cleanPriority,
                'sitemap_changefreq' => $cleanFrequency,
            ];
            $before = [
                'exclude_sitemapxml' => (bool) $page->getAttribute('exclude_sitemapxml'),
                'sitemap_priority' => (string) $page->getAttribute('sitemap_priority'),
                'sitemap_changefreq' => (string) $page->getAttribute('sitemap_changefreq'),
            ];

            foreach ($now as $handle => $value) {
                // a stored priority such as "0.50" is the same as the "0.5" typed back
                $same = $handle === 'sitemap_priority' && $value !== '' && $before[$handle] !== ''
                    ? (float) $before[$handle] === (float) $value
                    : $before[$handle] === $value;

                if ($same) {
                    continue;
                }

                $key = CollectionKey::getByHandle($handle);

                if (!$assignment || !$key || !$assignment->canEditAttributeKey($key)) {
                    $denied[$page->getCollectionName()] = true;

                    continue;
                }

                $value === '' || $value === false ? $page->clearAttribute($handle) : $page->setAttribute($handle, $value);
                $changed++;
            }
        }

        if ($denied !== []) {
            $this->error->add(t('You may not change these sitemap settings on: %s.', implode(', ', array_keys($denied))));
        }

        if ($skipped !== []) {
            $this->error->add(t('Not saved, because of an invalid priority or change frequency: %s.', implode(', ', $skipped)));
        }

        if (!$this->error->has()) {
            $this->flash('success', t2('%d change saved. Run "Generate Sitemap" to update the file.', '%d changes saved. Run "Generate Sitemap" to update the file.', $changed, $changed));
            $this->buildRedirect('/dashboard/seo_kit/sitemap')->send();
        }

        $this->view();
    }
}
