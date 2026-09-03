<?php

declare(strict_types=1);

namespace Magdicom;

interface ResultProcessor
{
    /**
     * @param list<mixed> $results
     */
    public function process(array $results, ProcessingContext $context): mixed;
}
