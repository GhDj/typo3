<?php

declare(strict_types=1);

namespace Thw\ThwOvssp\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;

class User extends FrontendUser
{
    protected int $thwUid = 0;

    protected ?OrgUnit $thwOrgunit = null;

    protected string $thwRealm = '';

    protected ?\DateTime $thwBirthdate = null;

    protected bool $thwPortalAccess = false;

    protected ?\DateTime $thwLastImport = null;

    protected ?\DateTime $thwImportMissingSince = null;

    public function getThwUid(): int
    {
        return $this->thwUid;
    }

    public function getThwOrgunit(): ?OrgUnit
    {
        return $this->thwOrgunit;
    }

    public function getThwRealm(): string
    {
        return $this->thwRealm;
    }

    public function getThwBirthdate(): ?\DateTime
    {
        return $this->thwBirthdate;
    }

    public function setThwBirthdate(?\DateTime $thwBirthdate): void
    {
        $this->thwBirthdate = $thwBirthdate;
    }

    public function isThwPortalAccess(): bool
    {
        return $this->thwPortalAccess;
    }

    public function setThwPortalAccess(bool $thwPortalAccess): void
    {
        $this->thwPortalAccess = $thwPortalAccess;
    }

    public function getThwLastImport(): ?\DateTime
    {
        return $this->thwLastImport;
    }

    public function getThwImportMissingSince(): ?\DateTime
    {
        return $this->thwImportMissingSince;
    }
}
