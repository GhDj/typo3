<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Tests\Unit\Domain\Model;

use Init\Thw\Ovssp\Domain\Model\Directory;
use PHPUnit\Framework\TestCase;

class DirectoryTest extends TestCase
{
    private Directory $subject;

    protected function setUp(): void
    {
        $this->subject = new Directory();
    }

    public function testNameDefaultsToEmpty(): void
    {
        self::assertSame('', $this->subject->getName());
    }

    public function testNameCanBeSet(): void
    {
        $this->subject->setName('Allgemein');
        self::assertSame('Allgemein', $this->subject->getName());
    }

    public function testAllowsReadDefaultsToFalse(): void
    {
        self::assertFalse($this->subject->getAllowsRead());
    }

    public function testAllowsReadCanBeToggled(): void
    {
        $this->subject->setAllowsRead(true);
        self::assertTrue($this->subject->getAllowsRead());
    }

    public function testAllowsWriteDefaultsToFalse(): void
    {
        self::assertFalse($this->subject->getAllowsWrite());
    }

    public function testAllowsWriteCanBeToggled(): void
    {
        $this->subject->setAllowsWrite(true);
        self::assertTrue($this->subject->getAllowsWrite());
    }

    public function testSortKeyDefaultsToNull(): void
    {
        self::assertNull($this->subject->getSortKey());
    }

    public function testSortKeyCanBeSet(): void
    {
        $this->subject->setSortKey(5);
        self::assertSame(5, $this->subject->getSortKey());
    }

    public function testSortKeyCanBeSetToNull(): void
    {
        $this->subject->setSortKey(5);
        $this->subject->setSortKey(null);
        self::assertNull($this->subject->getSortKey());
    }

    public function testDescriptionDefaultsToEmpty(): void
    {
        self::assertSame('', $this->subject->getDescription());
    }

    public function testDescriptionCanBeSet(): void
    {
        $this->subject->setDescription('Allgemeines Arbeitsverzeichnis');
        self::assertSame('Allgemeines Arbeitsverzeichnis', $this->subject->getDescription());
    }
}
