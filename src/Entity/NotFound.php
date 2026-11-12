<?php

namespace SeoKit\Entity;

use Doctrine\ORM\Mapping as ORM;
use ClassKit\Entity\Core\BaseEntity;

/**
 * An address that returned a 404, with how often and when. One row per path, so the table stays small.
 * Times are UTC unix seconds. Rows are written with an upsert in NotFoundService, not through this class.
 *
 * @ORM\Entity
 * @ORM\Table(
 *      name="seoKitNotFound",
 *      options={"comment": "SEO Kit 404 monitor"},
 *      uniqueConstraints={@ORM\UniqueConstraint(name="uniq_seokit_notfound_path", columns={"path"})},
 *      indexes={@ORM\Index(name="idx_seokit_notfound_seen", columns={"lastSeen"})}
 * )
 */
class NotFound extends BaseEntity
{
    /**
     * @ORM\Column(name="path", type="string", length=191, nullable=false, options={"comment": "Lowercase path, no trailing slash"})
     */
    protected $path = '';

    /**
     * @ORM\Column(name="hits", type="integer", nullable=false, options={"default": 1, "comment": "Times requested"})
     */
    protected $hits = 1;

    /**
     * @ORM\Column(name="firstSeen", type="integer", nullable=false, options={"default": 0, "comment": "First request, UTC unix seconds"})
     */
    protected $firstSeen = 0;

    /**
     * @ORM\Column(name="lastSeen", type="integer", nullable=false, options={"default": 0, "comment": "Latest request, UTC unix seconds"})
     */
    protected $lastSeen = 0;

    /**
     * @ORM\Column(name="referrerHost", type="string", length=255, nullable=true, options={"comment": "Host of the latest referring site"})
     */
    protected $referrerHost;
}
