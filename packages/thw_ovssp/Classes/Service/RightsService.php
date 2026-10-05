<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Computes combined directory rights for a user across all assigned roles.
 *
 * Resolution rule: deny overrides everything, write beats read.
 */
class RightsService
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Get combined directory rights for a user.
     *
     * @return array<int, array{directory_uid: int, directory_name: string, access: string}>
     *     Keyed by directory UID, sorted by sort_key/name.
     */
    public function getCombinedRightsForUser(int $userUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_domain_model_directoryright');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select('dr.directory', 'dr.access', 'd.name', 'd.sort_key')
            ->from('tx_thwovssp_domain_model_directoryright', 'dr')
            ->join(
                'dr',
                'tx_thwovssp_user_role_mm',
                'mm',
                $qb->expr()->eq('dr.role', $qb->quoteIdentifier('mm.uid_foreign'))
            )
            ->join(
                'dr',
                'tx_thwovssp_domain_model_directory',
                'd',
                $qb->expr()->eq('dr.directory', $qb->quoteIdentifier('d.uid'))
            )
            ->where(
                $qb->expr()->eq('mm.uid_local', $qb->createNamedParameter($userUid, Connection::PARAM_INT)),
                $qb->expr()->eq('dr.deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('dr.hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('d.deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('d.hidden', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->resolveRights($rows);
    }

    /**
     * Get role UIDs assigned to a user.
     *
     * @return list<int>
     */
    public function getRoleUidsForUser(int $userUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_user_role_mm');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select('uid_foreign')
            ->from('tx_thwovssp_user_role_mm')
            ->where(
                $qb->expr()->eq('uid_local', $qb->createNamedParameter($userUid, Connection::PARAM_INT))
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        $uids = [];
        foreach ($rows as $row) {
            if (is_numeric($row['uid_foreign'])) {
                $uids[] = (int) $row['uid_foreign'];
            }
        }

        return $uids;
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(int $userUid, int $roleUid): void
    {
        $existing = $this->getRoleUidsForUser($userUid);
        if (in_array($roleUid, $existing, true)) {
            return;
        }

        $conn = $this->connectionPool->getConnectionForTable('tx_thwovssp_user_role_mm');
        $conn->insert('tx_thwovssp_user_role_mm', [
            'uid_local' => $userUid,
            'uid_foreign' => $roleUid,
            'sorting' => count($existing) + 1,
            'sorting_foreign' => 0,
        ]);

        $this->updateRoleCount($userUid);
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(int $userUid, int $roleUid): void
    {
        $conn = $this->connectionPool->getConnectionForTable('tx_thwovssp_user_role_mm');
        $conn->delete('tx_thwovssp_user_role_mm', [
            'uid_local' => $userUid,
            'uid_foreign' => $roleUid,
        ]);

        $this->updateRoleCount($userUid);
    }

    /**
     * Get all available roles.
     *
     * @return array<int, array{uid: int, name: string, role_group: string, description: string}>
     */
    public function getAllRoles(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_domain_model_role');

        return $qb
            ->select('uid', 'name', 'role_group', 'description')
            ->from('tx_thwovssp_domain_model_role')
            ->orderBy('name')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Resolve combined rights: deny > write > read.
     *
     * @param array<int, array<string, mixed>> $rows Raw DB rows with directory, access, name, sort_key
     * @return array<int, array{directory_uid: int, directory_name: string, access: string}>
     */
    private function resolveRights(array $rows): array
    {
        /** @var array<int, array{directory_uid: int, directory_name: string, access: string, sort_key: int|null}> $merged */
        $merged = [];

        foreach ($rows as $row) {
            if (!is_numeric($row['directory'])) {
                continue;
            }
            $dirUid = (int) $row['directory'];
            $access = is_string($row['access']) ? $row['access'] : 'read';
            $name = is_string($row['name']) ? $row['name'] : '';
            $sortKey = is_numeric($row['sort_key']) ? (int) $row['sort_key'] : null;

            if (!isset($merged[$dirUid])) {
                $merged[$dirUid] = [
                    'directory_uid' => $dirUid,
                    'directory_name' => $name,
                    'access' => $access,
                    'sort_key' => $sortKey,
                ];
                continue;
            }

            $current = $merged[$dirUid]['access'];
            $merged[$dirUid]['access'] = $this->higherAccess($current, $access);
        }

        usort($merged, static function (array $a, array $b): int {
            $sortA = $a['sort_key'] ?? PHP_INT_MAX;
            $sortB = $b['sort_key'] ?? PHP_INT_MAX;
            if ($sortA !== $sortB) {
                return $sortA <=> $sortB;
            }

            return strcasecmp($a['directory_name'], $b['directory_name']);
        });

        $result = [];
        foreach ($merged as $entry) {
            $result[$entry['directory_uid']] = [
                'directory_uid' => $entry['directory_uid'],
                'directory_name' => $entry['directory_name'],
                'access' => $entry['access'],
            ];
        }

        return $result;
    }

    /**
     * Return the dominant access level: deny > write > read.
     */
    private function higherAccess(string $a, string $b): string
    {
        if ($a === 'deny' || $b === 'deny') {
            return 'deny';
        }
        if ($a === 'write' || $b === 'write') {
            return 'write';
        }

        return 'read';
    }

    private function updateRoleCount(int $userUid): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_thwovssp_user_role_mm');
        $qb->getRestrictions()->removeAll();

        $countResult = $qb
            ->count('*')
            ->from('tx_thwovssp_user_role_mm')
            ->where(
                $qb->expr()->eq('uid_local', $qb->createNamedParameter($userUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        $count = is_numeric($countResult) ? (int) $countResult : 0;

        $conn = $this->connectionPool->getConnectionForTable('fe_users');
        $conn->update('fe_users', ['thw_roles' => $count], ['uid' => $userUid]);
    }
}
