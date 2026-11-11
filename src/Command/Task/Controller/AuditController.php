<?php

namespace SeoKit\Command\Task\Controller;

use SeoKit\Command\Task\AuditCommand;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Controller\AbstractController;
use Concrete\Core\Command\Task\Runner\{ProcessTaskRunner, TaskRunnerInterface};

class AuditController extends AbstractController
{
    public function getName(): string
    {
        return t('Audit the site for search engines');
    }

    public function getDescription(): string
    {
        return t('Checks every published page for missing, long, short or repeated titles and descriptions, noindex pages in the sitemap, missing sharing images and structured data that would not be output, then scores the site. Results are under Dashboard > SEO Kit > Audit.');
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        return new ProcessTaskRunner(
            $task,
            new AuditCommand(),
            $input,
            t('Auditing the site...'),
        );
    }
}
