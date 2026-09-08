<?php

declare(strict_types=1);

namespace Magdicom\PhpStanFixtures;

use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\Processors\BooleanAndProcessor;
use Magdicom\Processors\BooleanOrProcessor;
use Magdicom\Processors\ConcatenateRenderer;
use Magdicom\Processors\FlattenProcessor;
use Magdicom\Processors\FirstProcessor;
use Magdicom\Processors\MergeProcessor;
use Magdicom\Renderer;
use Magdicom\ResultProcessor;

final class ConsumerHooksUsage
{
    public function configure(Hooks $hooks): void
    {
        $hooks->setProcessor('mixed', new MixedProcessor());
        $hooks->setProcessor('callable', static fn (array $results, ProcessingContext $context): mixed => [$context->hookPoint(), $results]);
        $hooks->setProcessor('class-string', BroadProcessor::class);
        $hooks->setProcessor('built-in', new FirstProcessor());
        $hooks->setProcessor('flatten', new FlattenProcessor());
        $hooks->setProcessor('merge', new MergeProcessor());
        $hooks->setProcessor('boolean-and', new BooleanAndProcessor());
        $hooks->setProcessor('boolean-or', new BooleanOrProcessor());

        $hooks->setRenderer('renderer-callable', static fn (array $results, ProcessingContext $context): string => $context->hookPoint() . ':' . count($results));
        $hooks->setRenderer('renderer-instance', new ConcatenateRenderer());
        $hooks->setRenderer('renderer-class-string', BroadRenderer::class);
    }
}

/** @implements ResultProcessor<mixed, mixed> */
final class BroadProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        return [$context->hookPoint(), $results];
    }
}

/** @implements Renderer<mixed> */
final class BroadRenderer implements Renderer
{
    public function process(array $results, ProcessingContext $context): string
    {
        return $context->hookPoint() . ':' . count($results);
    }
}
