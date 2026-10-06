<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(
    name: 'ovssp:verify-login',
    description: 'Verify fe_user login credentials and diagnose issues',
)]
class VerifyLoginCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PasswordHashFactory $passwordHashFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('OV-SSP Login Diagnostics');

        // 1. Check fe_users exist
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->getRestrictions()->removeAll();
        $users = $qb
            ->select('uid', 'pid', 'username', 'password', 'disable', 'deleted', 'usergroup', 'thw_uid', 'thw_portal_access')
            ->from('fe_users')
            ->where($qb->expr()->gt('thw_uid', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAllAssociative();

        $io->section('fe_users with thw_uid > 0: ' . count($users));

        foreach ($users as $user) {
            $io->writeln('');
            $io->writeln('  uid: ' . $user['uid']);
            $io->writeln('  pid: ' . $user['pid']);
            $io->writeln('  username: ' . $user['username']);
            $io->writeln('  password hash: ' . substr((string) $user['password'], 0, 30) . '...');
            $io->writeln('  disabled: ' . ($user['disable'] ? 'YES' : 'no'));
            $io->writeln('  deleted: ' . ($user['deleted'] ? 'YES' : 'no'));
            $io->writeln('  usergroup: ' . $user['usergroup']);
            $io->writeln('  thw_portal_access: ' . $user['thw_portal_access']);

            // Verify password
            $hashInstance = $this->passwordHashFactory->getDefaultHashInstance('FE');
            $isValid = $hashInstance->checkPassword('Test1234!', (string) $user['password']);
            $io->writeln('  password "Test1234!" valid: ' . ($isValid ? 'YES' : 'NO'));

            // Check hash type
            $hash = (string) $user['password'];
            if (str_starts_with($hash, '$argon2')) {
                $io->writeln('  hash type: argon2');
            } elseif (str_starts_with($hash, '$2y$')) {
                $io->writeln('  hash type: bcrypt');
            } else {
                $io->writeln('  hash type: UNKNOWN (' . substr($hash, 0, 10) . ')');
            }
        }

        // 2. Check fe_groups
        $io->section('fe_groups');
        $qb2 = $this->connectionPool->getQueryBuilderForTable('fe_groups');
        $qb2->getRestrictions()->removeAll();
        $groups = $qb2->select('uid', 'pid', 'title', 'deleted')->from('fe_groups')->executeQuery()->fetchAllAssociative();
        foreach ($groups as $group) {
            $io->writeln('  uid=' . $group['uid'] . ' title="' . $group['title'] . '" pid=' . $group['pid'] . ' deleted=' . $group['deleted']);
        }

        // 3. Check config
        $io->section('Config');
        $checkPid = $GLOBALS['TYPO3_CONF_VARS']['FE']['checkFeUserPid'] ?? 'NOT SET';
        $io->writeln('  FE.checkFeUserPid: ' . var_export($checkPid, true));

        return Command::SUCCESS;
    }
}
