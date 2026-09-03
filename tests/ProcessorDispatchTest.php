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
    /** @implements ResultProcessor<string, string> */
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
    /** @implements ResultProcessor<mixed, list<mixed>> */
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

    /** @implements ResultProcessor<string, string> */
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

test('process supports named-function and callable static-method strings', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            throw new RuntimeException('callable strings should not resolve through the resolver');
        }
    });

    $hooks->addCollector('CallableProcessorKinds', fn (): string => 'first');
    $hooks->addCollector('CallableProcessorKinds', fn (): string => 'second');

    $hooks->setProcessor('CallableProcessorKinds', 'testProcessorFunction');

    expect($hooks->process('CallableProcessorKinds'))->toBe('function:first-second');

    $hooks->setProcessor('CallableProcessorKinds', TestProcessorCallbacks::class . '::process');

    expect($hooks->process('CallableProcessorKinds'))->toBe('static:first|second');
});

test('class name processors resolve through the configured resolver', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            expect($className)->toBe(TestCollectorProcessor::class);

            /** @implements ResultProcessor<string, string> */
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

test('processor callable strings execute directly while non-callable strings resolve through the resolver', function () {
    $resolver = new class () implements Resolver {
        /** @var list<string> */
        public array $resolved = [];

        public function resolve(string $className): object
        {
            $this->resolved[] = $className;

            return new TestCollectorProcessor();
        }
    };

    $hooks = new Hooks($resolver);
    $hooks->addCollector('ProcessorStringKinds', fn (): string => 'first');
    $hooks->addCollector('ProcessorStringKinds', fn (): string => 'second');

    $hooks->setProcessor('ProcessorStringKinds', 'testProcessorFunction');

    expect($hooks->process('ProcessorStringKinds'))->toBe('function:first-second')
        ->and($resolver->resolved)->toBe([]);

    $hooks->setProcessor('ProcessorStringKinds', TestCollectorProcessor::class);

    expect($hooks->process('ProcessorStringKinds'))->toBe('processed:first,second')
        ->and($resolver->resolved)->toBe([TestCollectorProcessor::class]);
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

/** @implements ResultProcessor<string, string> */
class TestCollectorProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        return 'processed:' . implode(',', $results);
    }
}

class TestProcessorCallbacks
{
    public static function process(array $results, ProcessingContext $context): string
    {
        return 'static:' . implode('|', $results);
    }
}

function testProcessorFunction(array $results, ProcessingContext $context): string
{
    return 'function:' . implode('-', $results);
}
