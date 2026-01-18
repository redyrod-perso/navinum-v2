<?php

namespace App\Tests\Unit\Service;

use App\Entity\Visiteur;
use App\Entity\Xp;
use App\Entity\Typologie;
use App\Repository\XpRepository;
use App\Service\XpService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class XpServiceTest extends TestCase
{
    private $entityManager;
    private $xpRepository;
    private XpService $xpService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->xpRepository = $this->createMock(XpRepository::class);

        $this->xpService = new XpService(
            $this->xpRepository,
            $this->entityManager
        );
    }

    public function testGetTotalByVisiteur(): void
    {
        $visiteurId = Uuid::v4();
        $expectedTotal = 150;

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleResult')
            ->willReturn(['total' => $expectedTotal]);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('SUM(x.score) as total')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('x.visiteur = :visiteurId')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('visiteurId', $visiteurId, 'uuid')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $this->xpRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('x')
            ->willReturn($queryBuilder);

        $result = $this->xpService->getTotalByVisiteur($visiteurId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals($expectedTotal, $result['total']);
    }

    public function testGetTotalByVisiteurWhenNoXp(): void
    {
        $visiteurId = Uuid::v4();

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleResult')
            ->willReturn(['total' => null]);

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('SUM(x.score) as total')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('x.visiteur = :visiteurId')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('visiteurId', $visiteurId, 'uuid')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $this->xpRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('x')
            ->willReturn($queryBuilder);

        $result = $this->xpService->getTotalByVisiteur($visiteurId);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['total']);
    }

    public function testGetTotalBestVisiteur(): void
    {
        $visiteurId = Uuid::v4();
        $expectedTotal = 250;

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([
                ['total' => $expectedTotal, 'visiteur_id' => $visiteurId]
            ]);

        $this->entityManager->expects($this->once())
            ->method('createQuery')
            ->willReturn($query);

        $result = $this->xpService->getTotalBestVisiteur();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('visiteur_id', $result);
        $this->assertEquals($expectedTotal, $result['total']);
        $this->assertEquals($visiteurId, $result['visiteur_id']);
    }

    public function testGetTotalBestVisiteurWhenNoData(): void
    {
        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([]);

        $this->entityManager->expects($this->once())
            ->method('createQuery')
            ->willReturn($query);

        $result = $this->xpService->getTotalBestVisiteur();

        $this->assertNull($result);
    }
}
