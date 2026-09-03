<?php

declare(strict_types=1);

namespace Magdicom\Processor;

use Magdicom\ProcessingContext;
use Magdicom\ResultProcessor;

class FirstNonNullProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        foreach ($results as $result) {
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }
}
