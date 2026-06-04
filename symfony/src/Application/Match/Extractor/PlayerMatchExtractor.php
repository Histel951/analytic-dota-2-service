<?php
declare(strict_types = 1);

namespace App\Application\Match\Extractor;

use App\Domain\Match\Entity\PlayerMatchStats;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Duration;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Entities\Match\MatchDetail;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TeamSide;

final readonly class PlayerMatchExtractor
{
    /**
     * @param MatchDetail $matchDetail
     * @return PlayerMatchStats[]
     */
    public function extract(MatchDetail $matchDetail): array
    {
        $winner = $matchDetail->getResult()->getWinner();
        $loser = $matchDetail->getResult()->getLoser();
        $id = $matchDetail->getId();
        $duration = $matchDetail->getDuration();

        $result = [];
        foreach ([$winner, $loser] as $side) {
            $result = array_merge($this->extractMatchStats($side, $id, $duration), $result);
        }

        return $result;
    }

    private function extractMatchStats(TeamSide $teamSide, MatchId $matchId, Duration $duration): array
    {
        $result = [];
        foreach ($teamSide->getPlayers()->getValue() as $player) {
            $result[] = new PlayerMatchStats(
                matchId: $matchId,
                duration: $duration,
                playerId: $player->getIdentity()->getPlayerId(),
                openDotaHeroId: $player->getIdentity()->getHeroId(),
                openDotaTeamId: $teamSide->getTeamId(),
                kills: $player->getKda()->getKills(),
                deaths: $player->getKda()->getDeaths(),
                assists: $player->getKda()->getAssists(),
            );
        }

        return $result;
    }
}