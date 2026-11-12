<?php

namespace Concrete\Package\SeoKit\Controller\SinglePage\Dashboard;

use Concrete\Core\Page\Controller\DashboardPageController;

/**
 * The section page has no content of its own; send visitors to the first child.
 */
class SeoKit extends DashboardPageController
{
    public function view(): void
    {
        $this->buildRedirect('/dashboard/seo_kit/settings')->send();
    }
}
