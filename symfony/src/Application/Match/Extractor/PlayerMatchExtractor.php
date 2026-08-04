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
            $this->extractMatchStats($winner, $id, $duration, $matchDetail->getStartTime()),
            $this->extractMatchStats($loser, $id, $duration, $matchDetail->getStartTime())
        );
    }

    private function extractMatchStats(TeamSide $teamSide, MatchId $matchId, Duration $duration, MatchStartTime $startTime): array
    {
        $result = [];
        $isWin = $teamSide->isWon();

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
                gpm: $player->getEconomy()->getGPM(),
                xpm: $player->getEconomy()->getXPM(),
                netWorth: $player->getEconomy()->getNetWorth(),
                totalGold: $player->getEconomy()->getTotalGold(),
                totalXp: $player->getEconomy()->getTotalXp(),
                goldSpend: $player->getEconomy()->getGoldSpent(),
                creepsLastHits: $player->getCreeps()->getLastHits(),
                creepsLaneLastHits: $player->getCreeps()->getLaneCreeps(),
                creepsDenies: $player->getCreeps()->getDenies(),
                creepsNeutrals: $player->getCreeps()->getNeutralCreeps(),
                creepsAncients: $player->getCreeps()->getAncientCreeps(),
                campsStacked: $player->getCreeps()->getCampsStacked(),
                roshanKills: $player->getObjectives()->getRoshanKills(),
                towerKills: $player->getObjectives()->getTowerKills(),
                runePickups: $player->getObjectives()->getRunePickups(),
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
                role: $player->getRole(),
                startTime: $startTime->getValue(),
            );
        }

        return $result;
    }
}