<?php
declare(strict_types=1);

namespace App\Application\Team\Dto;

final readonly class TeamFormResult
{
    public function __construct(
        public int $analyzedTeams,
        public int $failedTeams,
        public array $errors = [],
    ) {}
}
