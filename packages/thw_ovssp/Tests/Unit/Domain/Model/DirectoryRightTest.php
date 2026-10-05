<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Tests\Unit\Domain\Model;

use Init\Thw\Ovssp\Domain\Model\Directory;
use Init\Thw\Ovssp\Domain\Model\DirectoryRight;
use Init\Thw\Ovssp\Domain\Model\Role;
use PHPUnit\Framework\TestCase;

final class DirectoryRightTest extends TestCase
{
    public function testAccessDefaultsToRead(): void
    {
        $right = new DirectoryRight();
        self::assertSame('read', $right->getAccess());
    }

    public function testAccessCanBeSetToWrite(): void
    {
        $right = new DirectoryRight();
        $right->setAccess(DirectoryRight::ACCESS_WRITE);
        self::assertSame('write', $right->getAccess());
    }

    public function testAccessCanBeSetToDeny(): void
    {
        $right = new DirectoryRight();
        $right->setAccess(DirectoryRight::ACCESS_DENY);
        self::assertSame('deny', $right->getAccess());
    }

    public function testRoleDefaultsToNull(): void
    {
        $right = new DirectoryRight();
        self::assertNull($right->getRole());
    }

    public function testRoleCanBeSet(): void
    {
        $right = new DirectoryRight();
        $role = new Role();
        $role->setName('Test');
        $right->setRole($role);
        self::assertSame('Test', $right->getRole()?->getName());
    }

    public function testDirectoryDefaultsToNull(): void
    {
        $right = new DirectoryRight();
        self::assertNull($right->getDirectory());
    }

    public function testDirectoryCanBeSet(): void
    {
        $right = new DirectoryRight();
        $dir = new Directory();
        $dir->setName('Allgemein');
        $right->setDirectory($dir);
        self::assertSame('Allgemein', $right->getDirectory()?->getName());
    }

    public function testConstantsAreCorrect(): void
    {
        self::assertSame('read', DirectoryRight::ACCESS_READ);
        self::assertSame('write', DirectoryRight::ACCESS_WRITE);
        self::assertSame('deny', DirectoryRight::ACCESS_DENY);
    }
}
