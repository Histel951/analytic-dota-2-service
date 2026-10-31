<?php

namespace App\Ingestion\Domain\Processing;

enum MatchProcessingStatus: string
{
    case PENDING = 'pending';
    case FETCHING = 'fetching';
    case FETCHED = 'fetched';
    case PROCESSING_ANALYTICS = 'processing_analytics';
    case ANALYTICS_COMPLETED = 'analytics_completed';
    case PROCESSING_FANTASY = 'processing_fantasy';
    case FANTASY_COMPLETED = 'fantasy_completed';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}