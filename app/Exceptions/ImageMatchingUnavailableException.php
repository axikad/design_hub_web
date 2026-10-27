<?php

namespace App\Exceptions;

use RuntimeException;

class ImageMatchingUnavailableException extends RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Image matching is temporarily unavailable.', previous: $previous);
    }
}
