<?php

declare(strict_types=1);

namespace Thw\ThwSso\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * User model mapped to fe_users.
 *
 * FrontendUser was removed in TYPO3 v12 (Deprecation #94654).
 * We extend AbstractEntity and declare only the fe_users core
 * properties we actually need, plus our THW extension fields.
 */
class User extends AbstractEntity
{
    // -- core fe_users fields we use --

    protected string $username = '';

    protected string $firstName = '';

    protected string $lastName = '';

    protected string $email = '';

    protected bool $disable = false;

    // -- THW extension fields (mandatory, from CSV) --

    protected int $thwUid = 0;

    protected ?OrgUnit $thwOrgunit = null;

    protected string $thwRealm = '';

    // -- THW extension fields (optional) --

    protected ?\DateTime $thwBirthdate = null;

    protected bool $thwPortalAccess = false;

    // -- THW system fields --

    protected ?\DateTime $thwLastImport = null;

    protected ?\DateTime $thwImportMissingSince = null;

    // -- core fe_users getters/setters --

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function isDisable(): bool
    {
        return $this->disable;
    }

    // -- THW field getters/setters --

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
