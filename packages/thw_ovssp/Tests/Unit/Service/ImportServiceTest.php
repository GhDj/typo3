<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Tests\Unit\Service;

use Doctrine\DBAL\Result;
use Init\Thw\Ovssp\Service\ImportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class ImportServiceTest extends TestCase
{
    private ImportService $subject;
    private ConnectionPool&MockObject $connectionPoolMock;
    private Connection&MockObject $connectionMock;

    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = dirname(__DIR__, 2) . '/Fixtures/';
        $this->connectionPoolMock = $this->createMock(ConnectionPool::class);
        $this->connectionMock = $this->createMock(Connection::class);

        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->subject = new ImportService($this->connectionPoolMock);
    }

    // ---------------------------------------------------------------
    // Unknown type
    // ---------------------------------------------------------------

    public function testUnknownTypeReturnsError(): void
    {
        $result = $this->subject->import('/dev/null', 'invalid', '', 0);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Unknown import type', $result['errors'][0]);
    }

    // ---------------------------------------------------------------
    // OrgUnit import
    // ---------------------------------------------------------------

    public function testOrgUnitImportRejectsWrongHeader(): void
    {
        $result = $this->subject->import(
            $this->fixturesPath . 'orgunits_bad_header.csv',
            'orgunits',
            '',
            0
        );

        self::assertFalse($result['success']);
        self::assertStringContainsString('Header mismatch', $result['errors'][0]);
    }

    public function testOrgUnitImportRejectsNonNumericUidAndBadOeCode(): void
    {
        $result = $this->subject->import(
            $this->fixturesPath . 'orgunits_bad_data.csv',
            'orgunits',
            '',
            0
        );

        self::assertFalse($result['success']);
        self::assertCount(2, $result['errors']);
        self::assertStringContainsString('not numeric', $result['errors'][0]);
        self::assertStringContainsString('not exactly 4 characters', $result['errors'][1]);
    }

    public function testOrgUnitImportCreatesNewRecords(): void
    {
        // Mock: no existing records
        $emptyResult = $this->createMock(Result::class);
        $emptyResult->method('fetchAssociative')->willReturn(false);
        $this->connectionMock->method('executeQuery')->willReturn($emptyResult);
        $this->connectionMock->expects(self::exactly(2))->method('insert');

        $result = $this->subject->import(
            $this->fixturesPath . 'orgunits_valid.csv',
            'orgunits',
            '',
            1
        );

        self::assertTrue($result['success']);
        self::assertSame(2, $result['total']);
        self::assertSame(2, $result['created']);
        self::assertSame(0, $result['updated']);
    }

    public function testOrgUnitImportUpdatesExistingRecords(): void
    {
        // Mock: one existing record with different name
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'uid' => 1,
                'thw_oe_uid' => 2000612,
                'oe_code' => 'OAAC',
                'name' => 'OLD NAME',
                'mail_address' => 'ov-aachen@thw.de',
                'regionalbereich_code' => 'GAAC',
                'landesverband_code' => 'LVNW',
            ],
            false
        );
        $this->connectionMock->method('executeQuery')->willReturn($resultMock);
        $this->connectionMock->expects(self::once())->method('insert');
        $this->connectionMock->expects(self::once())->method('update');

        $result = $this->subject->import(
            $this->fixturesPath . 'orgunits_valid.csv',
            'orgunits',
            '',
            1
        );

        self::assertTrue($result['success']);
        self::assertSame(2, $result['total']);
        self::assertSame(1, $result['created']);
        self::assertSame(1, $result['updated']);
    }

    public function testOrgUnitImportReportsUnchanged(): void
    {
        // Mock: existing record with identical data
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'uid' => 1,
                'thw_oe_uid' => 2000612,
                'oe_code' => 'OAAC',
                'name' => 'OV Aachen',
                'mail_address' => 'ov-aachen@thw.de',
                'regionalbereich_code' => 'GAAC',
                'landesverband_code' => 'LVNW',
            ],
            false
        );
        $this->connectionMock->method('executeQuery')->willReturn($resultMock);
        $this->connectionMock->expects(self::once())->method('insert'); // OAAL is new
        $this->connectionMock->expects(self::never())->method('update');

        $result = $this->subject->import(
            $this->fixturesPath . 'orgunits_valid.csv',
            'orgunits',
            '',
            1
        );

        self::assertTrue($result['success']);
        self::assertSame(1, $result['unchanged']);
    }

    // ---------------------------------------------------------------
    // Directory import
    // ---------------------------------------------------------------

    public function testDirectoryImportCreatesRecords(): void
    {
        $emptyResult = $this->createMock(Result::class);
        $emptyResult->method('fetchAssociative')->willReturn(false);
        $this->connectionMock->method('executeQuery')->willReturn($emptyResult);
        $this->connectionMock->expects(self::exactly(3))->method('insert');

        $result = $this->subject->import(
            $this->fixturesPath . 'directories_valid.csv',
            'directories',
            '',
            1
        );

        self::assertTrue($result['success']);
        self::assertSame(3, $result['total']);
        self::assertSame(3, $result['created']);
    }

    public function testDirectoryImportHandlesNullableSortKey(): void
    {
        $emptyResult = $this->createMock(Result::class);
        $emptyResult->method('fetchAssociative')->willReturn(false);

        $insertedRows = [];
        $this->connectionMock->method('executeQuery')->willReturn($emptyResult);
        $this->connectionMock->method('insert')->willReturnCallback(
            function (string $table, array $data) use (&$insertedRows) {
                $insertedRows[] = $data;
                return 1;
            }
        );

        $this->subject->import(
            $this->fixturesPath . 'directories_valid.csv',
            'directories',
            '',
            1
        );

        // First two rows have sort_key 1 and 2, third (AGT) has null
        self::assertSame(1, $insertedRows[0]['sort_key']);
        self::assertSame(2, $insertedRows[1]['sort_key']);
        self::assertNull($insertedRows[2]['sort_key']);
    }

    // ---------------------------------------------------------------
    // User import
    // ---------------------------------------------------------------

    public function testUserImportFailsWithoutOrgUnits(): void
    {
        $emptyResult = $this->createMock(Result::class);
        $emptyResult->method('fetchAssociative')->willReturn(false);
        $this->connectionMock->method('executeQuery')->willReturn($emptyResult);

        $result = $this->subject->import(
            $this->fixturesPath . 'users_valid.csv',
            'users',
            'EA',
            1
        );

        self::assertFalse($result['success']);
        self::assertStringContainsString('No OrgUnits found', $result['errors'][0]);
    }

    public function testUserImportRejectsNonNumericThwUid(): void
    {
        // Mock: OrgUnit lookup returns one record, then user validation runs
        $orgUnitResult = $this->createMock(Result::class);
        $orgUnitResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            ['uid' => 1, 'thw_oe_uid' => 2000612],
            false
        );
        $this->connectionMock->method('executeQuery')->willReturn($orgUnitResult);

        $result = $this->subject->import(
            $this->fixturesPath . 'users_bad_uid.csv',
            'users',
            'EA',
            1
        );

        self::assertFalse($result['success']);
        self::assertStringContainsString('not numeric', $result['errors'][0]);
    }

    public function testUserImportRejectsUnknownOrgUnit(): void
    {
        // Mock: OrgUnit lookup returns one record (2000612), but CSV references 9999999
        $orgUnitResult = $this->createMock(Result::class);
        $orgUnitResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            ['uid' => 1, 'thw_oe_uid' => 2000612],
            false
        );
        $this->connectionMock->method('executeQuery')->willReturn($orgUnitResult);

        $result = $this->subject->import(
            $this->fixturesPath . 'users_unknown_oe.csv',
            'users',
            'EA',
            1
        );

        self::assertFalse($result['success']);
        self::assertStringContainsString('not found in OrgUnit table', $result['errors'][0]);
    }

    public function testUserImportInsertsAndDeactivates(): void
    {
        // First call: OrgUnit lookup
        $orgUnitResult = $this->createMock(Result::class);
        $orgUnitResult->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            ['uid' => 1, 'thw_oe_uid' => 2000612],
            false
        );

        $this->connectionMock->method('executeQuery')->willReturn($orgUnitResult);

        // executeStatement: first call = batch upsert (returns 3 = 3 inserts), second = deactivation
        $this->connectionMock->method('executeStatement')->willReturnOnConsecutiveCalls(3, 0);

        $result = $this->subject->import(
            $this->fixturesPath . 'users_valid.csv',
            'users',
            'EA',
            1
        );

        self::assertTrue($result['success']);
        self::assertSame(3, $result['total']);
        self::assertSame(3, $result['created']);
        self::assertSame(0, $result['deactivated']);
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    public function testImportWithNonexistentFile(): void
    {
        $result = $this->subject->import(
            '/nonexistent/path/file.csv',
            'orgunits',
            '',
            0
        );

        self::assertFalse($result['success']);
        self::assertStringContainsString('Could not open', $result['errors'][0]);
    }
}
