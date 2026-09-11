<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;

class ImportService
{
    private const BATCH_SIZE = 500;

    private const USER_HEADERS = ['thw_uid', 'username', 'first_name', 'last_name', 'thw_oe_uid'];
    private const ORGUNIT_HEADERS = ['thw_oe_uid', 'oe_code', 'name', 'mail_address', 'Regionalbereich', 'Landesverband'];
    private const DIRECTORY_HEADERS = ['Verzeichnis', 'lesen', 'schreiben', 'SortKey', 'Beschreibung'];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return array{success: bool, total: int, created: int, updated: int, unchanged: int, deactivated: int, errors: string[]}
     */
    public function import(string $filePath, string $type, string $realm, int $pid): array
    {
        return match ($type) {
            'users' => $this->importUsers($filePath, $realm, $pid),
            'orgunits' => $this->importOrgUnits($filePath, $pid),
            'directories' => $this->importDirectories($filePath, $pid),
            default => ['success' => false, 'total' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'deactivated' => 0, 'errors' => ['Unknown import type: ' . $type]],
        };
    }

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
        while (($fields = fgetcsv($handle, 0, ';')) !== false) {
            $lineNum++;
            if (count($fields) < 6) {
                $errors[] = "Line $lineNum: expected 6 fields, got " . count($fields);
                continue;
            }
            $thwOeUid = $fields[0];
            $oeCode = trim($fields[1]);
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
                'name' => trim($fields[2]),
                'mail_address' => trim($fields[3]),
                'regionalbereich_code' => trim($fields[4]),
                'landesverband_code' => trim($fields[5]),
            ];
        }
        fclose($handle);

        if (!empty($errors)) {
            return $this->errorResult($errors);
        }

        $connection = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_orgunit');
        $now = time();
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        $existing = [];
        $result = $connection->executeQuery(
            'SELECT uid, thw_oe_uid, oe_code, name, mail_address, regionalbereich_code, landesverband_code FROM tx_thwovssp_domain_model_orgunit WHERE deleted = 0'
        );
        while ($row = $result->fetchAssociative()) {
            $existing[(int)$row['thw_oe_uid']] = $row;
        }

        foreach ($rows as $row) {
            $ex = $existing[$row['thw_oe_uid']] ?? null;
            if ($ex === null) {
                $connection->insert('tx_thwovssp_domain_model_orgunit', array_merge($row, [
                    'pid' => $pid,
                    'tstamp' => $now,
                    'crdate' => $now,
                    'active' => 1,
                ]));
                $created++;
            } else {
                $changes = [];
                foreach (['oe_code', 'name', 'mail_address', 'regionalbereich_code', 'landesverband_code'] as $col) {
                    if ($ex[$col] !== $row[$col]) {
                        $changes[$col] = $row[$col];
                    }
                }
                if (!empty($changes)) {
                    $changes['tstamp'] = $now;
                    $connection->update('tx_thwovssp_domain_model_orgunit', $changes, ['uid' => (int)$ex['uid']]);
                    $updated++;
                } else {
                    $unchanged++;
                }
            }
        }

        // TODO Phase 2: write audit/history entry for this import run

        return ['success' => true, 'total' => count($rows), 'created' => $created, 'updated' => $updated, 'unchanged' => $unchanged, 'deactivated' => 0, 'errors' => []];
    }

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
        while (($fields = fgetcsv($handle, 0, ';')) !== false) {
            $lineNum++;
            if (count($fields) < 5) {
                $errors[] = "Line $lineNum: expected 5 fields, got " . count($fields);
                continue;
            }
            $name = trim($fields[0]);
            if ($name === '') {
                $errors[] = "Line $lineNum: directory name is empty";
                continue;
            }
            $sortKey = trim($fields[3]);
            $rows[] = [
                'name' => $name,
                'allows_read' => (int)trim($fields[1]),
                'allows_write' => (int)trim($fields[2]),
                'sort_key' => ($sortKey !== '') ? (int)$sortKey : null,
                'description' => trim($fields[4]),
            ];
        }
        fclose($handle);

        if (!empty($errors)) {
            return $this->errorResult($errors);
        }

        $connection = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_directory');
        $now = time();
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        $existing = [];
        $result = $connection->executeQuery(
            'SELECT uid, name, allows_read, allows_write, sort_key, description FROM tx_thwovssp_domain_model_directory WHERE deleted = 0'
        );
        while ($row = $result->fetchAssociative()) {
            $existing[$row['name']] = $row;
        }

        foreach ($rows as $row) {
            $ex = $existing[$row['name']] ?? null;
            if ($ex === null) {
                $insertData = $row;
                $insertData['pid'] = $pid;
                $insertData['tstamp'] = $now;
                $insertData['crdate'] = $now;
                $connection->insert('tx_thwovssp_domain_model_directory', $insertData);
                $created++;
            } else {
                $changes = [];
                foreach (['allows_read', 'allows_write', 'sort_key', 'description'] as $col) {
                    if ($ex[$col] != $row[$col]) {
                        $changes[$col] = $row[$col];
                    }
                }
                if (!empty($changes)) {
                    $changes['tstamp'] = $now;
                    $connection->update('tx_thwovssp_domain_model_directory', $changes, ['uid' => (int)$ex['uid']]);
                    $updated++;
                } else {
                    $unchanged++;
                }
            }
        }

        // TODO Phase 2: write audit/history entry for this import run

        return ['success' => true, 'total' => count($rows), 'created' => $created, 'updated' => $updated, 'unchanged' => $unchanged, 'deactivated' => 0, 'errors' => []];
    }

    private function importUsers(string $filePath, string $realm, int $pid): array
    {
        $connection = $this->connectionPool->getConnectionForTable('fe_users');

        // Pre-load OrgUnit map: thw_oe_uid -> TYPO3 uid
        $orgUnitMap = [];
        $ouConn = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_orgunit');
        $result = $ouConn->executeQuery(
            'SELECT uid, thw_oe_uid FROM tx_thwovssp_domain_model_orgunit WHERE deleted = 0'
        );
        while ($row = $result->fetchAssociative()) {
            $orgUnitMap[(int)$row['thw_oe_uid']] = (int)$row['uid'];
        }

        if (empty($orgUnitMap)) {
            return $this->errorResult(['No OrgUnits found in the database. Import OrgUnits first.']);
        }

        // First pass: validate entire file
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

        $errors = [];
        $totalRows = 0;
        $lineNum = 1;
        while (($fields = fgetcsv($handle, 0, ';')) !== false) {
            $lineNum++;
            $totalRows++;
            if (count($fields) < 5) {
                $errors[] = "Line $lineNum: expected 5 fields, got " . count($fields);
                continue;
            }
            $thwUid = $fields[0];
            $thwOeUid = $fields[4];
            if (!ctype_digit($thwUid)) {
                $errors[] = "Line $lineNum: thw_uid '$thwUid' is not numeric";
            }
            if (!ctype_digit($thwOeUid)) {
                $errors[] = "Line $lineNum: thw_oe_uid '$thwOeUid' is not numeric";
            } elseif (!isset($orgUnitMap[(int)$thwOeUid])) {
                $errors[] = "Line $lineNum: thw_oe_uid '$thwOeUid' not found in OrgUnit table";
            }
            if (count($errors) > 50) {
                $errors[] = '... stopping validation after 50 errors';
                break;
            }
        }
        fclose($handle);

        if (!empty($errors)) {
            return $this->errorResult($errors);
        }

        // Second pass: upsert in batches
        $handle = $this->openCsv($filePath);
        $this->readHeader($handle);

        $now = time();
        $nowDatetime = date('Y-m-d H:i:s');
        $created = 0;
        $updated = 0;
        $batch = [];

        while (($fields = fgetcsv($handle, 0, ';')) !== false) {
            $thwUid = (int)$fields[0];
            $username = trim($fields[1]);
            $firstName = trim($fields[2]);
            $lastName = trim($fields[3]);
            $thwOeUid = (int)$fields[4];

            $batch[] = [
                $pid, $now, $now, $thwUid, $username, '!',
                $firstName, $lastName, $orgUnitMap[$thwOeUid], $realm, $nowDatetime,
            ];

            if (count($batch) >= self::BATCH_SIZE) {
                [$batchCreated, $batchUpdated] = $this->upsertUserBatch($connection, $batch);
                $created += $batchCreated;
                $updated += $batchUpdated;
                $batch = [];
            }
        }
        fclose($handle);

        if (!empty($batch)) {
            [$batchCreated, $batchUpdated] = $this->upsertUserBatch($connection, $batch);
            $created += $batchCreated;
            $updated += $batchUpdated;
        }

        // Deactivation
        $deactivated = $connection->executeStatement(
            'UPDATE fe_users
             SET disable = 1,
                 thw_import_missing_since = COALESCE(thw_import_missing_since, ?),
                 tstamp = ?
             WHERE thw_realm = ?
               AND (thw_last_import IS NULL OR thw_last_import < ?)
               AND deleted = 0
               AND thw_uid > 0',
            [$nowDatetime, $now, $realm, $nowDatetime]
        );

        // TODO Phase 2: write audit/history entry for this import run

        return ['success' => true, 'total' => $totalRows, 'created' => $created, 'updated' => $updated, 'unchanged' => 0, 'deactivated' => $deactivated, 'errors' => []];
    }

    /**
     * @return array{int, int} [created, updated]
     */
    private function upsertUserBatch(\TYPO3\CMS\Core\Database\Connection $connection, array $batch): array
    {
        $columns = 'pid, tstamp, crdate, thw_uid, username, password, first_name, last_name, thw_orgunit, thw_realm, thw_last_import';
        $rowPlaceholder = '(' . implode(', ', array_fill(0, 11, '?')) . ')';
        $placeholders = implode(', ', array_fill(0, count($batch), $rowPlaceholder));

        $params = [];
        foreach ($batch as $row) {
            array_push($params, ...$row);
        }

        $sql = "INSERT INTO fe_users ($columns)
                VALUES $placeholders
                ON DUPLICATE KEY UPDATE
                    tstamp = VALUES(tstamp),
                    username = VALUES(username),
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    thw_orgunit = VALUES(thw_orgunit),
                    thw_last_import = VALUES(thw_last_import),
                    disable = 0,
                    thw_import_missing_since = NULL";

        $affected = $connection->executeStatement($sql, $params);

        $batchSize = count($batch);
        $updates = $affected - $batchSize;
        if ($updates < 0) {
            $updates = 0;
        }
        $inserts = $batchSize - $updates;

        return [$inserts, $updates];
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
     */
    private function readHeader($handle): array
    {
        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            return [];
        }
        return array_map('trim', $header);
    }

    private function errorResult(array $errors): array
    {
        return ['success' => false, 'total' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'deactivated' => 0, 'errors' => $errors];
    }
}
