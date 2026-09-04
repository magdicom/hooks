<?php

declare(strict_types=1);

namespace Magdicom\PhpStanFixtures;

use Magdicom\ProcessingContext;
use Magdicom\Renderer;
use Magdicom\ResultProcessor;

/**
 * @param ResultProcessor<mixed, mixed> $processor
 */
function useMixedProcessor(ResultProcessor $processor): void
{
    $processor->process(['value', 1, null], new ProcessingContext('mixed'));
}

/**
 * @param ResultProcessor<string, string> $processor
 */
function useStringProcessor(ResultProcessor $processor): void
{
    $processor->process(['left', 'right'], new ProcessingContext('strings'));
}

/**
 * @param Renderer<string> $renderer
 */
function useStringRenderer(Renderer $renderer): void
{
    $renderer->process(['left', 'right'], new ProcessingContext('render'));
}

/** @implements ResultProcessor<mixed, mixed> */
final class MixedProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        return [$context->hookPoint(), $results];
    }
}

/** @implements ResultProcessor<string, string> */
final class StringProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        return implode('|', $results);
    }
}

/** @implements Renderer<string> */
final class StringRenderer implements Renderer
{
    public function process(array $results, ProcessingContext $context): string
    {
        return implode('|', $results);
    }
}

function exerciseCustomImplementations(): void
{
    useMixedProcessor(new MixedProcessor());
    useStringProcessor(new StringProcessor());
    useStringRenderer(new StringRenderer());
}
