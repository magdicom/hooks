<?php

declare(strict_types=1);

namespace Magdicom\Exceptions;

use Magdicom\ResultProcessor;
use RuntimeException;

class InvalidProcessorException extends RuntimeException
{
    public static function forResolvedClass(string $className): self
    {
        return new self(sprintf(
            'Resolved processor "%s" must implement %s.',
            $className,
            ResultProcessor::class
        ));
    }
}
