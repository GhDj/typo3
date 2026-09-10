<?php

declare(strict_types=1);

namespace Thw\ThwSso\Service;

/**
 * Derives the physical share name for a directory within a given OE.
 *
 * Per client spec the actual share is "{oe_code}-{directory_name}",
 * e.g. "OAAC-Allgemein" for directory "Allgemein" in OE "OAAC".
 */
class DirectoryNameService
{
    public function getShareName(string $oeCode, string $directoryName): string
    {
        return $oeCode . '-' . $directoryName;
    }
}
