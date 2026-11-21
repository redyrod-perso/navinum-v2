<?php

namespace App\Tests\Unit\Service;

use App\Service\ConfigSchema;
use PHPUnit\Framework\TestCase;

class ConfigSchemaTest extends TestCase
{
    private const SCHEMA = [
        'type' => 'object',
        'required' => ['duree'],
        'properties' => [
            'duree' => ['type' => 'integer', 'minimum' => 10, 'maximum' => 120, 'default' => 30],
            'mode' => ['type' => 'string', 'enum' => ['solo', 'equipe'], 'default' => 'solo'],
            'themes' => [
                'type' => 'array',
                'minItems' => 1,
                'items' => ['type' => 'object', 'required' => ['id'], 'properties' => ['id' => ['type' => 'string', 'minLength' => 1]]],
                'default' => [['id' => 'histoire']],
            ],
            'options' => ['type' => 'object', 'properties' => ['son' => ['type' => 'boolean', 'default' => true]]],
            'sansDefaut' => ['type' => 'string'],
        ],
    ];

    public function testDetecteUnSchema(): void
    {
        $this->assertTrue(ConfigSchema::isSchema(self::SCHEMA));
        $this->assertFalse(ConfigSchema::isSchema(['duree' => 30]));
    }

    public function testValeursParDefautRecursives(): void
    {
        $this->assertSame(
            ['duree' => 30, 'mode' => 'solo', 'themes' => [['id' => 'histoire']], 'options' => ['son' => true]],
            ConfigSchema::defaults(self::SCHEMA),
        );
    }

    public function testFusionObjetsEtRemplacementDesListes(): void
    {
        $merged = ConfigSchema::merge(
            ['duree' => 30, 'themes' => [['id' => 'a'], ['id' => 'b']], 'options' => ['son' => true, 'musique' => false]],
            ['themes' => [['id' => 'c']], 'options' => ['son' => false]],
        );
        $this->assertSame(['duree' => 30, 'themes' => [['id' => 'c']], 'options' => ['son' => false, 'musique' => false]], $merged);
    }

    public function testConfigValide(): void
    {
        $this->assertSame([], ConfigSchema::validate(self::SCHEMA, ['duree' => 60, 'mode' => 'equipe', 'themes' => [['id' => 'x']]]));
    }

    public function testErreursAvecChemin(): void
    {
        $errors = ConfigSchema::validate(self::SCHEMA, [
            'duree' => 5,
            'mode' => 'autre',
            'themes' => [['id' => '']],
            'options' => ['son' => 'oui'],
        ]);
        $this->assertContains('duree : doit être ≥ 10', $errors);
        $this->assertContains('mode : valeur non autorisée', $errors);
        $this->assertContains('themes[0].id : au moins 1 caractère(s)', $errors);
        $this->assertContains('options.son : type attendu « boolean »', $errors);
    }

    public function testChampObligatoireEtListeVide(): void
    {
        $errors = ConfigSchema::validate(self::SCHEMA, ['themes' => []]);
        $this->assertContains('duree : obligatoire', $errors);
        $this->assertContains('themes : au moins 1 élément(s)', $errors);
    }

    public function testTypeRacine(): void
    {
        $this->assertSame(['configuration : type attendu « object »'], ConfigSchema::validate(self::SCHEMA, [1, 2]));
    }
}
