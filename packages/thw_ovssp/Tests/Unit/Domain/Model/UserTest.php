<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Tests\Unit\Domain\Model;

use Init\Thw\Ovssp\Domain\Model\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $subject;

    protected function setUp(): void
    {
        $this->subject = new User();
    }

    public function testUsernameCanBeSetAndRead(): void
    {
        $this->subject->setUsername('beckw');
        self::assertSame('beckw', $this->subject->getUsername());
    }

    public function testGetNameCombinesFirstAndLast(): void
    {
        $this->subject->setFirstName('Walburga');
        $this->subject->setLastName('Beck');
        self::assertSame('Walburga Beck', $this->subject->getName());
    }

    public function testGetNameTrimsWhenPartsMissing(): void
    {
        $this->subject->setLastName('Beck');
        self::assertSame('Beck', $this->subject->getName());
    }

    public function testThwUidDefaultsToZero(): void
    {
        self::assertSame(0, $this->subject->getThwUid());
    }

    public function testThwRealmDefaultsToEmpty(): void
    {
        self::assertSame('', $this->subject->getThwRealm());
    }

    public function testPortalAccessDefaultsToFalse(): void
    {
        self::assertFalse($this->subject->isThwPortalAccess());
    }

    public function testPortalAccessCanBeToggled(): void
    {
        $this->subject->setThwPortalAccess(true);
        self::assertTrue($this->subject->isThwPortalAccess());
    }

    public function testBirthdateDefaultsToNull(): void
    {
        self::assertNull($this->subject->getThwBirthdate());
    }

    public function testBirthdateCanBeSet(): void
    {
        $date = new \DateTime('1995-07-30');
        $this->subject->setThwBirthdate($date);
        self::assertSame('1995-07-30', $this->subject->getThwBirthdate()->format('Y-m-d'));
    }

    public function testDisableDefaultsToFalse(): void
    {
        self::assertFalse($this->subject->isDisable());
    }

    public function testOrgunitDefaultsToNull(): void
    {
        self::assertNull($this->subject->getThwOrgunit());
    }

    public function testLastImportDefaultsToNull(): void
    {
        self::assertNull($this->subject->getThwLastImport());
    }

    public function testImportMissingSinceDefaultsToNull(): void
    {
        self::assertNull($this->subject->getThwImportMissingSince());
    }
}
