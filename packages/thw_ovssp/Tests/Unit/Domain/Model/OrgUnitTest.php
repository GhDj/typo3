<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Tests\Unit\Domain\Model;

use Init\Thw\Ovssp\Domain\Model\OrgUnit;
use PHPUnit\Framework\TestCase;

class OrgUnitTest extends TestCase
{
    private OrgUnit $subject;

    protected function setUp(): void
    {
        $this->subject = new OrgUnit();
    }

    public function testNameCanBeSetAndRead(): void
    {
        $this->subject->setName('OV Aachen');
        self::assertSame('OV Aachen', $this->subject->getName());
    }

    public function testOeCodeDefaultsToEmpty(): void
    {
        self::assertSame('', $this->subject->getOeCode());
    }

    public function testActiveDefaultsToTrue(): void
    {
        self::assertTrue($this->subject->isActive());
    }

    public function testActiveCanBeToggled(): void
    {
        $this->subject->setActive(false);
        self::assertFalse($this->subject->isActive());
    }

    public function testMailAddressCanBeSet(): void
    {
        $this->subject->setMailAddress('ov-aachen@thw.de');
        self::assertSame('ov-aachen@thw.de', $this->subject->getMailAddress());
    }
}
