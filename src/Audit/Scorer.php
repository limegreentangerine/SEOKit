<?php

namespace SeoKit\Audit;

/**
 * Turns findings into scores. A page starts at 100 and loses points per finding; the site score is the average
 * over every page scanned, so clean pages count.
 */
final class Scorer
{
    /**
     * Points lost per finding of each severity.
     */
    public const PENALTIES = [
        Checks::ERROR => 20,
        Checks::WARNING => 10,
        Checks::NOTICE => 3,
    ];

    /**
     * @param array<int, array{pageId: int, severity: string}> $findings
     *
     * @return array{score: int, errors: int, warnings: int, notices: int, pageScores: array<int, int>}
     */
    public static function summarise(int $pagesScanned, array $findings): array
    {
        $lost = [];
        $counts = [Checks::ERROR => 0, Checks::WARNING => 0, Checks::NOTICE => 0];

        foreach ($findings as $finding) {
            $severity = $finding['severity'];
            $lost[$finding['pageId']] = ($lost[$finding['pageId']] ?? 0) + (self::PENALTIES[$severity] ?? 0);
            $counts[$severity] = ($counts[$severity] ?? 0) + 1;
        }

        $pageScores = array_map(static fn(int $points): int => max(0, 100 - $points), $lost);

        // pages with no findings score 100 and are not listed in $pageScores
        $total = $pagesScanned > 0
            ? (int) round((array_sum($pageScores) + 100 * max(0, $pagesScanned - count($pageScores))) / $pagesScanned)
            : 100;

        return [
            'score' => $total,
            'errors' => $counts[Checks::ERROR],
            'warnings' => $counts[Checks::WARNING],
            'notices' => $counts[Checks::NOTICE],
            'pageScores' => $pageScores,
        ];
    }
}
