<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Tests\Unit\Service;

use Doctrine\DBAL\Result;
use Init\Thw\Ovssp\Service\RightsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;

class RightsServiceTest extends TestCase
{
    private RightsService $subject;
    private ConnectionPool&MockObject $connectionPoolMock;

    protected function setUp(): void
    {
        $this->connectionPoolMock = $this->createMock(ConnectionPool::class);
        $this->subject = new RightsService($this->connectionPoolMock);
    }

    private function createQueryBuilderMock(): QueryBuilder&MockObject
    {
        $qbMock = $this->createMock(QueryBuilder::class);
        $restrictionsMock = $this->createMock(QueryRestrictionContainerInterface::class);
        $exprMock = $this->createMock(ExpressionBuilder::class);

        $qbMock->method('getRestrictions')->willReturn($restrictionsMock);
        $restrictionsMock->method('removeAll')->willReturn($restrictionsMock);
        $qbMock->method('expr')->willReturn($exprMock);

        $exprMock->method('eq')->willReturn('1=1');
        $exprMock->method('gt')->willReturn('1=1');

        $qbMock->method('createNamedParameter')->willReturnCallback(
            static fn (mixed $value): string => is_string($value) ? "'" . $value . "'" : (string) $value
        );
        $qbMock->method('quoteIdentifier')->willReturnCallback(
            static fn (string $identifier): string => '`' . $identifier . '`'
        );

        // Fluent interface
        $qbMock->method('select')->willReturn($qbMock);
        $qbMock->method('count')->willReturn($qbMock);
        $qbMock->method('from')->willReturn($qbMock);
        $qbMock->method('join')->willReturn($qbMock);
        $qbMock->method('leftJoin')->willReturn($qbMock);
        $qbMock->method('where')->willReturn($qbMock);
        $qbMock->method('andWhere')->willReturn($qbMock);
        $qbMock->method('orderBy')->willReturn($qbMock);
        $qbMock->method('addOrderBy')->willReturn($qbMock);

        return $qbMock;
    }

    public function testGetCombinedRightsResolvesWriteBeatsRead(): void
    {
        $qbMock = $this->createQueryBuilderMock();
        $resultMock = $this->createMock(Result::class);

        $resultMock->method('fetchAllAssociative')->willReturn([
            ['directory' => 1, 'access' => 'read', 'name' => 'Allgemein', 'sort_key' => 1],
            ['directory' => 1, 'access' => 'write', 'name' => 'Allgemein', 'sort_key' => 1],
        ]);

        $qbMock->method('executeQuery')->willReturn($resultMock);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qbMock);

        $rights = $this->subject->getCombinedRightsForUser(42);

        self::assertCount(1, $rights);
        self::assertSame('write', $rights[1]['access']);
        self::assertSame('Allgemein', $rights[1]['directory_name']);
    }

    public function testGetCombinedRightsResolvesDenyOverridesAll(): void
    {
        $qbMock = $this->createQueryBuilderMock();
        $resultMock = $this->createMock(Result::class);

        $resultMock->method('fetchAllAssociative')->willReturn([
            ['directory' => 1, 'access' => 'write', 'name' => 'Allgemein', 'sort_key' => 1],
            ['directory' => 1, 'access' => 'deny', 'name' => 'Allgemein', 'sort_key' => 1],
        ]);

        $qbMock->method('executeQuery')->willReturn($resultMock);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qbMock);

        $rights = $this->subject->getCombinedRightsForUser(42);

        self::assertCount(1, $rights);
        self::assertSame('deny', $rights[1]['access']);
    }

    public function testGetCombinedRightsMergesMultipleDirectories(): void
    {
        $qbMock = $this->createQueryBuilderMock();
        $resultMock = $this->createMock(Result::class);

        $resultMock->method('fetchAllAssociative')->willReturn([
            ['directory' => 1, 'access' => 'read', 'name' => 'Allgemein', 'sort_key' => 1],
            ['directory' => 2, 'access' => 'write', 'name' => 'Verwaltung', 'sort_key' => 2],
            ['directory' => 3, 'access' => 'deny', 'name' => 'Geheim', 'sort_key' => 3],
        ]);

        $qbMock->method('executeQuery')->willReturn($resultMock);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qbMock);

        $rights = $this->subject->getCombinedRightsForUser(42);

        self::assertCount(3, $rights);
        self::assertSame('read', $rights[1]['access']);
        self::assertSame('write', $rights[2]['access']);
        self::assertSame('deny', $rights[3]['access']);
    }

    public function testGetCombinedRightsReturnsEmptyForNoRoles(): void
    {
        $qbMock = $this->createQueryBuilderMock();
        $resultMock = $this->createMock(Result::class);

        $resultMock->method('fetchAllAssociative')->willReturn([]);

        $qbMock->method('executeQuery')->willReturn($resultMock);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qbMock);

        $rights = $this->subject->getCombinedRightsForUser(42);

        self::assertCount(0, $rights);
    }

    public function testGetCombinedRightsSortsBySortKeyThenName(): void
    {
        $qbMock = $this->createQueryBuilderMock();
        $resultMock = $this->createMock(Result::class);

        $resultMock->method('fetchAllAssociative')->willReturn([
            ['directory' => 2, 'access' => 'read', 'name' => 'Zettel', 'sort_key' => null],
            ['directory' => 1, 'access' => 'write', 'name' => 'Allgemein', 'sort_key' => 1],
            ['directory' => 3, 'access' => 'read', 'name' => 'Ablage', 'sort_key' => null],
        ]);

        $qbMock->method('executeQuery')->willReturn($resultMock);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qbMock);

        $rights = $this->subject->getCombinedRightsForUser(42);

        $names = array_column(array_values($rights), 'directory_name');
        self::assertSame(['Allgemein', 'Ablage', 'Zettel'], $names);
    }

    public function testGetRoleUidsForUser(): void
    {
        $qbMock = $this->createQueryBuilderMock();
        $resultMock = $this->createMock(Result::class);

        $resultMock->method('fetchAllAssociative')->willReturn([
            ['uid_foreign' => 1],
            ['uid_foreign' => 3],
            ['uid_foreign' => 5],
        ]);

        $qbMock->method('executeQuery')->willReturn($resultMock);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qbMock);

        $uids = $this->subject->getRoleUidsForUser(42);

        self::assertSame([1, 3, 5], $uids);
    }

    public function testGetRoleUidsReturnsEmptyForNoAssignments(): void
    {
        $qbMock = $this->createQueryBuilderMock();
        $resultMock = $this->createMock(Result::class);

        $resultMock->method('fetchAllAssociative')->willReturn([]);

        $qbMock->method('executeQuery')->willReturn($resultMock);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qbMock);

        $uids = $this->subject->getRoleUidsForUser(99);

        self::assertSame([], $uids);
    }

    public function testGetCombinedRightsDenyOverridesWriteAndRead(): void
    {
        $qbMock = $this->createQueryBuilderMock();
        $resultMock = $this->createMock(Result::class);

        $resultMock->method('fetchAllAssociative')->willReturn([
            ['directory' => 1, 'access' => 'read', 'name' => 'Shared', 'sort_key' => 1],
            ['directory' => 1, 'access' => 'write', 'name' => 'Shared', 'sort_key' => 1],
            ['directory' => 1, 'access' => 'deny', 'name' => 'Shared', 'sort_key' => 1],
        ]);

        $qbMock->method('executeQuery')->willReturn($resultMock);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qbMock);

        $rights = $this->subject->getCombinedRightsForUser(42);

        self::assertSame('deny', $rights[1]['access']);
    }
}
