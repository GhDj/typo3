<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(
    name: 'ovssp:setup-demo',
    description: 'Create demo page tree, test data, roles, and portal plugin for development/testing',
)]
class SetupDemoCommand extends Command
{
    private const DEFAULT_PASSWORD = '$2y$12$LMVq3oMfCMkJE1GRXP2.bOmRADm0FeLYHOVhN2Q8E52GlreHu.LIi'; // Test1234!

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'base-url',
            null,
            InputOption::VALUE_OPTIONAL,
            'Site base URL',
            '/'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $baseUrl = $input->getOption('base-url');
        if (!is_string($baseUrl)) {
            $baseUrl = '/';
        }

        $io->title('OV-SSP Demo Setup');

        $rootPageUid = $this->createPageTree($io);
        $storagePid = $this->getPageUidByTitle('Datenspeicher');
        $portalPid = $this->getPageUidByTitle('Portal');
        $loginPid = $this->getPageUidByTitle('Login');
        $homePid = $this->getPageUidByTitle('Home');

        if ($storagePid === 0 || $portalPid === 0 || $loginPid === 0) {
            $io->error('Failed to create page tree.');

            return Command::FAILURE;
        }

        $this->createSiteConfiguration($rootPageUid, $baseUrl, $io);
        $this->createTypoScriptTemplate($rootPageUid, $storagePid, $io);
        $this->createFeGroup($storagePid, $io);
        $this->createOrgUnits($storagePid, $io);
        $this->createDirectories($storagePid, $io);
        $this->createUsers($storagePid, $io);
        $this->createRoles($storagePid, $io);
        $this->createDirectoryRights($storagePid, $io);
        $this->createLoginPlugin($loginPid, $portalPid, $io);
        $this->createPortalPlugin($portalPid, $io);
        $this->createHomePage($homePid, $loginPid, $io);

        $io->success([
            'Demo setup complete!',
            '',
            'Login: /login',
            'User: admin.test / Test1234!',
            'Portal: /portal (redirected after login)',
        ]);

        $io->note('Run "bin/typo3 cache:flush" to clear caches.');

        return Command::SUCCESS;
    }

    private function createPageTree(SymfonyStyle $io): int
    {
        $conn = $this->connectionPool->getConnectionForTable('pages');

        $rootUid = $this->getPageUidByTitle('THW OV-SSP');
        if ($rootUid > 0) {
            $io->comment('Page tree already exists, skipping.');

            return $rootUid;
        }

        $io->section('Creating page tree');

        $now = time();
        $rootUid = $this->insertPage($conn, 0, 'THW OV-SSP', 'standard', 1, $now, true);

        $this->insertPage($conn, $rootUid, 'Home', 'standard', 1, $now);
        $this->insertPage($conn, $rootUid, 'Portal', 'standard', 2, $now);
        $this->insertPage($conn, $rootUid, 'Login', 'standard', 3, $now);
        $this->insertPage($conn, $rootUid, 'Datenspeicher', 'sysfolder', 4, $now);

        $io->writeln('  Root page: uid=' . $rootUid);
        $io->writeln('  + Home, Portal, Login, Datenspeicher');

        return $rootUid;
    }

    private function insertPage(Connection $conn, int $pid, string $title, string $doktype, int $sorting, int $now, bool $isRoot = false): int
    {
        $doktypeMap = ['standard' => 1, 'sysfolder' => 254];
        $data = [
            'pid' => $pid,
            'title' => $title,
            'slug' => '/' . strtolower(str_replace(' ', '-', $title)),
            'doktype' => $doktypeMap[$doktype] ?? 1,
            'sorting' => $sorting * 256,
            'crdate' => $now,
            'tstamp' => $now,
            'is_siteroot' => $isRoot ? 1 : 0,
            'hidden' => 0,
            'deleted' => 0,
        ];
        $conn->insert('pages', $data);

        return (int) $conn->lastInsertId();
    }

    private function getPageUidByTitle(string $title): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');

        $row = $qb
            ->select('uid')
            ->from('pages')
            ->where(
                $qb->expr()->eq('title', $qb->createNamedParameter($title)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) && is_numeric($row['uid']) ? (int) $row['uid'] : 0;
    }

    private function createSiteConfiguration(int $rootPageUid, string $baseUrl, SymfonyStyle $io): void
    {
        $siteDir = getenv('TYPO3_PATH_ROOT') ?: getcwd();
        if (!is_string($siteDir)) {
            $siteDir = '/var/www/html';
        }
        $configDir = $siteDir . '/config/sites/ovssp';

        if (file_exists($configDir . '/config.yaml')) {
            $io->comment('Site configuration already exists, skipping.');

            return;
        }

        $io->section('Creating site configuration');

        if (!is_dir($configDir)) {
            mkdir($configDir, 0775, true);
        }

        $yaml = <<<YAML
rootPageId: {$rootPageUid}
base: '{$baseUrl}'
languages:
  -
    title: Deutsch
    enabled: true
    languageId: 0
    base: /
    locale: de_DE.UTF-8
    navigationTitle: DE
    flag: de
errorHandling: []
YAML;

        file_put_contents($configDir . '/config.yaml', $yaml);
        $io->writeln('  Created config/sites/ovssp/config.yaml');
    }

    private function createTypoScriptTemplate(int $rootPageUid, int $storagePid, SymfonyStyle $io): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_template');
        $exists = $qb
            ->count('uid')
            ->from('sys_template')
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($rootPageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        if (is_numeric($exists) && (int) $exists > 0) {
            $io->comment('TypoScript template already exists, skipping.');

            return;
        }

        $io->section('Creating TypoScript template');

        $now = time();
        $conn = $this->connectionPool->getConnectionForTable('sys_template');
        $conn->insert('sys_template', [
            'pid' => $rootPageUid,
            'title' => 'THW OV-SSP Main',
            'root' => 1,
            'clear' => 3,
            'include_static_file' => 'EXT:fluid_styled_content/Configuration/TypoScript/,EXT:felogin/Configuration/TypoScript/,EXT:thw_ovssp/Configuration/TypoScript/',
            'crdate' => $now,
            'tstamp' => $now,
        ]);

        $io->writeln('  Created sys_template record');
    }

    private function createFeGroup(int $storagePid, SymfonyStyle $io): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_groups');
        $exists = $qb
            ->count('uid')
            ->from('fe_groups')
            ->where(
                $qb->expr()->eq('title', $qb->createNamedParameter('Portal Users')),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        if (is_numeric($exists) && (int) $exists > 0) {
            $io->comment('fe_group "Portal Users" already exists, skipping.');

            return;
        }

        $io->section('Creating fe_group');

        $now = time();
        $conn = $this->connectionPool->getConnectionForTable('fe_groups');
        $conn->insert('fe_groups', [
            'pid' => $storagePid,
            'title' => 'Portal Users',
            'crdate' => $now,
            'tstamp' => $now,
        ]);

        $io->writeln('  Created group "Portal Users"');
    }

    private function createOrgUnits(int $storagePid, SymfonyStyle $io): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_domain_model_orgunit');
        $count = $qb->count('uid')->from('tx_thwovssp_domain_model_orgunit')->executeQuery()->fetchOne();
        if (is_numeric($count) && (int) $count > 0) {
            $io->comment('OrgUnits already exist (' . $count . '), skipping.');

            return;
        }

        $io->section('Creating demo OrgUnits');

        $now = time();
        $conn = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_orgunit');

        $units = [
            ['thw_oe_uid' => 100001, 'oe_code' => 'OAAC', 'name' => 'OV Musterstadt', 'mail_address' => 'ov-musterstadt@thw.de', 'regionalbereich_code' => 'RBAA', 'landesverband_code' => 'LVAA'],
            ['thw_oe_uid' => 100002, 'oe_code' => 'OAAD', 'name' => 'OV Beispielburg', 'mail_address' => 'ov-beispielburg@thw.de', 'regionalbereich_code' => 'RBAA', 'landesverband_code' => 'LVAA'],
        ];

        foreach ($units as $unit) {
            $conn->insert('tx_thwovssp_domain_model_orgunit', array_merge($unit, [
                'pid' => $storagePid,
                'crdate' => $now,
                'tstamp' => $now,
                'active' => 1,
            ]));
        }

        $io->writeln('  Created 2 OrgUnits (OAAC, OAAD)');
    }

    private function createDirectories(int $storagePid, SymfonyStyle $io): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_domain_model_directory');
        $count = $qb->count('uid')->from('tx_thwovssp_domain_model_directory')->executeQuery()->fetchOne();
        if (is_numeric($count) && (int) $count > 0) {
            $io->comment('Directories already exist (' . $count . '), skipping.');

            return;
        }

        $io->section('Creating demo Directories');

        $now = time();
        $conn = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_directory');

        $dirs = [
            ['name' => 'Allgemein', 'allows_read' => 1, 'allows_write' => 1, 'sort_key' => 1, 'description' => 'Allgemeine Dokumente'],
            ['name' => 'Verwaltung', 'allows_read' => 1, 'allows_write' => 1, 'sort_key' => 2, 'description' => 'Verwaltungsdokumente'],
            ['name' => 'Ausbildung', 'allows_read' => 1, 'allows_write' => 0, 'sort_key' => 3, 'description' => 'Ausbildungsunterlagen'],
        ];

        foreach ($dirs as $dir) {
            $conn->insert('tx_thwovssp_domain_model_directory', array_merge($dir, [
                'pid' => $storagePid,
                'crdate' => $now,
                'tstamp' => $now,
            ]));
        }

        $io->writeln('  Created 3 Directories');
    }

    private function createUsers(int $storagePid, SymfonyStyle $io): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $count = $qb
            ->count('uid')
            ->from('fe_users')
            ->where($qb->expr()->gt('thw_uid', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        if (is_numeric($count) && (int) $count > 0) {
            $io->comment('THW users already exist (' . $count . '), skipping.');

            return;
        }

        $io->section('Creating demo Users');

        $oaacUid = $this->getOrgUnitUid('OAAC');
        $oaadUid = $this->getOrgUnitUid('OAAD');
        $groupUid = $this->getFeGroupUid('Portal Users');

        $now = time();
        $conn = $this->connectionPool->getConnectionForTable('fe_users');

        $users = [
            ['thw_uid' => 900000001, 'username' => 'admin.test', 'first_name' => 'Max', 'last_name' => 'Mustermann', 'thw_orgunit' => $oaacUid, 'thw_portal_access' => 2],
            ['thw_uid' => 900000002, 'username' => 'helfer.eins', 'first_name' => 'Anna', 'last_name' => 'Schmidt', 'thw_orgunit' => $oaacUid, 'thw_portal_access' => 1],
            ['thw_uid' => 900000003, 'username' => 'helfer.zwei', 'first_name' => 'Peter', 'last_name' => 'Mueller', 'thw_orgunit' => $oaacUid, 'thw_portal_access' => 0],
            ['thw_uid' => 900000004, 'username' => 'helfer.drei', 'first_name' => 'Lisa', 'last_name' => 'Weber', 'thw_orgunit' => $oaadUid, 'thw_portal_access' => 1],
        ];

        foreach ($users as $user) {
            $conn->insert('fe_users', array_merge($user, [
                'pid' => $storagePid,
                'password' => self::DEFAULT_PASSWORD,
                'usergroup' => (string) $groupUid,
                'thw_realm' => 'EA',
                'thw_last_import' => date('Y-m-d H:i:s'),
                'crdate' => $now,
                'tstamp' => $now,
                'disable' => 0,
                'deleted' => 0,
            ]));
        }

        $io->writeln('  Created 4 users (admin.test is OV-Admin for OAAC)');
        $io->writeln('  Login: admin.test / Test1234!');
    }

    private function createRoles(int $storagePid, SymfonyStyle $io): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_domain_model_role');
        $count = $qb->count('uid')->from('tx_thwovssp_domain_model_role')->executeQuery()->fetchOne();
        if (is_numeric($count) && (int) $count > 0) {
            $io->comment('Roles already exist (' . $count . '), skipping.');

            return;
        }

        $io->section('Creating demo Roles');

        $now = time();
        $conn = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_role');

        $roles = [
            ['name' => 'OV-User', 'role_group' => 'standard', 'description' => 'Pflichtrolle fuer alle aktiven Helfer'],
            ['name' => 'OV-Leitung', 'role_group' => 'admin', 'description' => 'Erweiterte Rechte fuer OV-Leitung'],
        ];

        foreach ($roles as $role) {
            $conn->insert('tx_thwovssp_domain_model_role', array_merge($role, [
                'pid' => $storagePid,
                'crdate' => $now,
                'tstamp' => $now,
            ]));
        }

        $io->writeln('  Created 2 Roles (OV-User, OV-Leitung)');
    }

    private function createDirectoryRights(int $storagePid, SymfonyStyle $io): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_domain_model_directoryright');
        $count = $qb->count('uid')->from('tx_thwovssp_domain_model_directoryright')->executeQuery()->fetchOne();
        if (is_numeric($count) && (int) $count > 0) {
            $io->comment('DirectoryRights already exist (' . $count . '), skipping.');

            return;
        }

        $io->section('Creating demo DirectoryRights');

        $ovUserUid = $this->getRoleUid('OV-User');
        $ovLeitungUid = $this->getRoleUid('OV-Leitung');
        $allgemeinUid = $this->getDirectoryUid('Allgemein');
        $verwaltungUid = $this->getDirectoryUid('Verwaltung');
        $ausbildungUid = $this->getDirectoryUid('Ausbildung');

        $now = time();
        $conn = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_directoryright');

        $rights = [
            ['role' => $ovUserUid, 'directory' => $allgemeinUid, 'access' => 'read'],
            ['role' => $ovUserUid, 'directory' => $ausbildungUid, 'access' => 'read'],
            ['role' => $ovLeitungUid, 'directory' => $allgemeinUid, 'access' => 'write'],
            ['role' => $ovLeitungUid, 'directory' => $verwaltungUid, 'access' => 'write'],
            ['role' => $ovLeitungUid, 'directory' => $ausbildungUid, 'access' => 'read'],
        ];

        foreach ($rights as $right) {
            $conn->insert('tx_thwovssp_domain_model_directoryright', array_merge($right, [
                'pid' => $storagePid,
                'crdate' => $now,
                'tstamp' => $now,
            ]));
        }

        $io->writeln('  Created 5 DirectoryRights');
    }

    private function createLoginPlugin(int $loginPid, int $portalPid, SymfonyStyle $io): void
    {
        if ($this->contentExists($loginPid)) {
            $io->comment('Login page content already exists, skipping.');

            return;
        }

        $io->section('Creating login plugin');

        $now = time();
        $conn = $this->connectionPool->getConnectionForTable('tt_content');
        $conn->insert('tt_content', [
            'pid' => $loginPid,
            'CType' => 'felogin_login',
            'header' => 'Login',
            'crdate' => $now,
            'tstamp' => $now,
            'sorting' => 256,
            'pi_flexform' => $this->buildFeloginFlexform($portalPid),
        ]);

        $io->writeln('  Created felogin plugin on Login page (redirects to Portal)');
    }

    private function createPortalPlugin(int $portalPid, SymfonyStyle $io): void
    {
        if ($this->contentExists($portalPid)) {
            $io->comment('Portal page content already exists, skipping.');

            return;
        }

        $io->section('Creating portal plugin');

        $now = time();
        $conn = $this->connectionPool->getConnectionForTable('tt_content');
        $conn->insert('tt_content', [
            'pid' => $portalPid,
            'CType' => 'list',
            'list_type' => 'thwovssp_portal',
            'header' => 'OV-SSP Portal',
            'header_layout' => '100',
            'crdate' => $now,
            'tstamp' => $now,
            'sorting' => 256,
        ]);

        $io->writeln('  Created portal plugin on Portal page');
    }

    private function createHomePage(int $homePid, int $loginPid, SymfonyStyle $io): void
    {
        if ($this->contentExists($homePid)) {
            $io->comment('Home page content already exists, skipping.');

            return;
        }

        $io->section('Creating home page content');

        $now = time();
        $conn = $this->connectionPool->getConnectionForTable('tt_content');
        $conn->insert('tt_content', [
            'pid' => $homePid,
            'CType' => 'text',
            'header' => 'THW OV Self-Service-Portal',
            'bodytext' => '<p>Willkommen im OV-SSP. <a href="/login">Zum Login</a></p>',
            'crdate' => $now,
            'tstamp' => $now,
            'sorting' => 256,
        ]);

        $io->writeln('  Created home page content');
    }

    private function contentExists(int $pid): bool
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $count = $qb
            ->count('uid')
            ->from('tt_content')
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($pid, Connection::PARAM_INT)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        return is_numeric($count) && (int) $count > 0;
    }

    private function getOrgUnitUid(string $oeCode): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_domain_model_orgunit');
        $row = $qb
            ->select('uid')
            ->from('tx_thwovssp_domain_model_orgunit')
            ->where($qb->expr()->eq('oe_code', $qb->createNamedParameter($oeCode)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) && is_numeric($row['uid']) ? (int) $row['uid'] : 0;
    }

    private function getFeGroupUid(string $title): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_groups');
        $row = $qb
            ->select('uid')
            ->from('fe_groups')
            ->where($qb->expr()->eq('title', $qb->createNamedParameter($title)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) && is_numeric($row['uid']) ? (int) $row['uid'] : 0;
    }

    private function getRoleUid(string $name): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_domain_model_role');
        $row = $qb
            ->select('uid')
            ->from('tx_thwovssp_domain_model_role')
            ->where($qb->expr()->eq('name', $qb->createNamedParameter($name)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) && is_numeric($row['uid']) ? (int) $row['uid'] : 0;
    }

    private function getDirectoryUid(string $name): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_domain_model_directory');
        $row = $qb
            ->select('uid')
            ->from('tx_thwovssp_domain_model_directory')
            ->where($qb->expr()->eq('name', $qb->createNamedParameter($name)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) && is_numeric($row['uid']) ? (int) $row['uid'] : 0;
    }

    private function buildFeloginFlexform(int $redirectPid): string
    {
        return <<<XML
<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<T3FlexForms>
    <data>
        <sheet index="sDEF">
            <language index="lDEF">
                <field index="settings.redirectMode">
                    <value index="vDEF">login</value>
                </field>
                <field index="settings.redirectFirstMethod">
                    <value index="vDEF">1</value>
                </field>
                <field index="settings.redirectPageLogin">
                    <value index="vDEF">{$redirectPid}</value>
                </field>
            </language>
        </sheet>
    </data>
</T3FlexForms>
XML;
    }
}
