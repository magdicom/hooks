<?php

declare(strict_types=1);

namespace Magdicom\Processor;

use Magdicom\ProcessingContext;
use Magdicom\ResultProcessor;
use UnexpectedValueException;

/** @implements ResultProcessor<mixed, array<mixed>> */
class MergeProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        $merged = [];

        foreach ($results as $index => $result) {
            if (! is_array($result)) {
                throw new UnexpectedValueException(sprintf(
                    'MergeProcessor expects array results; result at index %d is %s.',
                    $index,
                    get_debug_type($result)
                ));
            }

            $merged = array_merge($merged, $result);
        }

        return $merged;
    }
}
