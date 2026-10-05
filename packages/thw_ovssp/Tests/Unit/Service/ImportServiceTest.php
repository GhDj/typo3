<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Tests\Unit\Service;

use Doctrine\DBAL\Result;
use Init\Thw\Ovssp\Service\ImportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;

class ImportServiceTest extends TestCase
{
    private ImportService $subject;
    private ConnectionPool&MockObject $connectionPoolMock;

    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = dirname(__DIR__, 2) . '/Fixtures/';
        $this->connectionPoolMock = $this->createMock(ConnectionPool::class);
        $this->subject = new ImportService($this->connectionPoolMock);
    }

    private function createQueryBuilderMock(): QueryBuilder&MockObject
    {
        $qbMock = $this->createMock(QueryBuilder::class);
        $restrictionsMock = $this->createMock(QueryRestrictionContainerInterface::class);
        $exprMock = $this->createMock(ExpressionBuilder::class);

        $qbMock->method('getRestrictions')->willReturn($restrictionsMock);
        $restrictionsMock->method('removeAll')->willReturn($restrictionsMock);
        $qbMock->method('expr')->willReturn($exprMock);

        // Expression builder returns string placeholders for comparison methods
        $exprMock->method('eq')->willReturn('1=1');
        $exprMock->method('gt')->willReturn('1=1');
        $exprMock->method('lt')->willReturn('1=1');
        $exprMock->method('isNull')->willReturn('1=1');
        $compositeExprMock = $this->createMock(\TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression::class);
        $exprMock->method('or')->willReturn($compositeExprMock);

        $qbMock->method('createNamedParameter')->willReturnCallback(
            fn($value) => "'" . $value . "'"
        );

        // Fluent interface: all builder methods return $qbMock
        $qbMock->method('select')->willReturn($qbMock);
        $qbMock->method('count')->willReturn($qbMock);
        $qbMock->method('from')->willReturn($qbMock);
        $qbMock->method('where')->willReturn($qbMock);
        $qbMock->method('insert')->willReturn($qbMock);
        $qbMock->method('update')->willReturn($qbMock);
        $qbMock->method('set')->willReturn($qbMock);
        $qbMock->method('values')->willReturn($qbMock);
        $qbMock->method('orderBy')->willReturn($qbMock);
        $qbMock->method('addOrderBy')->willReturn($qbMock);
        $qbMock->method('setFirstResult')->willReturn($qbMock);
        $qbMock->method('setMaxResults')->willReturn($qbMock);
        $qbMock->method('leftJoin')->willReturn($qbMock);

        return $qbMock;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function createResultMock(array $rows): Result&MockObject
    {
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAllAssociative')->willReturn($rows);
        $consecutive = $rows;
        $consecutive[] = false;
        $resultMock->method('fetchAssociative')->willReturnOnConsecutiveCalls(...$consecutive);
        $resultMock->method('fetchOne')->willReturn(count($rows));
        return $resultMock;
    }

    // ---------------------------------------------------------------
    // General
    // ---------------------------------------------------------------

    public function testUnknownTypeReturnsError(): void
    {
        $result = $this->subject->import('/dev/null', 'invalid', '', 0);
        self::assertFalse($result['success']);
        self::assertStringContainsString('Unknown import type', $result['errors'][0]);
    }

    public function testImportWithNonexistentFile(): void
    {
        $result = $this->subject->import('/nonexistent/path/file.csv', 'orgunits', '', 0);
        self::assertFalse($result['success']);
        self::assertStringContainsString('Could not open', $result['errors'][0]);
    }

    // ---------------------------------------------------------------
    // OrgUnit import
    // ---------------------------------------------------------------

    public function testOrgUnitImportRejectsWrongHeader(): void
    {
        $result = $this->subject->import($this->fixturesPath . 'orgunits_bad_header.csv', 'orgunits', '', 0);
        self::assertFalse($result['success']);
        self::assertStringContainsString('Header mismatch', $result['errors'][0]);
    }

    public function testOrgUnitImportRejectsNonNumericUidAndBadOeCode(): void
    {
        $result = $this->subject->import($this->fixturesPath . 'orgunits_bad_data.csv', 'orgunits', '', 0);
        self::assertFalse($result['success']);
        self::assertCount(2, $result['errors']);
        self::assertStringContainsString('not numeric', $result['errors'][0]);
        self::assertStringContainsString('not exactly 4 characters', $result['errors'][1]);
    }

    public function testOrgUnitImportCreatesNewRecords(): void
    {
        $selectQb = $this->createQueryBuilderMock();
        $selectQb->method('executeQuery')->willReturn($this->createResultMock([]));

        $insertQb = $this->createQueryBuilderMock();
        $insertQb->expects(self::once())->method('executeStatement');

        $callCount = 0;
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturnCallback(
            function () use ($selectQb, $insertQb, &$callCount) {
                $callCount++;
                // First call = SELECT existing, subsequent = INSERT
                return $callCount === 1 ? $selectQb : $insertQb;
            }
        );

        $result = $this->subject->import($this->fixturesPath . 'orgunits_valid.csv', 'orgunits', '', 1);

        self::assertTrue($result['success']);
        self::assertSame(2, $result['total']);
        self::assertSame(2, $result['created']);
    }

    // ---------------------------------------------------------------
    // Directory import
    // ---------------------------------------------------------------

    public function testDirectoryImportCreatesRecords(): void
    {
        $selectQb = $this->createQueryBuilderMock();
        $selectQb->method('executeQuery')->willReturn($this->createResultMock([]));

        $insertQb = $this->createQueryBuilderMock();
        $insertQb->method('executeStatement')->willReturn(1);

        $callCount = 0;
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturnCallback(
            function () use ($selectQb, $insertQb, &$callCount) {
                $callCount++;
                return $callCount === 1 ? $selectQb : $insertQb;
            }
        );

        $result = $this->subject->import($this->fixturesPath . 'directories_valid.csv', 'directories', '', 1);

        self::assertTrue($result['success']);
        self::assertSame(3, $result['total']);
        self::assertSame(3, $result['created']);
    }

    // ---------------------------------------------------------------
    // User import — abort cases
    // ---------------------------------------------------------------

    public function testUserImportFailsWithoutOrgUnits(): void
    {
        $qb = $this->createQueryBuilderMock();
        $qb->method('executeQuery')->willReturn($this->createResultMock([]));
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qb);

        $result = $this->subject->import($this->fixturesPath . 'users_valid.csv', 'users', 'EA', 1);

        self::assertFalse($result['success']);
        self::assertStringContainsString('No OrgUnits found', $result['errors'][0]);
    }

    public function testUserImportRejectsWrongHeader(): void
    {
        $qb = $this->createQueryBuilderMock();
        $qb->method('executeQuery')->willReturn($this->createResultMock([
            ['uid' => 1, 'oe_code' => 'OAAC'],
        ]));
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qb);

        $result = $this->subject->import($this->fixturesPath . 'orgunits_bad_header.csv', 'users', 'EA', 1);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Header mismatch', $result['errors'][0]);
    }

    // ---------------------------------------------------------------
    // User import — skip invalid rows
    // ---------------------------------------------------------------

    public function testUserImportSkipsNonNumericThwUid(): void
    {
        $qb = $this->createQueryBuilderMock();
        // First call: orgunit lookup, second call: existing users, third+: insert/update/deactivation
        $orgUnitResult = $this->createResultMock([['uid' => 1, 'oe_code' => 'OAAC']]);
        $emptyResult = $this->createResultMock([]);
        $qb->method('executeQuery')->willReturnOnConsecutiveCalls($orgUnitResult, $emptyResult);
        $qb->method('executeStatement')->willReturn(0);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qb);

        $result = $this->subject->import($this->fixturesPath . 'users_bad_uid.csv', 'users', 'EA', 1);

        self::assertTrue($result['success']);
        self::assertSame(1, $result['total']);
        self::assertSame(1, $result['skipped']);
        self::assertSame(0, $result['created']);
        self::assertStringContainsString('not numeric', $result['errors'][0]);
    }

    public function testUserImportSkipsUnknownOrgUnit(): void
    {
        $qb = $this->createQueryBuilderMock();
        $orgUnitResult = $this->createResultMock([['uid' => 1, 'oe_code' => 'OAAC']]);
        $emptyResult = $this->createResultMock([]);
        $qb->method('executeQuery')->willReturnOnConsecutiveCalls($orgUnitResult, $emptyResult);
        $qb->method('executeStatement')->willReturn(0);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qb);

        $result = $this->subject->import($this->fixturesPath . 'users_unknown_oe.csv', 'users', 'EA', 1);

        self::assertTrue($result['success']);
        self::assertSame(1, $result['total']);
        self::assertSame(1, $result['skipped']);
        self::assertStringContainsString('oe_code', $result['errors'][0]);
    }

    public function testUserImportMixedValidAndInvalidRows(): void
    {
        $qb = $this->createQueryBuilderMock();
        $orgUnitResult = $this->createResultMock([['uid' => 1, 'oe_code' => 'OAAC']]);
        $emptyResult = $this->createResultMock([]);
        $qb->method('executeQuery')->willReturnOnConsecutiveCalls($orgUnitResult, $emptyResult);
        $qb->method('executeStatement')->willReturn(1); // each insert returns 1
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qb);

        $result = $this->subject->import($this->fixturesPath . 'users_mixed.csv', 'users', 'EA', 1);

        self::assertTrue($result['success']);
        self::assertSame(6, $result['total']);
        self::assertSame(3, $result['created']);
        self::assertSame(3, $result['skipped']);
        self::assertCount(3, $result['errors']);
    }

    public function testUserImportSkippedErrorsContainLineNumbers(): void
    {
        $qb = $this->createQueryBuilderMock();
        $orgUnitResult = $this->createResultMock([['uid' => 1, 'oe_code' => 'OAAC']]);
        $emptyResult = $this->createResultMock([]);
        $qb->method('executeQuery')->willReturnOnConsecutiveCalls($orgUnitResult, $emptyResult);
        $qb->method('executeStatement')->willReturn(1);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qb);

        $result = $this->subject->import($this->fixturesPath . 'users_mixed.csv', 'users', 'EA', 1);

        self::assertStringContainsString('Line 3', $result['errors'][0]);
        self::assertStringContainsString('NOTNUM', $result['errors'][0]);
        self::assertStringContainsString('Line 5', $result['errors'][1]);
        self::assertStringContainsString('#NV', $result['errors'][1]);
        self::assertStringContainsString('Line 6', $result['errors'][2]);
        self::assertStringContainsString('ZZZZ', $result['errors'][2]);
    }

    public function testUserImportAllValidSucceeds(): void
    {
        $qb = $this->createQueryBuilderMock();
        $orgUnitResult = $this->createResultMock([['uid' => 1, 'oe_code' => 'OAAC']]);
        $emptyResult = $this->createResultMock([]);
        $qb->method('executeQuery')->willReturnOnConsecutiveCalls($orgUnitResult, $emptyResult);
        $qb->method('executeStatement')->willReturn(1);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qb);

        $result = $this->subject->import($this->fixturesPath . 'users_valid.csv', 'users', 'EA', 1);

        self::assertTrue($result['success']);
        self::assertSame(3, $result['total']);
        self::assertSame(3, $result['created']);
        self::assertSame(0, $result['skipped']);
        self::assertEmpty($result['errors']);
    }

    public function testUserImportAllRowsSkippedStillSucceeds(): void
    {
        $qb = $this->createQueryBuilderMock();
        $orgUnitResult = $this->createResultMock([['uid' => 1, 'oe_code' => 'OAAC']]);
        $emptyResult = $this->createResultMock([]);
        $qb->method('executeQuery')->willReturnOnConsecutiveCalls($orgUnitResult, $emptyResult);
        $qb->method('executeStatement')->willReturn(0);
        $this->connectionPoolMock->method('getQueryBuilderForTable')->willReturn($qb);

        $result = $this->subject->import($this->fixturesPath . 'users_bad_uid.csv', 'users', 'EA', 1);

        self::assertTrue($result['success']);
        self::assertSame(1, $result['total']);
        self::assertSame(0, $result['created']);
        self::assertSame(1, $result['skipped']);
    }
}
