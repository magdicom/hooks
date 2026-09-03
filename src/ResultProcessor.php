<?php

declare(strict_types=1);

namespace Magdicom;

/**
 * @template TRaw of mixed
 * @template-covariant TProcessed of mixed
 */
interface ResultProcessor
{
    /**
     * @param list<TRaw> $results
     * @return TProcessed
     */
    public function process(array $results, ProcessingContext $context): mixed;
}
