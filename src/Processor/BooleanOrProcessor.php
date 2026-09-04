<?php

declare(strict_types=1);

namespace Magdicom\Processor;

use Magdicom\ProcessingContext;
use Magdicom\ResultProcessor;
use UnexpectedValueException;

/** @implements ResultProcessor<mixed, bool> */
class BooleanOrProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        foreach ($results as $index => $result) {
            if (! is_bool($result)) {
                throw new UnexpectedValueException(sprintf(
                    'BooleanOrProcessor expects boolean results; result at index %d is %s.',
                    $index,
                    get_debug_type($result)
                ));
            }

            if ($result === true) {
                return true;
            }
        }

        return false;
    }
}
