<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Tests\Unit\Domain\Model;

use Init\Thw\Ovssp\Domain\Model\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testNameDefaultsToEmpty(): void
    {
        $role = new Role();
        self::assertSame('', $role->getName());
    }

    public function testNameCanBeSet(): void
    {
        $role = new Role();
        $role->setName('OV-User');
        self::assertSame('OV-User', $role->getName());
    }

    public function testRoleGroupDefaultsToEmpty(): void
    {
        $role = new Role();
        self::assertSame('', $role->getRoleGroup());
    }

    public function testRoleGroupCanBeSet(): void
    {
        $role = new Role();
        $role->setRoleGroup('standard');
        self::assertSame('standard', $role->getRoleGroup());
    }

    public function testDescriptionDefaultsToEmpty(): void
    {
        $role = new Role();
        self::assertSame('', $role->getDescription());
    }

    public function testDescriptionCanBeSet(): void
    {
        $role = new Role();
        $role->setDescription('Pflichtrolle für alle aktiven Helfer');
        self::assertSame('Pflichtrolle für alle aktiven Helfer', $role->getDescription());
    }
}
