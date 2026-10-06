<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(
    name: 'ovssp:reset-demo',
    description: 'Delete all demo data created by ovssp:setup-demo so it can be re-run cleanly',
)]
class ResetDemoCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$io->confirm('This will DELETE all demo pages, content, users, roles, rights, and site config. Continue?', false)) {
            $io->comment('Aborted.');

            return Command::SUCCESS;
        }

        $io->title('OV-SSP Demo Reset');

        $this->dropTable('tx_thwovssp_user_role_mm', $io);
        $this->dropTable('tx_thwovssp_domain_model_directoryright', $io);
        $this->dropTable('tx_thwovssp_domain_model_role', $io);
        $this->dropTable('tx_thwovssp_domain_model_directory', $io);
        $this->dropTable('tx_thwovssp_domain_model_orgunit', $io);

        $this->deleteFeUsers($io);
        $this->deleteFeGroups($io);
        $this->deleteContentAndPages($io);
        $this->deleteSiteConfig($io);

        $io->success([
            'Demo data reset complete.',
            'Run "bin/typo3 extension:setup" to recreate tables.',
            'Run "bin/typo3 ovssp:setup-demo" to recreate demo data.',
            'Run "bin/typo3 cache:flush" to clear caches.',
        ]);

        return Command::SUCCESS;
    }

    private function dropTable(string $table, SymfonyStyle $io): void
    {
        $conn = $this->connectionPool->getConnectionForTable($table);
        $conn->executeStatement('DROP TABLE IF EXISTS ' . $conn->quoteIdentifier($table));
        $io->writeln('  Dropped ' . $table);
    }

    private function deleteFeUsers(SymfonyStyle $io): void
    {
        $conn = $this->connectionPool->getConnectionForTable('fe_users');
        $affected = $conn->delete('fe_users', []);
        $io->writeln('  Deleted ' . $affected . ' fe_users');
    }

    private function deleteFeGroups(SymfonyStyle $io): void
    {
        $conn = $this->connectionPool->getConnectionForTable('fe_groups');
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_groups');
        $affected = $qb
            ->delete('fe_groups')
            ->where(
                $qb->expr()->eq('title', $qb->createNamedParameter('Portal Users'))
            )
            ->executeStatement();
        $io->writeln('  Deleted ' . $affected . ' fe_groups');
    }

    private function deleteContentAndPages(SymfonyStyle $io): void
    {
        $rootUid = $this->getPageUidByTitle('THW OV-SSP');
        if ($rootUid === 0) {
            $io->comment('  No demo pages found, skipping.');

            return;
        }

        $childPids = $this->getChildPageUids($rootUid);
        $allPids = array_merge([$rootUid], $childPids);

        // Delete content on all pages
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $qb->getRestrictions()->removeAll();
        $affected = $qb
            ->delete('tt_content')
            ->where(
                $qb->expr()->in('pid', $qb->createNamedParameter($allPids, Connection::PARAM_INT_ARRAY))
            )
            ->executeStatement();
        $io->writeln('  Deleted ' . $affected . ' tt_content records');

        // Delete child pages then root
        if ($childPids !== []) {
            $qb2 = $this->connectionPool->getQueryBuilderForTable('pages');
            $qb2->getRestrictions()->removeAll();
            $qb2->delete('pages')
                ->where(
                    $qb2->expr()->in('uid', $qb2->createNamedParameter($childPids, Connection::PARAM_INT_ARRAY))
                )
                ->executeStatement();
        }

        $conn = $this->connectionPool->getConnectionForTable('pages');
        $conn->delete('pages', ['uid' => $rootUid]);

        $io->writeln('  Deleted ' . count($allPids) . ' pages');
    }

    private function deleteSiteConfig(SymfonyStyle $io): void
    {
        $siteDir = getenv('TYPO3_PATH_ROOT') ?: getcwd();
        if (!is_string($siteDir)) {
            $siteDir = '/var/www/html';
        }

        $deleted = false;
        foreach (['ov-ssp', 'ovssp'] as $dirName) {
            $configFile = $siteDir . '/config/sites/' . $dirName . '/config.yaml';
            if (file_exists($configFile)) {
                unlink($configFile);
                $configDir = dirname($configFile);
                if (is_dir($configDir) && count((array) scandir($configDir)) === 2) {
                    rmdir($configDir);
                }
                $io->writeln('  Deleted site configuration (' . $dirName . ')');
                $deleted = true;
            }
        }

        if (!$deleted) {
            $io->comment('  No site configuration found, skipping.');
        }
    }

    private function getPageUidByTitle(string $title): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $qb->getRestrictions()->removeAll();

        $row = $qb
            ->select('uid')
            ->from('pages')
            ->where(
                $qb->expr()->eq('title', $qb->createNamedParameter($title))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) && is_numeric($row['uid']) ? (int) $row['uid'] : 0;
    }

    /**
     * @return list<int>
     */
    private function getChildPageUids(int $parentUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select('uid')
            ->from('pages')
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($parentUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $uids = [];
        foreach ($rows as $row) {
            if (is_numeric($row['uid'])) {
                $uids[] = (int) $row['uid'];
            }
        }

        return $uids;
    }
}
