<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Role extends AbstractEntity
{
    protected string $name = '';

    protected string $roleGroup = '';

    protected string $description = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getRoleGroup(): string
    {
        return $this->roleGroup;
    }

    public function setRoleGroup(string $roleGroup): void
    {
        $this->roleGroup = $roleGroup;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }
}
