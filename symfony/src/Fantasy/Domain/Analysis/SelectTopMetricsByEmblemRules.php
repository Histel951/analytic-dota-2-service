<?php
declare(strict_types=1);

namespace App\Fantasy\Domain\Analysis;


final readonly class SelectTopMetricsByEmblemRules
{
    /**
     * Выбирает топ-метрики по квотам эмблем role group.
     * Внутри каждой эмблемы метрики сортируются по вкладу (desc).
     *
     * @param array<int, array{metric: string, avg_value: float, contribution: float}> $metricContributions
     * @return MetricContribution[]
     */
    public function select(RoleGroup $roleGroup, array $metricContributions): array
    {
        $byEmblem = [];
        foreach (EmblemType::cases() as $emblem) {
            $byEmblem[$emblem->value] = [];
        }

        foreach ($metricContributions as $item) {
            $emblem = EmblemType::fromMetric($item['metric']);
            $byEmblem[$emblem->value][] = $item;
        }

        foreach ($byEmblem as &$items) {
            usort($items, fn($a, $b) => $b['contribution'] <=> $a['contribution']);
        }
        unset($items);

        $selected = [];
        foreach ($roleGroup->getEmblemQuota() as $emblemValue => $quota) {
            $selected = array_merge(
                $selected,
                array_slice($byEmblem[$emblemValue], 0, $quota)
            );
        }

        usort($selected, fn($a, $b) => $b['contribution'] <=> $a['contribution']);

        return array_map(
            fn(array $item) => new MetricContribution(
                metricName: $item['metric'],
                avgValue: $item['avg_value'],
                contributionPercent: round($item['contribution'], 2),
            ),
            $selected
        );
    }
}
