<?php

namespace App\Tests\Unit\Service;

use App\Repository\LogVisiteRepository;
use App\Service\LogVisiteService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class LogVisiteServiceTest extends TestCase
{
    private $entityManager;
    private $logVisiteRepository;
    private LogVisiteService $logVisiteService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logVisiteRepository = $this->createMock(LogVisiteRepository::class);

        $this->logVisiteService = new LogVisiteService(
            $this->logVisiteRepository,
            $this->entityManager
        );
    }

    public function testGetTotalWithVisiteurId(): void
    {
        $visiteurId = Uuid::v4();
        $expectedTotal = 250;

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleResult')
            ->willReturn(['total' => $expectedTotal]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('SUM(lv.score) as total')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('lv.visiteur = :visiteurId')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('visiteurId', $visiteurId, 'uuid')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $this->logVisiteRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('lv')
            ->willReturn($queryBuilder);

        $result = $this->logVisiteService->getTotal(['visiteur_id' => $visiteurId]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals($expectedTotal, $result['total']);
    }

    public function testGetTotalWithMultipleParams(): void
    {
        $visiteurId = Uuid::v4();
        $visiteId = Uuid::v4();
        $interactifId = Uuid::v4();
        $expectedTotal = 100;

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleResult')
            ->willReturn(['total' => $expectedTotal]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->logVisiteRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $result = $this->logVisiteService->getTotal([
            'visiteur_id' => $visiteurId,
            'visite_id' => $visiteId,
            'interactif_id' => $interactifId
        ]);

        $this->assertEquals($expectedTotal, $result['total']);
    }

    public function testGetTotalWhenNoData(): void
    {
        $visiteurId = Uuid::v4();

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleResult')
            ->willReturn(['total' => null]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->logVisiteRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $result = $this->logVisiteService->getTotal(['visiteur_id' => $visiteurId]);

        $this->assertEquals(0, $result['total']);
    }

    public function testGetVisiteurExpositions(): void
    {
        $visiteurId = Uuid::v4();
        $expositionId1 = Uuid::v4();
        $expositionId2 = Uuid::v4();
        $interactifId1 = Uuid::v4();
        $interactifId2 = Uuid::v4();

        $mockResults = [
            [
                'exposition_id' => $expositionId1,
                'interactif_id' => $interactifId1,
                'libelle' => 'Expo 1',
                'logo' => 'logo1.png',
                'description' => 'Description 1',
                'synopsis' => 'Synopsis 1'
            ],
            [
                'exposition_id' => $expositionId1,
                'interactif_id' => $interactifId2,
                'libelle' => 'Expo 1',
                'logo' => 'logo1.png',
                'description' => 'Description 1',
                'synopsis' => 'Synopsis 1'
            ],
            [
                'exposition_id' => $expositionId2,
                'interactif_id' => $interactifId1,
                'libelle' => 'Expo 2',
                'logo' => 'logo2.png',
                'description' => 'Description 2',
                'synopsis' => 'Synopsis 2'
            ]
        ];

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($mockResults);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('addSelect')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->logVisiteRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $result = $this->logVisiteService->getVisiteurExpositions($visiteurId);

        $this->assertIsArray($result);
        $this->assertCount(2, $result); // 2 expositions
        $this->assertEquals($expositionId1->__toString(), $result[0]['exposition_id']);
        $this->assertCount(2, $result[0]['interactif_id']); // Expo 1 a 2 interactifs
        $this->assertEquals($expositionId2->__toString(), $result[1]['exposition_id']);
        $this->assertCount(1, $result[1]['interactif_id']); // Expo 2 a 1 interactif
    }

    public function testGetVisiteurInteractifsExposition(): void
    {
        $visiteurId = Uuid::v4();
        $expositionId1 = Uuid::v4();
        $expositionId2 = Uuid::v4();
        $interactifId1 = Uuid::v4();
        $interactifId2 = Uuid::v4();

        $mockResults = [
            ['interactif_id' => $interactifId1],
            ['interactif_id' => $interactifId2]
        ];

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($mockResults);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->logVisiteRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $result = $this->logVisiteService->getVisiteurInteractifsExposition(
            $visiteurId,
            [$expositionId1, $expositionId2]
        );

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals($interactifId1->__toString(), $result[0]);
        $this->assertEquals($interactifId2->__toString(), $result[1]);
    }

    public function testGetHighScore(): void
    {
        $visiteurId = Uuid::v4();
        $interactifId = Uuid::v4();
        $visiteId = Uuid::v4();

        // Mock entities
        $mockVisiteur = $this->createMock(\App\Entity\Visiteur::class);
        $mockVisiteur->method('getId')->willReturn($visiteurId);

        $mockInteractif = $this->createMock(\App\Entity\Interactif::class);
        $mockInteractif->method('getId')->willReturn($interactifId);

        $mockVisite = $this->createMock(\App\Entity\Visite::class);
        $mockVisite->method('getId')->willReturn($visiteId);

        $mockLogVisite = $this->createMock(\App\Entity\LogVisite::class);
        $mockLogVisite->method('getScore')->willReturn(1000);
        $mockLogVisite->method('getInteractif')->willReturn($mockInteractif);
        $mockLogVisite->method('getVisiteur')->willReturn($mockVisiteur);
        $mockLogVisite->method('getVisite')->willReturn($mockVisite);
        $mockLogVisite->method('getStartAt')->willReturn(new \DateTime('2025-01-01 10:00:00'));
        $mockLogVisite->method('getEndAt')->willReturn(new \DateTime('2025-01-01 10:30:00'));

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$mockLogVisite]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('addSelect')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->logVisiteRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $result = $this->logVisiteService->getHighScore($interactifId, null, false);

        $this->assertIsArray($result);
        $this->assertEquals(1000, $result['highScore']);
        $this->assertEquals($visiteurId->__toString(), $result['visiteur_id']);
    }

    public function testGetHighScoreWhenNoData(): void
    {
        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('addSelect')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->logVisiteRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $result = $this->logVisiteService->getHighScore();

        $this->assertNull($result);
    }
}
