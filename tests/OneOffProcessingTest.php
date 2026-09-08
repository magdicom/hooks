<?php

use Magdicom\Exceptions\InvalidProcessorException;
use Magdicom\Exceptions\InvalidRendererException;
use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\Processors\ConcatenateRenderer;
use Magdicom\Processors\FirstNonNullProcessor;
use Magdicom\Processors\FirstProcessor;
use Magdicom\Renderer;
use Magdicom\Resolver;
use Magdicom\ResultProcessor;

test('processWith uses a processor instance without storing it', function () {
    $hooks = new Hooks();
    $processor = new OneOffProcessor();

    $hooks->addCollector('OneOffInstance', fn (): string => 'first');
    $hooks->addCollector('OneOffInstance', fn (): string => 'second');

    expect($hooks->hasProcessor('OneOffInstance'))->toBeFalse()
        ->and($hooks->processWith('OneOffInstance', $processor))->toBe('one-off:first,second')
        ->and($hooks->hasProcessor('OneOffInstance'))->toBeFalse()
        ->and($hooks->processor('OneOffInstance'))->toBeNull();
});

test('processWith accepts a renderer instance as a result processor', function () {
    $hooks = new Hooks();

    $hooks->addCollector('OneOffRendererAsProcessor', fn (): string => 'first');
    $hooks->addCollector('OneOffRendererAsProcessor', fn (): string => 'second');

    expect($hooks->processWith('OneOffRendererAsProcessor', new ConcatenateRenderer('|')))
        ->toBe('first|second');
});

test('processWith uses a callable and receives original invocation arguments', function () {
    $hooks = new Hooks();

    $hooks->addCollector('OneOffCallable', fn (string $customer): string => $customer . ':primary');
    $hooks->addCollector('OneOffCallable', fn (string $customer): string => $customer . ':fallback');

    $result = $hooks->processWith(
        'OneOffCallable',
        static function (array $results, ProcessingContext $context): array {
            return [$context->hookPoint(), $context->arguments(), $results];
        },
        'customer-1'
    );

    expect($result)->toBe([
        'OneOffCallable',
        ['customer-1'],
        ['customer-1:primary', 'customer-1:fallback'],
    ]);
});

test('processWith uses a resolver-backed class name', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            expect($className)->toBe(OneOffProcessor::class);

            return new OneOffProcessor();
        }
    });

    $hooks->addCollector('OneOffClass', fn (): string => 'left');
    $hooks->addCollector('OneOffClass', fn (): string => 'right');

    expect($hooks->processWith('OneOffClass', OneOffProcessor::class))
        ->toBe('one-off:left,right');
});

test('renderWith uses a renderer instance without storing it', function () {
    $hooks = new Hooks();
    $renderer = new ConcatenateRenderer('|');

    $hooks->addCollector('OneOffRenderInstance', fn (): string => 'first');
    $hooks->addCollector('OneOffRenderInstance', fn (): string => 'second');

    expect($hooks->hasProcessor('OneOffRenderInstance'))->toBeFalse()
        ->and($hooks->renderWith('OneOffRenderInstance', $renderer))->toBe('first|second')
        ->and($hooks->hasProcessor('OneOffRenderInstance'))->toBeFalse()
        ->and($hooks->processor('OneOffRenderInstance'))->toBeNull();
});

test('renderWith uses a callable and receives original invocation arguments', function () {
    $hooks = new Hooks();

    $hooks->addCollector('OneOffRenderCallable', fn (string $order): string => $order . ':summary');

    expect($hooks->renderWith(
        'OneOffRenderCallable',
        static function (array $results, ProcessingContext $context): string {
            return $context->hookPoint() . ':' . implode(',', $context->arguments()) . ':' . implode('|', $results);
        },
        'order-1'
    ))->toBe('OneOffRenderCallable:order-1:order-1:summary');
});

test('renderWith uses a resolver-backed class name', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            expect($className)->toBe(OneOffRenderer::class);

            return new OneOffRenderer();
        }
    });

    $hooks->addCollector('OneOffRenderClass', fn (): string => 'header');
    $hooks->addCollector('OneOffRenderClass', fn (): string => 'body');

    expect($hooks->renderWith('OneOffRenderClass', OneOffRenderer::class))
        ->toBe('receipt:header/body');
});

test('one-off processors and renderers receive empty collector results', function () {
    $hooks = new Hooks();

    expect($hooks->processWith('OneOffEmptyProcessor', new FirstNonNullProcessor()))->toBeNull()
        ->and($hooks->renderWith('OneOffEmptyRenderer', new ConcatenateRenderer('|')))->toBe('');
});

test('processWith and renderWith collect raw listener results exactly once', function () {
    $hooks = new Hooks();
    $calls = 0;

    $hooks->addCollector('OneOffCollectOnce', function () use (&$calls): string {
        $calls++;

        return 'value';
    });

    expect($hooks->processWith('OneOffCollectOnce', new FirstProcessor()))->toBe('value')
        ->and($calls)->toBe(1)
        ->and($hooks->renderWith('OneOffCollectOnce', new ConcatenateRenderer()))->toBe('value')
        ->and($calls)->toBe(2);
});

test('processWith throws InvalidProcessorException for invalid resolved processors', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            return new stdClass();
        }
    });

    expect(fn () => $hooks->processWith('OneOffInvalidProcessor', OneOffProcessor::class))
        ->toThrow(InvalidProcessorException::class);
});

test('renderWith throws InvalidRendererException for invalid resolved renderers', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            return new FirstProcessor();
        }
    });

    expect(fn () => $hooks->renderWith('OneOffInvalidRenderer', OneOffRenderer::class))
        ->toThrow(InvalidRendererException::class);
});

test('renderWith throws InvalidRendererException when a callable returns a non string', function () {
    $hooks = new Hooks();
    $hooks->addCollector('OneOffInvalidCallableRenderer', fn (): string => 'value');

    expect(fn () => $hooks->renderWith(
        'OneOffInvalidCallableRenderer',
        static fn (array $results, ProcessingContext $context): mixed => ['not-string']
    ))->toThrow(
        InvalidRendererException::class,
        'Callable renderer for collector hook point "OneOffInvalidCallableRenderer" must return string, array returned.'
    );
});

test('one-off collector processor renderer and resolver exceptions propagate unchanged', function () {
    $hooks = new Hooks();
    $hooks->addCollector('OneOffCollectorThrows', function (): string {
        throw new RuntimeException('collector failed');
    });

    expect(fn () => $hooks->processWith('OneOffCollectorThrows', new OneOffProcessor()))
        ->toThrow(RuntimeException::class, 'collector failed');

    expect(fn () => $hooks->processWith(
        'OneOffProcessorThrows',
        static function (array $results, ProcessingContext $context): mixed {
            throw new RuntimeException('processor failed');
        }
    ))->toThrow(RuntimeException::class, 'processor failed');

    expect(fn () => $hooks->renderWith(
        'OneOffRendererThrows',
        static function (array $results, ProcessingContext $context): string {
            throw new RuntimeException('renderer failed');
        }
    ))->toThrow(RuntimeException::class, 'renderer failed');

    $resolverHooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            throw new RuntimeException('resolver failed');
        }
    });

    expect(fn () => $resolverHooks->processWith('OneOffResolverThrows', OneOffProcessor::class))
        ->toThrow(RuntimeException::class, 'resolver failed');
});

test('one-off processing ignores and preserves persistent processor configuration', function () {
    $hooks = new Hooks();

    $hooks->addCollector('OneOffPersistentProcessor', fn (): string => 'first');
    $hooks->addCollector('OneOffPersistentProcessor', fn (): string => 'second');
    $hooks->setProcessor('OneOffPersistentProcessor', new FirstProcessor());

    expect($hooks->process('OneOffPersistentProcessor'))->toBe('first')
        ->and($hooks->processWith('OneOffPersistentProcessor', new OneOffProcessor()))->toBe('one-off:first,second')
        ->and($hooks->processor('OneOffPersistentProcessor'))->toBeInstanceOf(FirstProcessor::class)
        ->and($hooks->process('OneOffPersistentProcessor'))->toBe('first');
});

test('one-off rendering ignores and preserves persistent renderer configuration', function () {
    $hooks = new Hooks();

    $hooks->addCollector('OneOffPersistentRenderer', fn (): string => 'first');
    $hooks->addCollector('OneOffPersistentRenderer', fn (): string => 'second');
    $hooks->setRenderer('OneOffPersistentRenderer', new ConcatenateRenderer('|'));

    expect($hooks->render('OneOffPersistentRenderer'))->toBe('first|second')
        ->and($hooks->renderWith('OneOffPersistentRenderer', new OneOffRenderer()))->toBe('receipt:first/second')
        ->and($hooks->processor('OneOffPersistentRenderer'))->toBeInstanceOf(ConcatenateRenderer::class)
        ->and($hooks->render('OneOffPersistentRenderer'))->toBe('first|second');
});

test('persistent configuration survives a one-off processor exception', function () {
    $hooks = new Hooks();

    $hooks->addCollector('OneOffPersistentAfterException', fn (): string => 'value');
    $hooks->setProcessor('OneOffPersistentAfterException', new FirstProcessor());

    expect(fn () => $hooks->processWith(
        'OneOffPersistentAfterException',
        static function (array $results, ProcessingContext $context): mixed {
            throw new RuntimeException('one-off failed');
        }
    ))->toThrow(RuntimeException::class, 'one-off failed')
        ->and($hooks->processor('OneOffPersistentAfterException'))->toBeInstanceOf(FirstProcessor::class)
        ->and($hooks->process('OneOffPersistentAfterException'))->toBe('value');
});

test('nested processWith and renderWith calls remain isolated', function () {
    $hooks = new Hooks();

    $hooks->addCollector('OuterOneOff', function () use ($hooks): string {
        expect($hooks->processWith('InnerOneOffProcess', new FirstProcessor()))->toBe('inner-process')
            ->and($hooks->renderWith('InnerOneOffRender', new ConcatenateRenderer('|')))->toBe('inner|render');

        return 'outer';
    });
    $hooks->addCollector('InnerOneOffProcess', fn (): string => 'inner-process');
    $hooks->addCollector('InnerOneOffRender', fn (): string => 'inner');
    $hooks->addCollector('InnerOneOffRender', fn (): string => 'render');

    expect($hooks->processWith('OuterOneOff', new FirstProcessor()))->toBe('outer');
});

test('listener mutations during one-off processing affect only later invocations', function () {
    $hooks = new Hooks();
    $events = [];

    $hooks->addCollector('OneOffMutation', function () use ($hooks, &$events): string {
        $events[] = 'first';
        $hooks->addCollector('OneOffMutation', function () use (&$events): string {
            $events[] = 'late';

            return 'late';
        }, 30);

        return 'first';
    }, 10);
    $hooks->addCollector('OneOffMutation', function () use (&$events): string {
        $events[] = 'second';

        return 'second';
    }, 20);

    expect($hooks->processWith('OneOffMutation', new ConcatenateRenderer('|')))->toBe('first|second')
        ->and($events)->toBe(['first', 'second'])
        ->and($hooks->renderWith('OneOffMutation', new ConcatenateRenderer('|')))->toBe('first|second|late')
        ->and($events)->toBe(['first', 'second', 'first', 'second', 'late']);
});

test('collect continues to bypass persistent and one-off processing', function () {
    $hooks = new Hooks();

    $hooks->addCollector('OneOffCollectRaw', fn (): string => 'first');
    $hooks->addCollector('OneOffCollectRaw', fn (): string => 'second');
    $hooks->setProcessor('OneOffCollectRaw', new FirstProcessor());

    expect($hooks->collect('OneOffCollectRaw'))->toBe(['first', 'second'])
        ->and($hooks->processWith('OneOffCollectRaw', new ConcatenateRenderer('|')))->toBe('first|second')
        ->and($hooks->collect('OneOffCollectRaw'))->toBe(['first', 'second']);
});

test('processor-specific shortcut methods are not introduced', function () {
    $methods = get_class_methods(Hooks::class);

    expect($methods)->not->toContain('first')
        ->and($methods)->not->toContain('last')
        ->and($methods)->not->toContain('firstNonNull')
        ->and($methods)->not->toContain('flatten')
        ->and($methods)->not->toContain('merge')
        ->and($methods)->not->toContain('booleanAnd')
        ->and($methods)->not->toContain('booleanOr')
        ->and($methods)->not->toContain('concatenate');
});

/** @implements ResultProcessor<string, string> */
class OneOffProcessor implements ResultProcessor
{
    public function process(array $results, ProcessingContext $context): mixed
    {
        return 'one-off:' . implode(',', $results);
    }
}

/** @implements Renderer<string> */
class OneOffRenderer implements Renderer
{
    public function process(array $results, ProcessingContext $context): string
    {
        return 'receipt:' . implode('/', $results);
    }
}
