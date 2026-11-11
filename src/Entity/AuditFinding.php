<?php

namespace SeoKit\Entity;

use Doctrine\ORM\Mapping as ORM;
use ClassKit\Entity\Core\BaseEntity;

/**
 * One issue found on one page in one audit. The page name and path are kept as they were when audited, so results
 * stay readable after a page is renamed or deleted; the wording for the code comes from SeoKit\Audit\Issues.
 *
 * @ORM\Entity
 * @ORM\Table(
 *      name="seoKitAuditFindings",
 *      indexes={
 *          @ORM\Index(name="idx_seokit_finding_run", columns={"run_id"}),
 *          @ORM\Index(name="idx_seokit_finding_page", columns={"pageID"})
 *      },
 *      options={"comment": "SEO Kit audit findings"}
 * )
 */
class AuditFinding extends BaseEntity
{
    /**
     * @ORM\ManyToOne(targetEntity="SeoKit\Entity\AuditRun")
     * @ORM\JoinColumn(name="run_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $run;

    /**
     * @ORM\Column(type="integer", nullable=false, options={"default": 0, "comment": "Concrete page ID"})
     */
    protected $pageID = 0;

    /**
     * @ORM\Column(type="string", length=255, nullable=false, options={"default": "", "comment": "Page name when audited"})
     */
    protected $pageName = '';

    /**
     * @ORM\Column(type="string", length=255, nullable=false, options={"default": "", "comment": "Page path when audited"})
     */
    protected $pagePath = '';

    /**
     * @ORM\Column(type="string", length=40, nullable=false, options={"default": "", "comment": "Issue code, see SeoKit Audit Checks"})
     */
    protected $code = '';

    /**
     * @ORM\Column(type="string", length=8, nullable=false, options={"default": "notice", "comment": "error, warning or notice"})
     */
    protected $severity = 'notice';

    /**
     * @ORM\Column(type="string", length=255, nullable=false, options={"default": "", "comment": "Short detail, such as a length"})
     */
    protected $detail = '';

    public function getRun(): ?AuditRun
    {
        return $this->run;
    }

    public function setRun(AuditRun $run): self
    {
        $this->run = $run;

        return $this;
    }

    public function getPageID(): int
    {
        return (int) $this->pageID;
    }

    public function setPageID(int $pageID): self
    {
        $this->pageID = $pageID;

        return $this;
    }

    public function getPageName(): string
    {
        return (string) $this->pageName;
    }

    public function setPageName(string $pageName): self
    {
        $this->pageName = mb_substr($pageName, 0, 255);

        return $this;
    }

    public function getPagePath(): string
    {
        return (string) $this->pagePath;
    }

    public function setPagePath(string $pagePath): self
    {
        $this->pagePath = mb_substr($pagePath, 0, 255);

        return $this;
    }

    public function getCode(): string
    {
        return (string) $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = mb_substr($code, 0, 40);

        return $this;
    }

    public function getSeverity(): string
    {
        return (string) $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = mb_substr($severity, 0, 8);

        return $this;
    }

    public function getDetail(): string
    {
        return (string) $this->detail;
    }

    public function setDetail(string $detail): self
    {
        $this->detail = mb_substr($detail, 0, 255);

        return $this;
    }
}
