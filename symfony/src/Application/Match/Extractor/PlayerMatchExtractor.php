<?php
declare(strict_types = 1);

namespace App\Application\Match\Extractor;

use App\Domain\Match\Entity\PlayerMatchStats;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Duration;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Entities\Match\MatchDetail;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TeamSide;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\MatchStartTime;

final readonly class PlayerMatchExtractor
{
    public function extract(MatchDetail $matchDetail): array
    {
        $winner = $matchDetail->getResult()->getWinner();
        $loser = $matchDetail->getResult()->getLoser();
        $id = $matchDetail->getId();
        $duration = $matchDetail->getDuration();

        return array_merge(
            $this->extractMatchStats($winner, $id, $duration),
            $this->extractMatchStats($loser, $id, $duration)
        );
    }

    private function extractMatchStats(TeamSide $teamSide, MatchId $matchId, Duration $duration, MatchStartTime $startTime): array
    {
        $result = [];
        $isWin = $teamSide->isWinner();

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
                gpm: $player->getEconomic()->getGPM(),
                xpm: $player->getEconomic()->getXPM(),
                netWorth: $player->getEconomic()->getNetWorth(),
                totalGold: $player->getEconomic()->getTotalGold(),
                totalXp: $player->getEconomic()->getTotalXp(),
                goldSpend: $player->getEconomic()->getGoldSpend(),
                creepsLastHits: $player->getCreeps()->getCreepsLastHits(),
                creepsLaneLastHits: $player->getCreeps()->getCreepsLaneLastHits(),
                creepsDenies: $player->getCreeps()->getCreepsDenies(),
                creepsNeutrals: $player->getCreeps()->getCreepsNeutrals(),
                creepsAncients: $player->getCreeps()->getCreepsAncients(),
                roshanKills: $player->getObjectActives()->getRoshanKills(),
                towerKills: $player->getObjectActives()->getTowerKills(),
                runePickups: $player->getObjectActives()->getRunePickups(),
                observersPlaced: $player->getWarding()->getObserversPlaced(),
                observersDestroyed: $player->getWarding()->getObserversDestroyed(),
                sentryPlaced: $player->getWarding()->getSentryPlaced(),
                sentryDestroyed: $player->getWarding()->getSentryDestroyed(),
                heroDamage: $player->getDamage()->getHeroDamage(),
                towerDamage: $player->getDamage()->getTowerDamage(),
                heroHealing: $player->getDamage()->getHeroHealing(),
                buybackCount: $player->getUtility()->getBuybackCount(),
                firstBloodClaimed: $player->getUtility()->isFirstBloodClaimed(),
                courierKills: $player->getUtility()->getCourierKills(),
                stuns: $player->getUtility()->getStuns(),
                teamfightParticipation: $player->getUtility()->getTeamfightParticipation(),
                side: $teamSide->getSide(),
                win: $isWin,
                lane: $player->getPosition()->getLane(),
                role: $player->getPosition()->getRole(),
                startTime: $startTime->getValue(),
            );
        }

        return $result;
    }
}