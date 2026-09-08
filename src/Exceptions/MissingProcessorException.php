<?php

declare(strict_types=1);

namespace Magdicom\Exceptions;

use RuntimeException;

class MissingProcessorException extends RuntimeException
{
    public static function forHookPoint(string $hookPoint): self
    {
        return new self(sprintf('No processor is configured for collector hook point "%s".', $hookPoint));
    }
}
