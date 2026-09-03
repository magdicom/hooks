<?php

use Magdicom\ProcessingContext;
use Magdicom\Renderer;
use Magdicom\ResultProcessor;

test('processing context stores the collector hook point and invocation arguments', function () {
    $object = new stdClass();
    $context = new ProcessingContext('Report', 'first', 2, $object);

    expect($context->hookPoint())->toBe('Report')
        ->and($context->arguments())->toBe(['first', 2, $object]);
});

test('processing context arguments are returned as a defensive copy', function () {
    $context = new ProcessingContext('CopyCheck', 'first');
    $arguments = $context->arguments();
    $arguments[] = 'second';

    expect($context->arguments())->toBe(['first'])
        ->and($arguments)->toBe(['first', 'second']);
});

test('result processor receives raw collector results and processing context', function () {
    /** @implements ResultProcessor<mixed, array{hook: string, arguments: list<mixed>, results: list<mixed>}> */
    $processor = new class () implements ResultProcessor {
        public function process(array $results, ProcessingContext $context): mixed
        {
            return [
                'hook' => $context->hookPoint(),
                'arguments' => $context->arguments(),
                'results' => $results,
            ];
        }
    };

    expect($processor->process(['first', ['id' => 2]], new ProcessingContext('ProcessMe', 'arg')))
        ->toBe([
            'hook' => 'ProcessMe',
            'arguments' => ['arg'],
            'results' => ['first', ['id' => 2]],
        ]);
});

test('renderer is a specialized result processor with string output', function () {
    /** @implements Renderer<string> */
    $renderer = new class () implements Renderer {
        public function process(array $results, ProcessingContext $context): string
        {
            return $context->hookPoint() . ':' . implode(',', $results);
        }
    };

    expect($renderer)->toBeInstanceOf(ResultProcessor::class)
        ->and($renderer->process(['a', 'b'], new ProcessingContext('RenderMe')))->toBe('RenderMe:a,b');
});
