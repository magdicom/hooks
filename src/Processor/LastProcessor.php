<?php

declare(strict_types=1);

namespace Magdicom\Processor;

use Magdicom\ProcessingContext;
use Magdicom\ResultProcessor;

class LastProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        if ($results === []) {
            return null;
        }

        return $results[array_key_last($results)];
    }
}
