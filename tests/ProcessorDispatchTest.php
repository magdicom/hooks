<?php

use Magdicom\Hooks;
use Magdicom\InvalidProcessorException;
use Magdicom\MissingProcessorException;
use Magdicom\ProcessingContext;
use Magdicom\Resolver;
use Magdicom\ResultProcessor;

test('collect bypasses configured processors and returns raw collector results', function () {
    $hooks = new Hooks();

    $hooks->addCollector('CollectRaw', fn (): string => 'first', 10);
    $hooks->addCollector('CollectRaw', fn (): string => 'second', 20);
    $hooks->setProcessor('CollectRaw', new class () implements ResultProcessor {
        public function process(array $results, ProcessingContext $context): mixed
        {
            return strtoupper(implode(',', $results));
        }
    });

    expect($hooks->collect('CollectRaw'))->toBe(['first', 'second'])
        ->and($hooks->process('CollectRaw'))->toBe('FIRST,SECOND');
});

test('processor management stores and clears instances callables and class names', function () {
    $hooks = new Hooks();
    $instance = new class () implements ResultProcessor {
        public function process(array $results, ProcessingContext $context): mixed
        {
            return $results;
        }
    };
    $callable = static fn (array $results, ProcessingContext $context): mixed => count($results);

    expect($hooks->hasProcessor('Managed'))->toBeFalse()
        ->and($hooks->processor('Managed'))->toBeNull();

    $hooks->setProcessor('Managed', $instance);

    expect($hooks->hasProcessor('Managed'))->toBeTrue()
        ->and($hooks->processor('Managed'))->toBe($instance);

    $hooks->setProcessor('Managed', $callable);

    expect($hooks->processor('Managed'))->toBe($callable);

    $hooks->setProcessor('Managed', TestCollectorProcessor::class);

    expect($hooks->processor('Managed'))->toBe(TestCollectorProcessor::class)
        ->and($hooks->clearProcessor('Managed'))->toBeTrue()
        ->and($hooks->clearProcessor('Managed'))->toBeFalse()
        ->and($hooks->processor('Managed'))->toBeNull();
});

test('process supports processor instances callables and class names', function () {
    $hooks = new Hooks();

    $hooks->addCollector('ProcessorKinds', fn (): string => 'first', 10);
    $hooks->addCollector('ProcessorKinds', fn (): string => 'second', 20);

    $hooks->setProcessor('ProcessorKinds', new class () implements ResultProcessor {
        public function process(array $results, ProcessingContext $context): mixed
        {
            return implode('|', $results);
        }
    });

    expect($hooks->process('ProcessorKinds'))->toBe('first|second');

    $hooks->setProcessor('ProcessorKinds', static function (array $results, ProcessingContext $context): mixed {
        return [$context->hookPoint(), $context->arguments(), $results];
    });

    expect($hooks->process('ProcessorKinds', 'arg'))->toBe([
        'ProcessorKinds',
        ['arg'],
        ['first', 'second'],
    ]);

    $hooks->setProcessor('ProcessorKinds', TestCollectorProcessor::class);

    expect($hooks->process('ProcessorKinds'))->toBe('processed:first,second');
});

test('class name processors resolve through the configured resolver', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            expect($className)->toBe(TestCollectorProcessor::class);

            return new class () implements ResultProcessor {
                public function process(array $results, ProcessingContext $context): mixed
                {
                    return $context->hookPoint() . ':' . implode('+', $results);
                }
            };
        }
    });

    $hooks->addCollector('ResolverProcessor', fn (): string => 'left');
    $hooks->addCollector('ResolverProcessor', fn (): string => 'right');
    $hooks->setProcessor('ResolverProcessor', TestCollectorProcessor::class);

    expect($hooks->process('ResolverProcessor'))->toBe('ResolverProcessor:left+right');
});

test('process passes empty raw results to the configured processor', function () {
    $hooks = new Hooks();

    $hooks->setProcessor('EmptyCollector', static function (array $results, ProcessingContext $context): mixed {
        return [$context->hookPoint(), $results, $context->arguments()];
    });

    expect($hooks->process('EmptyCollector', 'arg'))->toBe([
        'EmptyCollector',
        [],
        ['arg'],
    ]);
});

test('process throws when no processor is configured', function () {
    $hooks = new Hooks();

    expect(fn () => $hooks->process('MissingProcessor'))
        ->toThrow(MissingProcessorException::class);
});

test('processor exceptions propagate unchanged', function () {
    $hooks = new Hooks();

    $hooks->setProcessor('ProcessorThrows', static function (array $results, ProcessingContext $context): mixed {
        throw new RuntimeException('processor failed');
    });

    expect(fn () => $hooks->process('ProcessorThrows'))
        ->toThrow(RuntimeException::class, 'processor failed');
});

test('resolved processor classes must implement the result processor contract', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            return new stdClass();
        }
    });

    $hooks->setProcessor('InvalidProcessor', TestCollectorProcessor::class);

    expect(fn () => $hooks->process('InvalidProcessor'))
        ->toThrow(InvalidProcessorException::class);
});

class TestCollectorProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        return 'processed:' . implode(',', $results);
    }
}
