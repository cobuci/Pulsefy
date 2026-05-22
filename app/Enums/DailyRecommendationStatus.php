<?php

namespace App\Enums;

enum DailyRecommendationStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Empty = 'empty';
    case Failed = 'failed';

    public function isPending(): bool
    {
        return $this === self::Processing;
    }
}
