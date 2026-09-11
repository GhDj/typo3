<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Directory extends AbstractEntity
{
    protected string $name = '';

    protected bool $allowsRead = false;

    protected bool $allowsWrite = false;

    protected ?int $sortKey = null;

    protected string $description = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAllowsRead(): bool
    {
        return $this->allowsRead;
    }

    public function setAllowsRead(bool $allowsRead): void
    {
        $this->allowsRead = $allowsRead;
    }

    public function getAllowsWrite(): bool
    {
        return $this->allowsWrite;
    }

    public function setAllowsWrite(bool $allowsWrite): void
    {
        $this->allowsWrite = $allowsWrite;
    }

    public function getSortKey(): ?int
    {
        return $this->sortKey;
    }

    public function setSortKey(?int $sortKey): void
    {
        $this->sortKey = $sortKey;
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
