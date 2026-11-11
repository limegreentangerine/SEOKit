<?php

namespace SeoKit\Entity;

use Doctrine\ORM\Mapping as ORM;
use ClassKit\Entity\Core\BaseEntity;

/**
 * One SEO audit: when it ran, how many pages it covered and the resulting score. Times are UTC unix seconds.
 *
 * @ORM\Entity
 * @ORM\Table(
 *      name="seoKitAuditRuns",
 *      options={"comment": "SEO Kit audit runs"}
 * )
 */
class AuditRun extends BaseEntity
{
    public const RUNNING = 'running';

    public const COMPLETE = 'complete';

    public const FAILED = 'failed';

    /**
     * @ORM\Column(type="string", length=16, nullable=false, options={"default": "running", "comment": "running, complete or failed"})
     */
    protected $status = self::RUNNING;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "comment": "Start, UTC unix seconds"})
     */
    protected $startedAt = 0;

    /**
     * @ORM\Column(type="integer", nullable=true, options={"comment": "End, UTC unix seconds"})
     */
    protected $finishedAt;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "comment": "Pages audited"})
     */
    protected $pagesScanned = 0;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "comment": "Site score, 0 to 100"})
     */
    protected $score = 0;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "comment": "Error findings"})
     */
    protected $errorCount = 0;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "comment": "Warning findings"})
     */
    protected $warningCount = 0;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "comment": "Notice findings"})
     */
    protected $noticeCount = 0;

    public function start(int $now): self
    {
        $this->status = self::RUNNING;
        $this->startedAt = $now;
        $this->finishedAt = null;

        return $this;
    }

    public function finish(string $status, int $now): self
    {
        $this->status = in_array($status, [self::COMPLETE, self::FAILED], true) ? $status : self::FAILED;
        $this->finishedAt = $now;

        return $this;
    }

    public function getStatus(): string
    {
        return (string) $this->status;
    }

    public function getStartedAt(): int
    {
        return (int) $this->startedAt;
    }

    public function getFinishedAt(): ?int
    {
        return $this->finishedAt === null ? null : (int) $this->finishedAt;
    }

    public function getPagesScanned(): int
    {
        return (int) $this->pagesScanned;
    }

    public function setPagesScanned(int $pages): self
    {
        $this->pagesScanned = max(0, $pages);

        return $this;
    }

    public function getScore(): int
    {
        return (int) $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = max(0, min(100, $score));

        return $this;
    }

    public function getErrorCount(): int
    {
        return (int) $this->errorCount;
    }

    public function setErrorCount(int $count): self
    {
        $this->errorCount = max(0, $count);

        return $this;
    }

    public function getWarningCount(): int
    {
        return (int) $this->warningCount;
    }

    public function setWarningCount(int $count): self
    {
        $this->warningCount = max(0, $count);

        return $this;
    }

    public function getNoticeCount(): int
    {
        return (int) $this->noticeCount;
    }

    public function setNoticeCount(int $count): self
    {
        $this->noticeCount = max(0, $count);

        return $this;
    }
}
