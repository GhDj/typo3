<?php

declare(strict_types=1);

namespace Thw\ThwSelfservice\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;

class User extends FrontendUser
{
    protected string $thwUid = '';

    protected ?OrganizationalUnit $thwOu = null;

    protected ?\DateTime $thwBirthdate = null;

    protected bool $thwActive = false;

    protected bool $thwPortalAccess = false;

    protected string $thwSsoSubject = '';

    protected ?\DateTime $thwLastImport = null;

    public function getThwUid(): string
    {
        return $this->thwUid;
    }

    public function getThwOu(): ?OrganizationalUnit
    {
        return $this->thwOu;
    }

    public function getThwBirthdate(): ?\DateTime
    {
        return $this->thwBirthdate;
    }

    public function isThwActive(): bool
    {
        return $this->thwActive;
    }

    public function isThwPortalAccess(): bool
    {
        return $this->thwPortalAccess;
    }

    public function setThwPortalAccess(bool $thwPortalAccess): void
    {
        $this->thwPortalAccess = $thwPortalAccess;
    }

    public function getThwSsoSubject(): string
    {
        return $this->thwSsoSubject;
    }

    public function setThwSsoSubject(string $thwSsoSubject): void
    {
        $this->thwSsoSubject = $thwSsoSubject;
    }

    public function getThwLastImport(): ?\DateTime
    {
        return $this->thwLastImport;
    }
}
