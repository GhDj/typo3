<?php

declare(strict_types=1);

namespace Thw\ThwSso\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class OrgUnit extends AbstractEntity
{
    protected int $thwOeUid = 0;

    protected string $oeCode = '';

    protected string $name = '';

    protected string $mailAddress = '';

    protected string $regionalbereichCode = '';

    protected string $landesverbandCode = '';

    protected bool $active = true;

    public function getThwOeUid(): int
    {
        return $this->thwOeUid;
    }

    public function getOeCode(): string
    {
        return $this->oeCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getMailAddress(): string
    {
        return $this->mailAddress;
    }

    public function setMailAddress(string $mailAddress): void
    {
        $this->mailAddress = $mailAddress;
    }

    public function getRegionalbereichCode(): string
    {
        return $this->regionalbereichCode;
    }

    public function getLandesverbandCode(): string
    {
        return $this->landesverbandCode;
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
