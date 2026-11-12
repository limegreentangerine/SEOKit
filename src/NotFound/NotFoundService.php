<?php

namespace SeoKit\NotFound;

use Concrete\Core\Application\Application;
use Concrete\Core\Database\Connection\Connection;

/**
 * Logs 404s and turns them into redirects through the toolkit's redirection tool.
 */
class NotFoundService
{
    /**
     * One write in this many also prunes. shortcut: pruning on a share of writes, not on a schedule, keeps this to
     * one query per 404; move it to a task if a site gets many 404s a second.
     */
    protected const PRUNE_ONE_IN = 100;
    /**
     * Rows older than this many days are dropped.
     */
    public const KEEP_DAYS = 90;

    /**
     * Most rows kept. The least requested and oldest go first, so a flood of random addresses cannot grow the table.
     */
    public const MAX_ROWS = 5000;

    public const PER_PAGE = 50;

    public function __construct(protected Application $app, protected Connection $db) {}

    /**
     * Counts one 404 for the path. The referrer is reduced to its host name.
     */
    public function record(mixed $rawPath, mixed $referer, ?int $now = null): bool
    {
        $path = NotFoundPath::clean($rawPath);

        if ($path === null) {
            return false;
        }

        $now ??= time();

        $this->db->executeStatement(
            'INSERT INTO seoKitNotFound (path, hits, firstSeen, lastSeen, referrerHost) VALUES (?, 1, ?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE hits = hits + 1, lastSeen = VALUES(lastSeen), referrerHost = COALESCE(VALUES(referrerHost), referrerHost)',
            [$path, $now, $now, NotFoundPath::referrerHost($referer)],
        );

        if (random_int(1, self::PRUNE_ONE_IN) === 1) {
            $this->prune($now);
        }

        return true;
    }

    public function prune(?int $now = null): void
    {
        $this->db->executeStatement('DELETE FROM seoKitNotFound WHERE lastSeen < ?', [($now ?? time()) - self::KEEP_DAYS * 86400]);

        $excess = (int) $this->db->fetchOne('SELECT COUNT(*) FROM seoKitNotFound') - self::MAX_ROWS;

        if ($excess > 0) {
            $this->db->executeStatement(
                'DELETE FROM seoKitNotFound WHERE id IN (SELECT id FROM (SELECT id FROM seoKitNotFound ORDER BY hits ASC, lastSeen ASC LIMIT ' . $excess . ') AS doomed)',
            );
        }
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function list(string $search = '', int $page = 1): array
    {
        $where = '';
        $args = [];

        if ($search !== '') {
            $where = ' WHERE path LIKE ?';
            $args[] = '%' . addcslashes(mb_strtolower($search), '%_\\') . '%';
        }

        $offset = max(0, $page - 1) * self::PER_PAGE;
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, path, hits, firstSeen, lastSeen, referrerHost FROM seoKitNotFound' . $where
            . ' ORDER BY hits DESC, lastSeen DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset,
            $args,
        );

        return ['rows' => $rows, 'total' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM seoKitNotFound' . $where, $args)];
    }

    public function find(int $id): ?array
    {
        $row = $this->db->fetchAssociative('SELECT id, path FROM seoKitNotFound WHERE id = ?', [$id]);

        return $row ?: null;
    }

    public function dismiss(int $id): void
    {
        $this->db->executeStatement('DELETE FROM seoKitNotFound WHERE id = ?', [$id]);
    }

    /**
     * Whether the toolkit's redirection tool is installed. It is on a toolkit branch that is not released yet, so a
     * site may have the toolkit without it.
     */
    public function canRedirect(): bool
    {
        return $this->app->bound('lgt_redirections');
    }

    /**
     * Creates a redirect from the logged path to a page through the toolkit, then removes the log row.
     * Returns null on success, or a message for the editor.
     */
    public function createRedirect(int $id, int $cID, int|string $code): ?string
    {
        if (!$this->canRedirect()) {
            return t('The redirection tool is not available. Update LGT Toolkit to a version that includes it.');
        }

        $row = $this->find($id);

        if ($row === null) {
            return t('That address is no longer in the list.');
        }

        try {
            $this->app->make('lgt_redirections')->create((string) $row['path'], $cID, $code);
        } catch (\Throwable $e) {
            // the toolkit's own exception carries a message written for editors; anything else is logged, not shown
            if ($e::class === 'LgtToolkit\Redirections\RedirectionException') {
                return $e->getMessage();
            }

            $this->app->make('log')->error('SEO Kit 404 monitor could not create a redirect: ' . $e->getMessage());

            return t('The redirect could not be created. See the Concrete log.');
        }

        $this->dismiss($id);

        return null;
    }
}
