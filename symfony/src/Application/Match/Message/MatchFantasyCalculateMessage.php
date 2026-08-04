<?php
declare(strict_types=1);

namespace App\Application\Match\Message;

use Histel951\Dota2Api\Domain\Entities\Match\MatchDetail;

final readonly class MatchFantasyCalculateMessage
{
    public function __construct(
        private int $matchId,
        private string $serializedMatchDetail,
    ) {
    }

    public function getMatchId(): int
    {
        return $this->matchId;
    }

    public function getSerializedMatchDetail(): string
    {
        return $this->serializedMatchDetail;
    }

    public function getMatchDetail(): MatchDetail
    {
        return unserialize($this->serializedMatchDetail);
    }
}
