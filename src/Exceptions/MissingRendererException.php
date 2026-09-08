<?php

declare(strict_types=1);

namespace Magdicom\Exceptions;

use RuntimeException;

class MissingRendererException extends RuntimeException
{
    public static function forHookPoint(string $hookPoint): self
    {
        return new self(sprintf('No renderer is configured for collector hook point "%s".', $hookPoint));
    }
}
