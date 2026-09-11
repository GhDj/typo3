<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Tests\Unit\Service;

use Init\Thw\Ovssp\Service\DirectoryNameService;
use PHPUnit\Framework\TestCase;

class DirectoryNameServiceTest extends TestCase
{
    private DirectoryNameService $subject;

    protected function setUp(): void
    {
        $this->subject = new DirectoryNameService();
    }

    public function testShareNameCombinesOeCodeAndDirectoryName(): void
    {
        self::assertSame('OAAC-Allgemein', $this->subject->getShareName('OAAC', 'Allgemein'));
    }

    public function testShareNameWithDifferentOe(): void
    {
        self::assertSame('OMUC-Einsatz', $this->subject->getShareName('OMUC', 'Einsatz'));
    }

    public function testShareNamePreservesSpecialCharacters(): void
    {
        self::assertSame('OAAC-TZ_Zugführung', $this->subject->getShareName('OAAC', 'TZ_Zugführung'));
    }

    public function testShareNameWithEmptyDirectoryName(): void
    {
        self::assertSame('OAAC-', $this->subject->getShareName('OAAC', ''));
    }
}
