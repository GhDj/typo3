<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class DirectoryRight extends AbstractEntity
{
    public const ACCESS_READ = 'read';
    public const ACCESS_WRITE = 'write';
    public const ACCESS_DENY = 'deny';

    protected ?Role $role = null;

    protected ?Directory $directory = null;

    protected string $access = self::ACCESS_READ;

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(Role $role): void
    {
        $this->role = $role;
    }

    public function getDirectory(): ?Directory
    {
        return $this->directory;
    }

    public function setDirectory(Directory $directory): void
    {
        $this->directory = $directory;
    }

    public function getAccess(): string
    {
        return $this->access;
    }

    public function setAccess(string $access): void
    {
        $this->access = $access;
    }
}
