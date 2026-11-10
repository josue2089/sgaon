<?php

namespace App\Support;

use App\Models\Program;
use App\Models\ProgramLevel;

/**
 * Plan de clases oficial del cliente: plantillas File A/B por serie (20 clases por parte)
 * y estructura de la clase por programa. Fuente: docs/guia-niveles/*-file-a.jpg, *-file-b.jpg y *-framework.jpg. #470
 */
final class LessonTemplates
{
    public const TYPES = [
        'unit' => 'Unidad',
        'review' => 'Repaso',
        'workbook' => 'Workbook',
        'test' => 'Prueba (P.E.)',
        'workshop' => 'Taller',
        'skills' => 'Skills',
        'speech' => 'Speech',
        'meeting' => 'Meeting',
    ];

    public const TAGS = [
        'module_zero' => 'Module Zero',
        'grammar' => 'Grammar',
        'handicraft' => 'Handicraft',
        'book' => 'Book',
        'vr' => 'VR',
        'meeting' => 'Meeting',
        'reading' => 'Reading skill',
        'financial' => 'Financial class',
        'lab' => 'Lab activity',
        'games' => 'Fun games',
        'production' => 'Production',
        'speech_practice' => 'Speech practice',
        'speech' => 'Speech',
    ];

    public const FRAMEWORKS = [
        'preprimary' => ['Starting on a High (Lead in)', 'Intro vocab', "Book's activities", 'Song', 'Handicraft', 'Game', 'Closure'],
        'primary' => ['Starting on a High (Lead in)', 'Intro vocab', 'Intro Grammar', "Book's activities", 'Song', 'Handicraft', 'Game', 'Closure'],
        'teens_adults' => ['Lead In', 'Intro vocab', 'Games or Activities', 'Intro Grammar', "Book's activities", 'Game', 'Closure'],
    ];

    /** Código del programa => estructura de clase. */
    public const PROGRAM_FRAMEWORKS = [
        'PREP' => 'preprimary',
        'PRIMARY' => 'primary',
        'HS' => 'teens_adults',
        Program::ADULTS_CODE => 'teens_adults',
    ];

    /**
     * Serie de la plantilla para un subnivel. Las plantillas que envió el cliente cubren Pre-Primary,
     * Primary y High School 1-2-3; el resto (HS4+, Conversational, adultos) queda sin plantilla hasta que la envíen.
     */
    public static function seriesFor(ProgramLevel $level): ?string
    {
        $level->loadMissing('program');
        $code = strtoupper((string) $level->code);

        return match ($level->program?->code) {
            'PREP' => 'preprimary',
            'PRIMARY' => 'primary',
            'HS' => preg_match('/^HS[123][AB]$/', $code) ? 'hs123' : null,
            default => null,
        };
    }

    public static function partFor(ProgramLevel $level): ?string
    {
        if (in_array($level->part, ['A', 'B'], true)) {
            return $level->part;
        }

        return preg_match('/([AB])$/i', (string) $level->code, $match) ? strtoupper($match[1]) : null;
    }

    /**
     * Las 20 clases de una parte.
     *
     * @return list<array{class_number: int, unit: string, content: string, lesson_type: string, activity_tags: list<string>}>
     */
    public static function lessons(string $series, string $part): array
    {
        $rows = match ($series) {
            'preprimary' => self::prePrimary($part),
            'primary' => self::primary($part),
            'hs123' => self::highSchool($part),
            default => [],
        };

        return array_map(fn (array $row, int $index) => [
            'class_number' => $index + 1,
            'unit' => $row[0],
            'content' => $row[1],
            'lesson_type' => $row[2],
            'activity_tags' => $row[3] ?? [],
        ], $rows, array_keys($rows));
    }

    public static function seriesLabel(string $series): string
    {
        return ['preprimary' => 'Pre-Primary', 'primary' => 'Primary', 'hs123' => 'High School 1-2-3'][$series] ?? $series;
    }

    /** @return list<array> */
    private static function prePrimary(string $part): array
    {
        $unit = fn (int $n) => [
            ["Unit {$n}", 'Vocab A', 'unit'],
            ["Unit {$n}", 'Vocab B', 'unit'],
            ["Unit {$n}", 'Review + Grammar', 'review', ['grammar']],
            ["Unit {$n}", 'Workshop', 'workshop'],
            ["P.E. {$n}", "Prueba de la unidad {$n}", 'test'],
        ];
        $handiVrMeeting = ['Handi + Book / VR + Meeting', 'Handicraft con el libro, realidad virtual y reunión con representantes', 'meeting', ['handicraft', 'book', 'vr', 'meeting']];
        $handiVr = ['Handi + Book / VR', 'Handicraft con el libro y realidad virtual', 'workshop', ['handicraft', 'book', 'vr']];
        $speechPractice = ['Speech practice', 'Práctica del speech', 'skills', ['speech_practice']];

        if ($part === 'A') {
            return [
                ['Module Zero', 'Getting to know you!', 'unit', ['module_zero']],
                ...$unit(1), $handiVrMeeting,
                ...$unit(2), $handiVr,
                ...$unit(3), $handiVr,
                ['Mini speech / Meeting', 'Mini speech y reunión con representantes', 'speech', ['speech', 'meeting']],
            ];
        }

        return [
            ...$unit(4), $handiVr,
            ...$unit(5), $handiVrMeeting,
            $speechPractice,
            ...$unit(6), $speechPractice,
            ['Final speech / Meeting', 'Speech final y reunión con representantes', 'speech', ['speech', 'meeting']],
        ];
    }

    /** @return list<array> */
    private static function primary(string $part): array
    {
        $unit = fn (int $n) => [
            ["Unit {$n}A", "Unit {$n}A", 'unit'],
            ["Unit {$n}B", "Unit {$n}B", 'unit'],
            ["Unit {$n}C", "Unit {$n}C", 'unit'],
            ["WB {$n}", "Workbook de la unidad {$n}", 'workbook'],
            ['Reading skill / Financial class', 'Reading skill y clase de educación financiera', 'skills', ['reading', 'financial']],
        ];
        $workshop = ['Workshop / VR + Meeting', 'Taller, realidad virtual y reunión con representantes', 'workshop', ['vr', 'meeting']];
        $speechPractice = ['Speech practice', 'Práctica del speech', 'skills', ['speech_practice']];
        $funGames = ['Fun games', 'Juegos de repaso', 'review', ['games']];

        if ($part === 'A') {
            $first = $unit(1);
            $first[0][1] = 'Module Zero · Unit 1A';
            $first[0][3] = ['module_zero'];

            return [
                ...$first,
                ...$unit(2),
                ['Lab act 1-2', 'Actividad de laboratorio de las unidades 1 y 2', 'workshop', ['lab']],
                ['P.E. 1-2', 'Prueba de las unidades 1 y 2', 'test'],
                $funGames,
                $workshop,
                ...$unit(3),
                ['Mini speech / Meeting', 'Mini speech y reunión con representantes', 'speech', ['speech', 'meeting']],
            ];
        }

        return [
            ...$unit(4),
            ['Lab act 3-4', 'Actividad de laboratorio de las unidades 3 y 4', 'workshop', ['lab']],
            ['P.E. 3-4', 'Prueba de las unidades 3 y 4', 'test'],
            $funGames,
            $workshop,
            $speechPractice,
            ...$unit(5),
            ['Lab act 5', 'Actividad de laboratorio de la unidad 5', 'workshop', ['lab']],
            ['P.E. 5', 'Prueba de la unidad 5', 'test'],
            $workshop,
            $speechPractice,
            ['Final speech / Meeting', 'Speech final y reunión con representantes', 'speech', ['speech', 'meeting']],
        ];
    }

    /** @return list<array> */
    private static function highSchool(string $part): array
    {
        $block = function (int $n, string $closingUnit, array $closing) {
            return [
                ["Unit {$n}.1 - {$n}.2", "Unit {$n}.1 - {$n}.2", 'unit', $n === 1 || $n === 3 ? ['module_zero'] : []],
                ["Unit {$n}.3 - {$n}.4", "Unit {$n}.3 - {$n}.4", 'unit'],
                ["Review {$n}.1 - {$n}.4", "Repaso de {$n}.1 a {$n}.4", 'review'],
                ["Unit {$n}.5 - {$n}.6", "Unit {$n}.5 - {$n}.6", 'unit'],
                ["Unit {$n}.7 - {$n}.8", "Unit {$n}.7 - {$n}.8", 'unit'],
                ["Review {$n}.5 - {$n}.8", "Repaso de {$n}.5 a {$n}.8", 'review'],
                ["WB {$n} + Production", "Workbook de la unidad {$n} y producción", 'workbook', ['production']],
                ["P.E. {$n}", "Prueba de la unidad {$n}", 'test'],
                [$closingUnit, $closing[0], $closing[1], $closing[2]],
            ];
        };
        $workshopVr = ['Taller, realidad virtual y reunión con representantes', 'workshop', ['vr', 'meeting']];

        if ($part === 'A') {
            $first = $block(1, 'Workshop / VR + Meeting', $workshopVr);
            $second = $block(2, 'Workshop / VR + Meeting', $workshopVr);

            return [
                ...$first,
                ['Skills / Speech practice', 'Skills y práctica del speech', 'skills', ['speech_practice']],
                ...$second,
                ['Mini speech / Meeting', 'Mini speech y reunión con representantes', 'speech', ['speech', 'meeting']],
            ];
        }

        return [
            ...$block(3, 'Workshop / VR + Meeting', $workshopVr),
            ['Skills / Speech practice', 'Skills y práctica del speech', 'skills', ['speech_practice']],
            ...$block(4, 'Workshop / Speech practice', ['Taller y práctica del speech', 'workshop', ['speech_practice']]),
            ['Final speech / Meeting', 'Speech final y reunión con representantes', 'speech', ['speech', 'meeting']],
        ];
    }
}
