<?php

namespace App\Services\DesignFiles\Contracts;

interface DesignFileMetadataParser
{
    /** @return list<string> */
    public function extensions(): array;

    public function formatName(): string;

    /** @return array<string, mixed> */
    public function parse(string $path): array;
}
