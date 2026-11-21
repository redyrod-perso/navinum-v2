<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Interactif;
use App\Entity\LaunchToken;
use App\Entity\Parcours;
use PHPUnit\Framework\TestCase;

class ParcoursInteractifTest extends TestCase
{
    private function quiz(): Interactif
    {
        return (new Interactif())->setLibelle('Quiz')->setVariable(json_encode([
            'type' => 'object',
            'properties' => [
                'tempsParQuestion' => ['type' => 'integer', 'default' => 20],
                'themes' => ['type' => 'array', 'default' => [['id' => 'histoire']]],
            ],
        ]));
    }

    public function testConfigParDefautDepuisLeSchema(): void
    {
        $quiz = $this->quiz();
        $this->assertTrue($quiz->isConfigurable());
        $this->assertSame(['tempsParQuestion' => 20, 'themes' => [['id' => 'histoire']]], $quiz->getDefaultConfig());
    }

    public function testVariableJsonSimpleUtiliseeTelleQuelle(): void
    {
        $i = (new Interactif())->setLibelle('Jeu')->setVariable('{"niveau": 3}');
        $this->assertFalse($i->isConfigurable());
        $this->assertSame(['niveau' => 3], $i->getDefaultConfig());
    }

    public function testVariableVideOuInvalide(): void
    {
        $this->assertSame([], (new Interactif())->setLibelle('A')->getDefaultConfig());
        $this->assertSame([], (new Interactif())->setLibelle('B')->setVariable('{pas du json')->getDefaultConfig());
    }

    public function testConfigEffectiveDuParcoursSurchargeLesDefauts(): void
    {
        $parcours = (new Parcours())->setLibelle('Musée A');
        $placement = $parcours->addInteractif($this->quiz(), ['tempsParQuestion' => 45]);

        $this->assertSame(['tempsParQuestion' => 45, 'themes' => [['id' => 'histoire']]], $placement->getEffectiveConfig());
    }

    public function testOrdreCroissantALAjout(): void
    {
        $parcours = (new Parcours())->setLibelle('Musée B');
        $a = $parcours->addInteractif($this->quiz());
        $b = $parcours->addInteractif($this->quiz());
        $this->assertSame([0, 1], [$a->getOrdre(), $b->getOrdre()]);
        $this->assertCount(2, $parcours->getInteractifs());
    }

    public function testJetonDeLancement(): void
    {
        $quiz = $this->quiz();
        $placement = (new Parcours())->setLibelle('Musée C')->addInteractif($quiz, ['tempsParQuestion' => 10]);

        $token = new LaunchToken($quiz, $placement);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token->getToken());
        $this->assertFalse($token->isExpired());
        $this->assertTrue($token->isExpired(new \DateTimeImmutable('+1 day')));
        $this->assertSame(10, $token->getConfig()['tempsParQuestion']);

        $apercu = new LaunchToken($quiz);
        $this->assertSame(20, $apercu->getConfig()['tempsParQuestion']);
    }
}
