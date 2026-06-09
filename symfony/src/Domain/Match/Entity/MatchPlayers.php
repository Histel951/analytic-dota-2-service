<?php
declare(strict_types=1);

namespace App\Domain\Match\Entity;

use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;

final class MatchPlayers
{
    public function __construct(
        private readonly MatchId   $matchId,
        private readonly PlayerId  $playerId,
        private readonly ?string   $playerProName = null,
        private ?DateTimeImmutable $createdAt = null,
    )
    {
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getPlayerProName(): ?string
    {
        return $this->playerProName;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}