<?php

declare(strict_types=1);

namespace Thw\ThwSso\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;

class ImportCommand extends Command
{
    private const BATCH_SIZE = 500;

    private const USER_HEADERS = ['thw_uid', 'username', 'first_name', 'last_name', 'thw_oe_uid'];
    private const ORGUNIT_HEADERS = ['thw_oe_uid', 'oe_code', 'name', 'mail_address', 'Regionalbereich', 'Landesverband'];
    private const DIRECTORY_HEADERS = ['Verzeichnis', 'lesen', 'schreiben', 'SortKey', 'Beschreibung'];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import CSV data (users, orgunits, directories)')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Import type: users, orgunits, directories')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to CSV file')
            ->addOption('realm', 'r', InputOption::VALUE_OPTIONAL, 'AD realm: HA or EA (required for users)', '')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and report counts without writing')
            ->addOption('pid', 'p', InputOption::VALUE_OPTIONAL, 'Storage page ID for new records', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getOption('type');
        $file = $input->getOption('file');
        $realm = strtoupper((string)$input->getOption('realm'));
        $dryRun = (bool)$input->getOption('dry-run');
        $pid = (int)$input->getOption('pid');

        if (!in_array($type, ['users', 'orgunits', 'directories'], true)) {
            $io->error('--type must be one of: users, orgunits, directories');
            return Command::FAILURE;
        }

        if (!$file || !is_readable($file)) {
            $io->error('--file must point to a readable CSV file');
            return Command::FAILURE;
        }

        if ($type === 'users' && !in_array($realm, ['HA', 'EA'], true)) {
            $io->error('--realm HA or EA is required for user import');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('DRY RUN — no data will be written');
        }

        return match ($type) {
            'users' => $this->importUsers($file, $realm, $dryRun, $pid, $io),
            'orgunits' => $this->importOrgUnits($file, $dryRun, $pid, $io),
            'directories' => $this->importDirectories($file, $dryRun, $pid, $io),
        };
    }

    // ---------------------------------------------------------------
    // OrgUnit import
    // ---------------------------------------------------------------

    private function importOrgUnits(string $file, bool $dryRun, int $pid, SymfonyStyle $io): int
    {
        $handle = $this->openCsv($file);
        if ($handle === false) {
            $io->error('Could not open CSV file');
            return Command::FAILURE;
        }

        $headers = $this->readHeader($handle);
        if ($headers !== self::ORGUNIT_HEADERS) {
            $io->error(sprintf(
                "Header mismatch.\n  Expected: %s\n  Got:      %s",
                implode(';', self::ORGUNIT_HEADERS),
                implode(';', $headers)
            ));
            fclose($handle);
            return Command::FAILURE;
        }

        // First pass: validate
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
            $io->error('Validation failed — aborting entire run:');
            foreach (array_slice($errors, 0, 20) as $err) {
                $io->text("  $err");
            }
            if (count($errors) > 20) {
                $io->text('  … and ' . (count($errors) - 20) . ' more errors');
            }
            return Command::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;

        if (!$dryRun) {
            $connection = $this->connectionPool->getConnectionForTable('tx_thwsso_domain_model_orgunit');
            $now = time();

            // Load existing records keyed by thw_oe_uid
            $existing = [];
            $result = $connection->executeQuery(
                'SELECT uid, thw_oe_uid, oe_code, name, mail_address, regionalbereich_code, landesverband_code FROM tx_thwsso_domain_model_orgunit WHERE deleted = 0'
            );
            while ($row = $result->fetchAssociative()) {
                $existing[(int)$row['thw_oe_uid']] = $row;
            }

            foreach ($rows as $row) {
                $ex = $existing[$row['thw_oe_uid']] ?? null;
                if ($ex === null) {
                    $connection->insert('tx_thwsso_domain_model_orgunit', array_merge($row, [
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
                        $connection->update(
                            'tx_thwsso_domain_model_orgunit',
                            $changes,
                            ['uid' => (int)$ex['uid']]
                        );
                        $updated++;
                    } else {
                        $unchanged++;
                    }
                }
            }
        } else {
            // Dry-run: just count
            $connection = $this->connectionPool->getConnectionForTable('tx_thwsso_domain_model_orgunit');
            $existingUids = [];
            $result = $connection->executeQuery(
                'SELECT thw_oe_uid FROM tx_thwsso_domain_model_orgunit WHERE deleted = 0'
            );
            while ($row = $result->fetchAssociative()) {
                $existingUids[(int)$row['thw_oe_uid']] = true;
            }
            foreach ($rows as $row) {
                if (isset($existingUids[$row['thw_oe_uid']])) {
                    $updated++; // or unchanged — cannot tell in dry-run without full comparison
                } else {
                    $created++;
                }
            }
        }

        $io->success(sprintf(
            'OrgUnit import %s: %d total, %d created, %d updated, %d unchanged',
            $dryRun ? '(dry run)' : 'complete',
            count($rows),
            $created,
            $updated,
            $unchanged
        ));

        // TODO Phase 2: write audit/history entry for this import run

        return Command::SUCCESS;
    }

    // ---------------------------------------------------------------
    // Directory import
    // ---------------------------------------------------------------

    private function importDirectories(string $file, bool $dryRun, int $pid, SymfonyStyle $io): int
    {
        $handle = $this->openCsv($file);
        if ($handle === false) {
            $io->error('Could not open CSV file');
            return Command::FAILURE;
        }

        $headers = $this->readHeader($handle);
        if ($headers !== self::DIRECTORY_HEADERS) {
            $io->error(sprintf(
                "Header mismatch.\n  Expected: %s\n  Got:      %s",
                implode(';', self::DIRECTORY_HEADERS),
                implode(';', $headers)
            ));
            fclose($handle);
            return Command::FAILURE;
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
            $io->error('Validation failed — aborting entire run:');
            foreach ($errors as $err) {
                $io->text("  $err");
            }
            return Command::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;

        if (!$dryRun) {
            $connection = $this->connectionPool->getConnectionForTable('tx_thwsso_domain_model_directory');
            $now = time();

            $existing = [];
            $result = $connection->executeQuery(
                'SELECT uid, name, allows_read, allows_write, sort_key, description FROM tx_thwsso_domain_model_directory WHERE deleted = 0'
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
                    $connection->insert('tx_thwsso_domain_model_directory', $insertData);
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
                        $connection->update(
                            'tx_thwsso_domain_model_directory',
                            $changes,
                            ['uid' => (int)$ex['uid']]
                        );
                        $updated++;
                    } else {
                        $unchanged++;
                    }
                }
            }
        } else {
            $created = count($rows);
        }

        $io->success(sprintf(
            'Directory import %s: %d total, %d created, %d updated, %d unchanged',
            $dryRun ? '(dry run)' : 'complete',
            count($rows),
            $created,
            $updated,
            $unchanged
        ));

        // TODO Phase 2: write audit/history entry for this import run

        return Command::SUCCESS;
    }

    // ---------------------------------------------------------------
    // User import — performance-critical (91k rows)
    // ---------------------------------------------------------------

    private function importUsers(string $file, string $realm, bool $dryRun, int $pid, SymfonyStyle $io): int
    {
        $connection = $this->connectionPool->getConnectionForTable('fe_users');

        // Pre-load OrgUnit map: thw_oe_uid → TYPO3 uid
        $orgUnitMap = [];
        $ouConn = $this->connectionPool->getConnectionForTable('tx_thwsso_domain_model_orgunit');
        $result = $ouConn->executeQuery(
            'SELECT uid, thw_oe_uid FROM tx_thwsso_domain_model_orgunit WHERE deleted = 0'
        );
        while ($row = $result->fetchAssociative()) {
            $orgUnitMap[(int)$row['thw_oe_uid']] = (int)$row['uid'];
        }

        if (empty($orgUnitMap)) {
            $io->error('No OrgUnits found in the database. Import OrgUnits first.');
            return Command::FAILURE;
        }

        // First pass: validate entire file
        $handle = $this->openCsv($file);
        if ($handle === false) {
            $io->error('Could not open CSV file');
            return Command::FAILURE;
        }

        $headers = $this->readHeader($handle);
        if ($headers !== self::USER_HEADERS) {
            $io->error(sprintf(
                "Header mismatch.\n  Expected: %s\n  Got:      %s",
                implode(';', self::USER_HEADERS),
                implode(';', $headers)
            ));
            fclose($handle);
            return Command::FAILURE;
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
                $errors[] = '… stopping validation after 50 errors';
                break;
            }
        }
        fclose($handle);

        if (!empty($errors)) {
            $io->error('Validation failed — aborting entire run:');
            foreach ($errors as $err) {
                $io->text("  $err");
            }
            return Command::FAILURE;
        }

        $io->text("Validated $totalRows rows, all clean.");

        if ($dryRun) {
            // Dry-run: count inserts vs updates
            $existingUids = [];
            $result = $connection->executeQuery(
                'SELECT thw_uid FROM fe_users WHERE thw_realm = ? AND deleted = 0',
                [$realm]
            );
            while ($row = $result->fetchAssociative()) {
                $existingUids[(int)$row['thw_uid']] = true;
            }

            $handle = $this->openCsv($file);
            $this->readHeader($handle); // skip header
            $wouldCreate = 0;
            $wouldUpdate = 0;
            $fileUids = [];
            while (($fields = fgetcsv($handle, 0, ';')) !== false) {
                $thwUid = (int)$fields[0];
                $fileUids[$thwUid] = true;
                if (isset($existingUids[$thwUid])) {
                    $wouldUpdate++;
                } else {
                    $wouldCreate++;
                }
            }
            fclose($handle);

            $wouldDeactivate = 0;
            foreach ($existingUids as $uid => $_) {
                if (!isset($fileUids[$uid])) {
                    $wouldDeactivate++;
                }
            }

            $io->success(sprintf(
                'User import (dry run): %d total, %d would create, %d would update, %d would deactivate',
                $totalRows,
                $wouldCreate,
                $wouldUpdate,
                $wouldDeactivate
            ));
            return Command::SUCCESS;
        }

        // Second pass: upsert in batches
        $handle = $this->openCsv($file);
        $this->readHeader($handle); // skip header

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
                $pid,                           // pid
                $now,                           // tstamp
                $now,                           // crdate
                $thwUid,                        // thw_uid
                $username,                      // username
                '!',                            // password (invalid hash — login via OIDC only)
                $firstName,                     // first_name
                $lastName,                      // last_name
                $orgUnitMap[$thwOeUid],         // thw_orgunit
                $realm,                         // thw_realm
                $nowDatetime,                   // thw_last_import
            ];

            if (count($batch) >= self::BATCH_SIZE) {
                [$batchCreated, $batchUpdated] = $this->upsertUserBatch($connection, $batch);
                $created += $batchCreated;
                $updated += $batchUpdated;
                $batch = [];
            }
        }
        fclose($handle);

        // Flush remaining batch
        if (!empty($batch)) {
            [$batchCreated, $batchUpdated] = $this->upsertUserBatch($connection, $batch);
            $created += $batchCreated;
            $updated += $batchUpdated;
        }

        // Deactivation: users in this realm whose thw_last_import is older than this run
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

        $io->success(sprintf(
            'User import complete: %d total, %d created, %d updated, %d deactivated',
            $totalRows,
            $created,
            $updated,
            $deactivated
        ));

        // TODO Phase 2: write audit/history entry for this import run

        return Command::SUCCESS;
    }

    /**
     * Batch upsert for fe_users using INSERT … ON DUPLICATE KEY UPDATE.
     * Matches on the UNIQUE index on thw_uid.
     *
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

        // MySQL/MariaDB: affected_rows = 1 for insert, 2 for update, 0 for unchanged
        // With ON DUPLICATE KEY UPDATE, we can approximate:
        $batchSize = count($batch);
        $updates = $affected - $batchSize; // each update counts as 2, minus the 1 counted for insert
        if ($updates < 0) {
            $updates = 0;
        }
        $inserts = $batchSize - $updates;

        return [$inserts, $updates];
    }

    // ---------------------------------------------------------------
    // CSV helpers
    // ---------------------------------------------------------------

    /**
     * Opens a CSV file, strips UTF-8 BOM if present.
     * @return resource|false
     */
    private function openCsv(string $path)
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return false;
        }

        // Strip UTF-8 BOM (EF BB BF)
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        return $handle;
    }

    /**
     * Reads and returns the header row as an array of trimmed column names.
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
}
