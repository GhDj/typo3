<?php

declare(strict_types=1);

namespace Thw\ThwSelfservice\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class OrganizationalUnit extends AbstractEntity
{
    protected string $title = '';

    protected string $code = '';

    protected ?OrganizationalUnit $parent = null;

    protected bool $active = true;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getParent(): ?OrganizationalUnit
    {
        return $this->parent;
    }

    public function setParent(?OrganizationalUnit $parent): void
    {
        $this->parent = $parent;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
