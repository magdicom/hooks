<?php

declare(strict_types=1);

namespace Magdicom;

/**
 * @template TRaw of mixed
 * @extends ResultProcessor<TRaw, string>
 */
interface Renderer extends ResultProcessor
{
    /**
     * @param list<TRaw> $results
     */
    public function process(array $results, ProcessingContext $context): string;
}
