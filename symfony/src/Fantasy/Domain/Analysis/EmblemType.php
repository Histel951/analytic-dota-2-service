<?php
declare(strict_types=1);

namespace App\Fantasy\Domain\Analysis;

enum EmblemType: string
{
    case CORE = 'core';
    case UTILITY = 'utility';
    case SUPPORT = 'support';

    /**
     * @return string[]
     */
    public function getMetrics(): array
    {
        return match ($this) {
            self::CORE => [
                'deaths_score',
                'gpm_score',
                'kills_score',
                'creeps_score',
                'tower_score',
            ],
            self::UTILITY => [
                'teamfight_score',
                'tormentor_score',
                'first_blood_score',
                'roshan_score',
                'courier_score',
                'stun_score',
            ],
            self::SUPPORT => [
                'smoke_score',
                'stacks_score',
                'watcher_score',
                'observer_score',
                'rune_score',
            ],
        };
    }

    public static function fromMetric(string $metricName): self
    {
        foreach (self::cases() as $emblem) {
            if (in_array($metricName, $emblem->getMetrics(), true)) {
                return $emblem;
            }
        }

        throw new \InvalidArgumentException("Unknown fantasy metric: {$metricName}");
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::CORE => 'Red (Core)',
            self::UTILITY => 'Green (Utility)',
            self::SUPPORT => 'Blue (Support)',
        };
    }
}
