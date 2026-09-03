<?php

declare(strict_types=1);

namespace Magdicom\Processor;

use Magdicom\ProcessingContext;
use Magdicom\Renderer;
use Stringable;
use UnexpectedValueException;

/** @implements Renderer<mixed> */
class ConcatenateRenderer implements Renderer
{
    public function __construct(
        private readonly string $separator = ''
    ) {
    }

    public function process(array $results, ProcessingContext $context): string
    {
        return implode(
            $this->separator,
            array_map($this->stringify(...), $results)
        );
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new UnexpectedValueException(sprintf(
            'ConcatenateRenderer cannot render %s values.',
            get_debug_type($value)
        ));
    }
}
