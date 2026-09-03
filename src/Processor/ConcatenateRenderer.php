<?php

declare(strict_types=1);

namespace Magdicom\Processor;

use Magdicom\ProcessingContext;
use Magdicom\Renderer;
use Stringable;
use UnexpectedValueException;

class ConcatenateRenderer implements Renderer
{
    public function process(array $results, ProcessingContext $context): string
    {
        $rendered = '';

        foreach ($results as $result) {
            $rendered .= $this->stringify($result);
        }

        return $rendered;
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
