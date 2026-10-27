<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateDesignImageException extends RuntimeException
{
    public function __construct(
        public readonly float $similarityScore,
        public readonly ?string $matchedDesignId = null,
    ) {
        parent::__construct('A same or very similar design image already exists.');
    }
}
