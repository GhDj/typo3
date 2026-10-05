<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class ImportService
{
    private const USER_HEADERS = ['thw_uid', 'username', 'first_name', 'last_name', 'oe_code'];
    private const ORGUNIT_HEADERS = ['thw_oe_uid', 'oe_code', 'name', 'mail_address', 'Regionalbereich', 'Landesverband'];
    private const DIRECTORY_HEADERS = ['Verzeichnis', 'lesen', 'schreiben', 'SortKey', 'Beschreibung'];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return array{success: bool, total: int, created: int, updated: int, unchanged: int, deactivated: int, skipped: int, errors: string[]}
     */
    public function import(string $filePath, string $type, string $realm, int $pid): array
    {
        return match ($type) {
            'users' => $this->importUsers($filePath, $realm, $pid),
            'orgunits' => $this->importOrgUnits($filePath, $pid),
            'directories' => $this->importDirectories($filePath, $pid),
            default => $this->errorResult(['Unknown import type: ' . $type]),
        };
    }

    /**
     * @return array{success: bool, total: int, created: int, updated: int, unchanged: int, deactivated: int, skipped: int, errors: list<string>}
     */
    private function importOrgUnits(string $filePath, int $pid): array
    {
        $handle = $this->openCsv($filePath);
        if ($handle === false) {
            return $this->errorResult(['Could not open CSV file']);
        }

        $headers = $this->readHeader($handle);
        if ($headers !== self::ORGUNIT_HEADERS) {
            fclose($handle);
            return $this->errorResult([sprintf(
                'Header mismatch. Expected: %s — Got: %s',
                implode(';', self::ORGUNIT_HEADERS),
                implode(';', $headers)
            )]);
        }

        $rows = [];
        $errors = [];
        $lineNum = 1;
        while (($fields = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $lineNum++;
            if (count($fields) < 6) {
                $errors[] = "Line $lineNum: expected 6 fields, got " . count($fields);
                continue;
            }
            $thwOeUid = trim((string)$fields[0]);
            $oeCode = trim((string)$fields[1]);
            if (!ctype_digit($thwOeUid)) {
                $errors[] = "Line $lineNum: thw_oe_uid '$thwOeUid' is not numeric";
                continue;
            }
            if (strlen($oeCode) !== 4) {
                $errors[] = "Line $lineNum: oe_code '$oeCode' is not exactly 4 characters";
                continue;
            }
            $rows[] = [
                'thw_oe_uid' => (int)$thwOeUid,
                'oe_code' => $oeCode,
                'name' => trim((string)$fields[2]),
                'mail_address' => trim((string)$fields[3]),
                'regionalbereich_code' => trim((string)$fields[4]),
                'landesverband_code' => trim((string)$fields[5]),
            ];
        }
        fclose($handle);

        if (!empty($errors)) {
            return $this->errorResult($errors);
        }

        $tableName = 'tx_thwovssp_domain_model_orgunit';
        $now = time();
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        // Load existing records via QueryBuilder
        $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
        $qb->getRestrictions()->removeAll();
        $existingRows = $qb
            ->select('uid', 'thw_oe_uid', 'oe_code', 'name', 'mail_address', 'regionalbereich_code', 'landesverband_code')
            ->from($tableName)
            ->where($qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAllAssociative();

        $existing = [];
        foreach ($existingRows as $row) {
            $existing[(int)$row['thw_oe_uid']] = $row;
        }

        foreach ($rows as $row) {
            $ex = $existing[$row['thw_oe_uid']] ?? null;
            if ($ex === null) {
                $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
                $qb->insert($tableName)
                    ->values(array_merge($row, [
                        'pid' => $pid,
                        'tstamp' => $now,
                        'crdate' => $now,
                        'active' => 1,
                    ]))
                    ->executeStatement();
                $created++;
            } else {
                $changes = [];
                foreach (['oe_code', 'name', 'mail_address', 'regionalbereich_code', 'landesverband_code'] as $col) {
                    if ($ex[$col] !== $row[$col]) {
                        $changes[$col] = $row[$col];
                    }
                }
                if (!empty($changes)) {
                    $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
                    $qb->update($tableName)
                        ->where($qb->expr()->eq('uid', $qb->createNamedParameter((int)$ex['uid'], Connection::PARAM_INT)));
                    foreach ($changes as $col => $val) {
                        $qb->set($col, $val);
                    }
                    $qb->set('tstamp', $now);
                    $qb->executeStatement();
                    $updated++;
                } else {
                    $unchanged++;
                }
            }
        }

        // TODO Phase 2: write audit/history entry for this import run

        return ['success' => true, 'total' => count($rows), 'created' => $created, 'updated' => $updated, 'unchanged' => $unchanged, 'deactivated' => 0, 'skipped' => 0, 'errors' => []];
    }

    /**
     * @return array{success: bool, total: int, created: int, updated: int, unchanged: int, deactivated: int, skipped: int, errors: list<string>}
     */
    private function importDirectories(string $filePath, int $pid): array
    {
        $handle = $this->openCsv($filePath);
        if ($handle === false) {
            return $this->errorResult(['Could not open CSV file']);
        }

        $headers = $this->readHeader($handle);
        if ($headers !== self::DIRECTORY_HEADERS) {
            fclose($handle);
            return $this->errorResult([sprintf(
                'Header mismatch. Expected: %s — Got: %s',
                implode(';', self::DIRECTORY_HEADERS),
                implode(';', $headers)
            )]);
        }

        $rows = [];
        $errors = [];
        $lineNum = 1;
        while (($fields = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $lineNum++;
            if (count($fields) < 5) {
                $errors[] = "Line $lineNum: expected 5 fields, got " . count($fields);
                continue;
            }
            $name = trim((string)$fields[0]);
            if ($name === '') {
                $errors[] = "Line $lineNum: directory name is empty";
                continue;
            }
            $sortKey = trim((string)$fields[3]);
            $rows[] = [
                'name' => $name,
                'allows_read' => (int)trim((string)$fields[1]),
                'allows_write' => (int)trim((string)$fields[2]),
                'sort_key' => ($sortKey !== '') ? (int)$sortKey : null,
                'description' => trim((string)$fields[4]),
            ];
        }
        fclose($handle);

        if (!empty($errors)) {
            return $this->errorResult($errors);
        }

        $tableName = 'tx_thwovssp_domain_model_directory';
        $now = time();
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
        $qb->getRestrictions()->removeAll();
        $existingRows = $qb
            ->select('uid', 'name', 'allows_read', 'allows_write', 'sort_key', 'description')
            ->from($tableName)
            ->where($qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAllAssociative();

        $existing = [];
        foreach ($existingRows as $row) {
            $existing[$row['name']] = $row;
        }

        foreach ($rows as $row) {
            $ex = $existing[$row['name']] ?? null;
            if ($ex === null) {
                $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
                $insertData = $row;
                $insertData['pid'] = $pid;
                $insertData['tstamp'] = $now;
                $insertData['crdate'] = $now;
                $qb->insert($tableName)->values($insertData)->executeStatement();
                $created++;
            } else {
                $changes = [];
                foreach (['allows_read', 'allows_write', 'sort_key', 'description'] as $col) {
                    if ($ex[$col] != $row[$col]) {
                        $changes[$col] = $row[$col];
                    }
                }
                if (!empty($changes)) {
                    $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
                    $qb->update($tableName)
                        ->where($qb->expr()->eq('uid', $qb->createNamedParameter((int)$ex['uid'], Connection::PARAM_INT)));
                    foreach ($changes as $col => $val) {
                        $qb->set($col, (string)($val ?? ''));
                    }
                    $qb->set('tstamp', (string)$now);
                    $qb->executeStatement();
                    $updated++;
                } else {
                    $unchanged++;
                }
            }
        }

        // TODO Phase 2: write audit/history entry for this import run

        return ['success' => true, 'total' => count($rows), 'created' => $created, 'updated' => $updated, 'unchanged' => $unchanged, 'deactivated' => 0, 'skipped' => 0, 'errors' => []];
    }

    /**
     * @return array{success: bool, total: int, created: int, updated: int, unchanged: int, deactivated: int, skipped: int, errors: list<string>}
     */
    private function importUsers(string $filePath, string $realm, int $pid): array
    {
        // Pre-load OrgUnit map: oe_code -> TYPO3 uid
        $orgUnitMap = [];
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_domain_model_orgunit');
        $qb->getRestrictions()->removeAll();
        $ouRows = $qb
            ->select('uid', 'oe_code')
            ->from('tx_thwovssp_domain_model_orgunit')
            ->where($qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($ouRows as $row) {
            $orgUnitMap[(string)$row['oe_code']] = (int)$row['uid'];
        }

        if (empty($orgUnitMap)) {
            return $this->errorResult(['No OrgUnits found in the database. Import OrgUnits first.']);
        }

        $handle = $this->openCsv($filePath);
        if ($handle === false) {
            return $this->errorResult(['Could not open CSV file']);
        }

        $headers = $this->readHeader($handle);
        if ($headers !== self::USER_HEADERS) {
            fclose($handle);
            return $this->errorResult([sprintf(
                'Header mismatch. Expected: %s — Got: %s',
                implode(';', self::USER_HEADERS),
                implode(';', $headers)
            )]);
        }

        // Pre-load ALL existing users by thw_uid (globally unique index)
        $existingUsers = [];
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->getRestrictions()->removeAll();
        $existingRows = $qb
            ->select('uid', 'thw_uid')
            ->from('fe_users')
            ->where(
                $qb->expr()->gt('thw_uid', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($existingRows as $row) {
            $existingUsers[(int)$row['thw_uid']] = (int)$row['uid'];
        }

        $now = time();
        $nowDatetime = date('Y-m-d H:i:s');
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $totalRows = 0;
        $skippedErrors = [];
        $lineNum = 1;

        while (($fields = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $lineNum++;
            $totalRows++;

            if (count($fields) < 5) {
                $skipped++;
                if (count($skippedErrors) < 50) {
                    $skippedErrors[] = "Line $lineNum: expected 5 fields, got " . count($fields);
                }
                continue;
            }

            $thwUid = trim((string)$fields[0]);
            $oeCode = trim((string)$fields[4]);

            if (!ctype_digit($thwUid)) {
                $skipped++;
                if (count($skippedErrors) < 50) {
                    $skippedErrors[] = "Line $lineNum: thw_uid '$thwUid' is not numeric — skipped";
                }
                continue;
            }

            if ($oeCode === '' || !isset($orgUnitMap[$oeCode])) {
                $skipped++;
                if (count($skippedErrors) < 50) {
                    $skippedErrors[] = "Line $lineNum: oe_code '$oeCode' invalid or not found — skipped";
                }
                continue;
            }

            $thwUidInt = (int)$thwUid;
            $username = trim((string)$fields[1]);
            $firstName = trim((string)$fields[2]);
            $lastName = trim((string)$fields[3]);
            $orgUnitUid = $orgUnitMap[$oeCode];

            if (isset($existingUsers[$thwUidInt])) {
                // Update existing user
                $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
                $qb->update('fe_users')
                    ->where($qb->expr()->eq('uid', $qb->createNamedParameter($existingUsers[$thwUidInt], Connection::PARAM_INT)))
                    ->set('username', $username)
                    ->set('first_name', $firstName)
                    ->set('last_name', $lastName)
                    ->set('thw_orgunit', (string)$orgUnitUid)
                    ->set('thw_last_import', $nowDatetime)
                    ->set('thw_import_missing_since', '', true, Connection::PARAM_NULL)
                    ->set('disable', '0')
                    ->set('tstamp', (string)$now)
                    ->executeStatement();
                $updated++;
            } else {
                // Insert new user
                $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
                $qb->insert('fe_users')
                    ->values([
                        'pid' => $pid,
                        'tstamp' => $now,
                        'crdate' => $now,
                        'thw_uid' => $thwUidInt,
                        'username' => $username,
                        'password' => '!',
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'thw_orgunit' => $orgUnitUid,
                        'thw_realm' => $realm,
                        'thw_last_import' => $nowDatetime,
                        'disable' => 0,
                    ])
                    ->executeStatement();
                $existingUsers[$thwUidInt] = 0; // mark as known for duplicate rows in same file
                $created++;
            }
        }
        fclose($handle);

        // Deactivation: users in this realm not touched by this import
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $deactivated = $qb->update('fe_users')
            ->set('disable', '1')
            ->set('thw_import_missing_since', $nowDatetime)
            ->set('tstamp', (string)$now)
            ->where(
                $qb->expr()->eq('thw_realm', $qb->createNamedParameter($realm)),
                $qb->expr()->or(
                    $qb->expr()->isNull('thw_last_import'),
                    $qb->expr()->lt('thw_last_import', $qb->createNamedParameter($nowDatetime))
                ),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->gt('thw_uid', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeStatement();

        if (count($skippedErrors) < $skipped) {
            $skippedErrors[] = '... and ' . ($skipped - count($skippedErrors)) . ' more skipped rows';
        }

        // TODO Phase 2: write audit/history entry for this import run

        return ['success' => true, 'total' => $totalRows, 'created' => $created, 'updated' => $updated, 'unchanged' => 0, 'deactivated' => $deactivated, 'skipped' => $skipped, 'errors' => $skippedErrors];
    }

    /**
     * @return resource|false
     */
    private function openCsv(string $path)
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return false;
        }
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        return $handle;
    }

    /**
     * @param resource $handle
     * @return list<string>
     */
    private function readHeader($handle): array
    {
        $header = fgetcsv($handle, 0, ';', '"', '');
        if ($header === false) {
            return [];
        }
        return array_map(static fn(?string $v): string => trim((string)$v), $header);
    }

    /**
     * @param list<string> $errors
     * @return array{success: bool, total: int, created: int, updated: int, unchanged: int, deactivated: int, skipped: int, errors: list<string>}
     */
    private function errorResult(array $errors): array
    {
        return ['success' => false, 'total' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'deactivated' => 0, 'skipped' => 0, 'errors' => $errors];
    }
}
