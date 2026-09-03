<?php

declare(strict_types=1);

namespace Magdicom;

use RuntimeException;

class InvalidRendererException extends RuntimeException
{
    public static function forHookPoint(string $hookPoint): self
    {
        return new self(sprintf(
            'Collector hook point "%s" is configured with a processor that is not a renderer.',
            $hookPoint
        ));
    }

    public static function forResolvedClass(string $className): self
    {
        return new self(sprintf(
            'Resolved renderer "%s" must implement %s.',
            $className,
            Renderer::class
        ));
    }
}
