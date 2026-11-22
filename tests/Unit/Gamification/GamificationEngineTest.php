<?php

namespace App\Tests\Unit\Gamification;

use App\Gamification\GamificationEngine;
use App\Gamification\RulesSchema;
use PHPUnit\Framework\TestCase;

class GamificationEngineTest extends TestCase
{
    private const REGLES = [
        'xp' => [
            ['evenement' => 'quiz.completed', 'points' => 'score * 10', 'typologie' => 'Joueur'],
            ['evenement' => 'quiz.completed', 'points' => '50', 'si' => 'victoire'],
            ['evenement' => 'quiz.*', 'points' => 1],
        ],
        'medailles' => [
            ['medaille' => 'Sans faute', 'evenement' => 'quiz.completed', 'si' => 'score == total'],
            ['medaille' => 'Participant', 'evenement' => 'quiz.started'],
        ],
    ];

    private GamificationEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new GamificationEngine();
    }

    public function testPointsEtMedaillesSurPartieParfaite(): void
    {
        $result = $this->engine->evaluate(self::REGLES, 'quiz.completed', ['score' => 3, 'total' => 3, 'victoire' => true]);

        $this->assertSame([
            ['typologie' => 'Joueur', 'points' => 30],
            ['typologie' => 'Général', 'points' => 50],
            ['typologie' => 'Général', 'points' => 1],
        ], $result['xp']);
        $this->assertSame(['Sans faute'], $result['medailles']);
    }

    public function testConditionsFausses(): void
    {
        $result = $this->engine->evaluate(self::REGLES, 'quiz.completed', ['score' => 2, 'total' => 3, 'victoire' => false]);

        $this->assertSame([['typologie' => 'Joueur', 'points' => 20], ['typologie' => 'Général', 'points' => 1]], $result['xp']);
        $this->assertSame([], $result['medailles']);
    }

    public function testCorrespondanceDesEvenements(): void
    {
        $this->assertTrue(GamificationEngine::matches('quiz.completed', 'quiz.completed'));
        $this->assertTrue(GamificationEngine::matches('quiz.*', 'quiz.answered'));
        $this->assertFalse(GamificationEngine::matches('quiz.*', 'jeu.completed'));
        $this->assertFalse(GamificationEngine::matches('quiz.completed', 'quiz.completed.bis'));

        $result = $this->engine->evaluate(self::REGLES, 'quiz.started', []);
        $this->assertSame(['Participant'], $result['medailles']);
        $this->assertSame([['typologie' => 'Général', 'points' => 1]], $result['xp']);
    }

    public function testRegleIgnoreeSiVariableAbsenteOuExpressionInvalide(): void
    {
        $regles = ['xp' => [
            ['evenement' => 'e', 'points' => 'inconnu * 2'],
            ['evenement' => 'e', 'points' => 'score +'],
            ['evenement' => 'e', 'points' => 'score * 2'],
        ]];
        $this->assertSame([['typologie' => 'Général', 'points' => 8]], $this->engine->evaluate($regles, 'e', ['score' => 4])['xp']);
    }

    public function testPointsBornesEtArrondis(): void
    {
        $regles = ['xp' => [
            ['evenement' => 'e', 'points' => 'score * 1000000'],
            ['evenement' => 'e', 'points' => 'score - 10'],
            ['evenement' => 'e', 'points' => 'score / 3'],
        ]];
        $this->assertSame([
            ['typologie' => 'Général', 'points' => GamificationEngine::POINTS_MAX],
            ['typologie' => 'Général', 'points' => 2],
        ], $this->engine->evaluate($regles, 'e', ['score' => 7])['xp']);
    }

    public function testAucuneFonctionPhpNiConstanteAccessible(): void
    {
        $regles = ['xp' => [
            ['evenement' => 'e', 'points' => "constant('PHP_INT_MAX')"],
            ['evenement' => 'e', 'points' => "system('id')"],
            ['evenement' => 'e', 'points' => 'max(5, score)'],
        ]];
        $this->assertSame([['typologie' => 'Général', 'points' => 5]], $this->engine->evaluate($regles, 'e', ['score' => 1])['xp']);
    }

    public function testSansReglesRienNEstAttribue(): void
    {
        $this->assertSame(['xp' => [], 'medailles' => []], $this->engine->evaluate(null, 'quiz.completed', ['score' => 3]));
    }

    public function testValidationDesRegles(): void
    {
        $this->assertSame([], RulesSchema::validate(self::REGLES));
        $this->assertSame(['Les règles doivent être un objet JSON'], RulesSchema::validate('pas un objet'));

        $errors = RulesSchema::validate(['xp' => [['evenement' => 'e', 'points' => 'score *']], 'medailles' => [['evenement' => 'e']]]);
        $this->assertContains('medailles[0].medaille : obligatoire', $errors);

        $errors = RulesSchema::validate(['xp' => [['evenement' => 'e', 'points' => 'score * (2']]]);
        $this->assertCount(1, $errors);
        $this->assertStringStartsWith('xp[0].points : expression invalide', $errors[0]);
    }
}
