<?php

namespace SeoKit\Command\Task;

use Psr\Log\LoggerInterface;
use SeoKit\Audit\AuditService;
use Concrete\Core\Command\Task\Output\{OutputAwareInterface, OutputAwareTrait};

/**
 * Runs from the command line, cron or the Automation page, so there is no web request. Output is counts and fixed
 * sentences only. A failed audit throws a fixed-message exception, which Concrete records as the task's failure
 * message; the detail goes to the Concrete log.
 */
class AuditCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    public const FAILED = 'The audit could not finish. Details are in the Concrete log.';

    public function __invoke(AuditCommand $command)
    {
        try {
            @set_time_limit(300);
            $run = app(AuditService::class)->run(fn(string $message) => $this->output->write($message));
        } catch (\Throwable $e) {
            try {
                app(LoggerInterface::class)->error('SEO Kit audit failed: ' . $e->getMessage());
            } catch (\Throwable) {
                // nothing more can be done if logging fails too
            }

            throw new \RuntimeException(t(self::FAILED));
        }

        $this->output->write(t(
            'Audited %1$s pages: score %2$s, %3$s errors, %4$s warnings, %5$s notices.',
            $run->getPagesScanned(),
            $run->getScore(),
            $run->getErrorCount(),
            $run->getWarningCount(),
            $run->getNoticeCount(),
        ));
    }
}
