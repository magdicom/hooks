<?php

declare(strict_types=1);

namespace Magdicom\Processors;

use Magdicom\ProcessingContext;
use Magdicom\ResultProcessor;
use UnexpectedValueException;

/** @implements ResultProcessor<mixed, bool> */
class BooleanAndProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        foreach ($results as $index => $result) {
            if (! is_bool($result)) {
                throw new UnexpectedValueException(sprintf(
                    'BooleanAndProcessor expects boolean results; result at index %d is %s.',
                    $index,
                    get_debug_type($result)
                ));
            }

            if ($result === false) {
                return false;
            }
        }

        return true;
    }
}
