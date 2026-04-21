<?php

namespace App\Support;

final class GradeRubric
{
    /** @var list<string> */
    public const SKILL_KEYS = ['vocabulary', 'listening', 'speaking', 'writing', 'grammar'];

    /** Stored in DB */
    public const OUTSTANDING = 'outstanding';

    public const ACCEPTABLE = 'acceptable';

    public const REGULAR = 'regular';

    public const NEED_SUPPORT = 'need_support';

    /** @var list<string> */
    public const RATING_VALUES = [
        self::OUTSTANDING,
        self::ACCEPTABLE,
        self::REGULAR,
        self::NEED_SUPPORT,
    ];

    /** @var array<string, string> Spanish labels for UI */
    public const RATING_LABELS_ES = [
        self::OUTSTANDING => 'Outstanding',
        self::ACCEPTABLE => 'Acceptable',
        self::REGULAR => 'Regular',
        self::NEED_SUPPORT => 'Need Support',
    ];

    /** @var array<string, string> */
    public const SKILL_LABELS_ES = [
        'vocabulary' => 'Vocabulary',
        'listening' => 'Listening',
        'speaking' => 'Speaking',
        'writing' => 'Writing',
        'grammar' => 'Grammar',
    ];

    public static function ratingTone(string $rating): string
    {
        return match ($rating) {
            self::OUTSTANDING => 'ok',
            self::ACCEPTABLE => 'info',
            self::REGULAR => 'warn',
            self::NEED_SUPPORT => 'danger',
            default => 'info',
        };
    }

    /** @return array<string, string> skill key => DB column name */
    public static function skillToColumnMap(): array
    {
        return [
            'vocabulary' => 'vocabulary_rating',
            'listening' => 'listening_rating',
            'speaking' => 'speaking_rating',
            'writing' => 'writing_rating',
            'grammar' => 'grammar_rating',
        ];
    }
}
