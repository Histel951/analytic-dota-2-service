<?php

declare(strict_types=1);

namespace App\Ingestion\Infrastructure\Source\OpenDota;

use App\Ingestion\Domain\Match\ImportedMatch;
use App\Ingestion\Domain\Match\ImportedPlayer;
use App\Ingestion\Domain\Match\PlayerMatchStats;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\MatchId;
use App\Shared\Domain\PlayerId;
use App\Shared\Domain\PlayerRole;
use App\Shared\Domain\Region\Region;
use App\Shared\Domain\Side;
use App\Shared\Domain\TeamId;
use DateTimeImmutable;
use Histel951\Dota2Api\OpenDota\Match\Domain\MatchDetail;
use Histel951\Dota2Api\OpenDota\Match\Domain\Player\MatchPlayerPerformance;
use Histel951\Dota2Api\OpenDota\Match\Domain\Side\TeamSide;

/**
 * MatchDetail OpenDota (бандл dota-api 2.0) → ImportedMatch приложения.
 */
final readonly class OpenDotaMatchMapper
{
    /**
     * @throws DomainException
     */
    public function toImportedMatch(MatchDetail $matchDetail): ImportedMatch
    {
        $matchId = new MatchId($matchDetail->getId()->getValue());
        $durationSeconds = $matchDetail->getDuration()->getSeconds();
        $startTime = $matchDetail->getStartTime()->getValue();
        $region = $matchDetail->getRegion();

        return new ImportedMatch(
            matchId: $matchId,
            startTime: $startTime,
            durationSeconds: $durationSeconds,
            radiantWin: $matchDetail->getRadiant()->isWon(),
            region: $region !== null ? Region::fromId($region->getId()) : null,
            players: array_merge(
                $this->mapSide($matchDetail->getResult()->getWinner(), $matchId, $durationSeconds, $startTime),
                $this->mapSide($matchDetail->getResult()->getLoser(), $matchId, $durationSeconds, $startTime),
            ),
        );
    }

    /**
     * @return list<ImportedPlayer>
     *
     * @throws DomainException
     */
    private function mapSide(
        TeamSide $teamSide,
        MatchId $matchId,
        int $durationSeconds,
        DateTimeImmutable $startTime,
    ): array {
        $teamId = new TeamId($teamSide->getTeamId()->getValue());
        $side = Side::from($teamSide->getSide()->value);
        $isWin = $teamSide->isWon();

        $players = [];

        foreach ($teamSide->getPlayers()->getValue() as $player) {
            $players[] = $this->mapPlayer($player, $matchId, $durationSeconds, $teamId, $side, $isWin, $startTime);
        }

        return $players;
    }

    /**
     * @throws DomainException
     */
    private function mapPlayer(
        MatchPlayerPerformance $player,
        MatchId $matchId,
        int $durationSeconds,
        TeamId $teamId,
        Side $side,
        bool $isWin,
        DateTimeImmutable $startTime,
    ): ImportedPlayer {
        $identity = $player->getIdentity();
        $playerId = new PlayerId($identity->getPlayerId()->getValue());
        $kda = $player->getKda();
        $economy = $player->getEconomy();
        $creeps = $player->getCreeps();
        $objectives = $player->getObjectives();
        $warding = $player->getWarding();
        $damage = $player->getDamage();
        $utility = $player->getUtility();

        return new ImportedPlayer(
            playerId: $playerId,
            proName: $identity->getPlayerProName()?->getValue(),
            stats: new PlayerMatchStats(
                matchId: $matchId,
                durationSeconds: $durationSeconds,
                playerId: $playerId,
                openDotaHeroId: $identity->getHeroId()->getValue(),
                openDotaTeamId: $teamId,
                kills: $kda->getKills(),
                deaths: $kda->getDeaths(),
                assists: $kda->getAssists(),
                gpm: $economy->getGPM()->getValue(),
                xpm: $economy->getXPM()->getValue(),
                netWorth: $economy->getNetWorth()->getValue(),
                totalGold: $economy->getTotalGold()->getValue(),
                totalXp: $economy->getTotalXP()->getValue(),
                goldSpend: $economy->getGoldSpent()->getValue(),
                creepsLastHits: $creeps->getLastHits()->getValue(),
                creepsLaneLastHits: $creeps->getLaneCreeps()->getValue(),
                creepsDenies: $creeps->getDenies()->getValue(),
                creepsNeutrals: $creeps->getNeutralCreeps()->getValue(),
                creepsAncients: $creeps->getAncientCreeps()->getValue(),
                campsStacked: $creeps->getCampsStacked()->getValue(),
                roshanKills: $objectives->getRoshanKills()->getValue(),
                towerKills: $objectives->getTowerKills()->getValue(),
                runePickups: $objectives->getRunePickups()->getValue(),
                tormentorKills: $objectives->getTormentorKills()->getValue(),
                observersPlaced: $warding->getObserversPlaced()->getValue(),
                observersDestroyed: $warding->getObserversDestroyed()->getValue(),
                sentryPlaced: $warding->getSentryPlaced()->getValue(),
                sentryDestroyed: $warding->getSentryDestroyed()->getValue(),
                observerTakeovers: $warding->getObserverTakeovers()->getValue(),
                heroDamage: $damage->getHeroDamage()->getValue(),
                towerDamage: $damage->getTowerDamage()->getValue(),
                heroHealing: $damage->getHeroHealing()->getValue(),
                buybackCount: $utility->getBuybackCount()->getValue(),
                firstBloodClaimed: $utility->isFirstBloodClaimed(),
                courierKills: $utility->getCourierKills()->getValue(),
                stuns: $utility->getStuns()->getValue(),
                teamfightParticipation: $utility->getTeamfightParticipation()->getValue(),
                smokeUses: $utility->getSmokeUses()->getValue(),
                side: $side,
                win: $isWin,
                role: PlayerRole::from($player->getRole()->toInt()),
                startTime: $startTime,
            ),
        );
    }
}
