<?php

declare(strict_types=1);

namespace Magdicom\Processor;

use Magdicom\ProcessingContext;
use Magdicom\ResultProcessor;

class FirstProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        return $results[0] ?? null;
    }
}
