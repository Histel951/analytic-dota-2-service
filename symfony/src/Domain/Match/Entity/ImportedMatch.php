<?php

declare(strict_types=1);

namespace App\Domain\Match\Entity;

use App\Shared\Domain\MatchId;
use App\Shared\Domain\Region\Region;
use DateTimeImmutable;

/**
 * Матч, полученный из внешнего источника, в модели приложения.
 * Игроки — сначала победившей стороны, затем проигравшей.
 */
final readonly class ImportedMatch
{
    /**
     * @param list<ImportedPlayer> $players
     */
    public function __construct(
        private MatchId $matchId,
        private DateTimeImmutable $startTime,
        private int $durationSeconds,
        private bool $radiantWin,
        private ?Region $region,
        private array $players,
    ) {
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function isRadiantWin(): bool
    {
        return $this->radiantWin;
    }

    public function getRegion(): ?Region
    {
        return $this->region;
    }

    /**
     * @return list<ImportedPlayer>
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    /**
     * @return list<PlayerMatchStats>
     */
    public function getPlayerStats(): array
    {
        return array_map(
            static fn(ImportedPlayer $player): PlayerMatchStats => $player->getStats(),
            $this->players,
        );
    }
}
