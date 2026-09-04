<?php

declare(strict_types=1);

namespace Magdicom\Processor;

use InvalidArgumentException;
use Magdicom\ProcessingContext;
use Magdicom\ResultProcessor;
use UnexpectedValueException;

/** @implements ResultProcessor<mixed, list<mixed>> */
class FlattenProcessor implements ResultProcessor
{
    public function __construct(
        private readonly int $depth = -1
    ) {
        if ($this->depth < -1) {
            throw new InvalidArgumentException(sprintf(
                'FlattenProcessor depth must be -1 or greater, %d given.',
                $this->depth
            ));
        }
    }

    public function process(array $results, ProcessingContext $context): mixed
    {
        $flattened = [];

        foreach ($results as $index => $result) {
            if (! is_array($result)) {
                throw new UnexpectedValueException(sprintf(
                    'FlattenProcessor expects array results; result at index %d is %s.',
                    $index,
                    get_debug_type($result)
                ));
            }

            foreach ($result as $value) {
                array_push($flattened, ...$this->flattenValue($value, $this->depth));
            }
        }

        return $flattened;
    }

    /**
     * @return list<mixed>
     */
    private function flattenValue(mixed $value, int $depth): array
    {
        if (! is_array($value) || $depth === 0) {
            return [$value];
        }

        $flattened = [];
        $nextDepth = $depth === -1 ? -1 : $depth - 1;

        foreach ($value as $nestedValue) {
            array_push($flattened, ...$this->flattenValue($nestedValue, $nextDepth));
        }

        return $flattened;
    }
}
