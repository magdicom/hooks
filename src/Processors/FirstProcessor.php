<?php

declare(strict_types=1);

namespace Magdicom\Processors;

use Magdicom\ProcessingContext;
use Magdicom\ResultProcessor;

/** @implements ResultProcessor<mixed, mixed> */
class FirstProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        return $results[0] ?? null;
    }
}
