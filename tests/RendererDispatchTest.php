<?php

use Magdicom\Hooks;
use Magdicom\InvalidRendererException;
use Magdicom\MissingRendererException;
use Magdicom\ProcessingContext;
use Magdicom\Processor\ConcatenateRenderer;
use Magdicom\Processor\FirstNonNullProcessor;
use Magdicom\Processor\FirstProcessor;
use Magdicom\Processor\LastProcessor;
use Magdicom\Renderer;
use Magdicom\Resolver;

test('renderer configuration reuses the processor slot', function () {
    $hooks = new Hooks();
    $renderer = new ConcatenateRenderer();

    $hooks->setRenderer('RenderSlot', $renderer);

    expect($hooks->hasProcessor('RenderSlot'))->toBeTrue()
        ->and($hooks->processor('RenderSlot'))->toBe($renderer);
});

test('render requires a configured renderer instance', function () {
    $hooks = new Hooks();
    $hooks->addCollector('RenderMissing', fn (): string => 'value');

    expect(fn () => $hooks->render('RenderMissing'))
        ->toThrow(MissingRendererException::class);
});

test('render throws when a non-renderer processor is configured', function () {
    $hooks = new Hooks();
    $hooks->addCollector('RenderWrongType', fn (): string => 'value');
    $hooks->setProcessor('RenderWrongType', new FirstProcessor());

    expect(fn () => $hooks->render('RenderWrongType'))
        ->toThrow(InvalidRendererException::class);
});

test('render supports renderer instances and class names', function () {
    $hooks = new Hooks();
    $hooks->addCollector('RenderKinds', fn (): string => 'first');
    $hooks->addCollector('RenderKinds', fn (): string => 'second');

    $hooks->setRenderer('RenderKinds', new ConcatenateRenderer());

    expect($hooks->render('RenderKinds'))->toBe('firstsecond');

    $hooks->setRenderer('RenderKinds', ConcatenateRenderer::class);

    expect($hooks->render('RenderKinds'))->toBe('firstsecond');
});

test('render supports callable renderers', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            throw new RuntimeException('callable strings should not resolve through the resolver');
        }
    });

    $hooks->addCollector('CallableRenderKinds', fn (): string => 'first');
    $hooks->addCollector('CallableRenderKinds', fn (): string => 'second');

    $hooks->setRenderer('CallableRenderKinds', static function (array $results, ProcessingContext $context): string {
        return $context->hookPoint() . ':' . implode('|', $results);
    });

    expect($hooks->render('CallableRenderKinds'))->toBe('CallableRenderKinds:first|second');

    $hooks->setRenderer('CallableRenderKinds', new TestInvokableRenderer());

    expect($hooks->render('CallableRenderKinds'))->toBe('invokable:first+second');

    $hooks->setRenderer('CallableRenderKinds', 'testRendererFunction');

    expect($hooks->render('CallableRenderKinds'))->toBe('function:first-second');

    $hooks->setRenderer('CallableRenderKinds', TestRendererCallbacks::class . '::render');

    expect($hooks->render('CallableRenderKinds'))->toBe('static:first/second');
});

test('assigning a processor replaces a renderer and assigning a renderer replaces a processor', function () {
    $hooks = new Hooks();
    $hooks->addCollector('ReplaceProcessor', fn (): string => 'first');
    $hooks->addCollector('ReplaceProcessor', fn (): string => 'second');

    $hooks->setRenderer('ReplaceProcessor', new ConcatenateRenderer());

    expect($hooks->render('ReplaceProcessor'))->toBe('firstsecond');

    $hooks->setProcessor('ReplaceProcessor', new FirstProcessor());

    expect($hooks->process('ReplaceProcessor'))->toBe('first')
        ->and(fn () => $hooks->render('ReplaceProcessor'))->toThrow(InvalidRendererException::class);

    $hooks->setRenderer('ReplaceProcessor', new ConcatenateRenderer());

    expect($hooks->process('ReplaceProcessor'))->toBe('firstsecond')
        ->and($hooks->render('ReplaceProcessor'))->toBe('firstsecond');
});

test('class name renderers resolve through the configured resolver', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            expect($className)->toBe(ConcatenateRenderer::class);

            /** @implements Renderer<string> */
            return new class () implements Renderer {
                public function process(array $results, ProcessingContext $context): string
                {
                    return $context->hookPoint() . ':' . implode('|', $results);
                }
            };
        }
    });

    $hooks->addCollector('ResolverRenderer', fn (): string => 'left');
    $hooks->addCollector('ResolverRenderer', fn (): string => 'right');
    $hooks->setRenderer('ResolverRenderer', ConcatenateRenderer::class);

    expect($hooks->render('ResolverRenderer'))->toBe('ResolverRenderer:left|right');
});

test('renderer callable strings execute directly while non-callable strings resolve through the resolver', function () {
    $resolver = new class () implements Resolver {
        /** @var list<string> */
        public array $resolved = [];

        public function resolve(string $className): object
        {
            $this->resolved[] = $className;

            return new ConcatenateRenderer('|');
        }
    };

    $hooks = new Hooks($resolver);
    $hooks->addCollector('RendererStringKinds', fn (): string => 'first');
    $hooks->addCollector('RendererStringKinds', fn (): string => 'second');

    $hooks->setRenderer('RendererStringKinds', 'testRendererFunction');

    expect($hooks->render('RendererStringKinds'))->toBe('function:first-second')
        ->and($resolver->resolved)->toBe([]);

    $hooks->setRenderer('RendererStringKinds', ConcatenateRenderer::class);

    expect($hooks->render('RendererStringKinds'))->toBe('first|second')
        ->and($resolver->resolved)->toBe([ConcatenateRenderer::class]);
});

test('built in processors return the expected empty and non-empty values', function () {
    $context = new ProcessingContext('BuiltIn');

    expect((new FirstProcessor())->process([], $context))->toBeNull()
        ->and((new FirstProcessor())->process(['first', 'second'], $context))->toBe('first')
        ->and((new FirstNonNullProcessor())->process([], $context))->toBeNull()
        ->and((new FirstNonNullProcessor())->process([null, 'second'], $context))->toBe('second')
        ->and((new LastProcessor())->process([], $context))->toBeNull()
        ->and((new LastProcessor())->process(['first', 'second'], $context))->toBe('second')
        ->and((new ConcatenateRenderer())->process([], $context))->toBe('')
        ->and((new ConcatenateRenderer())->process(['a', 1, 2.5, true, null, new class () implements Stringable {
            public function __toString(): string
            {
                return 'obj';
            }
        }], $context))->toBe('a12.51obj');
});

test('concatenate renderer supports default and custom separators while preserving null positions', function () {
    $context = new ProcessingContext('Separator');

    expect((new ConcatenateRenderer())->process(['first', 'second'], $context))->toBe('firstsecond')
        ->and((new ConcatenateRenderer('|'))->process(['first', 'second'], $context))->toBe('first|second')
        ->and((new ConcatenateRenderer('|'))->process(['first', null, 'third'], $context))->toBe('first||third');
});

test('concatenate renderer rejects arrays resources and non stringable objects', function () {
    $renderer = new ConcatenateRenderer();
    $context = new ProcessingContext('Unexpected');
    $resource = fopen('php://memory', 'rb');

    expect($resource)->not->toBeFalse();

    expect(fn () => $renderer->process([['bad']], $context))
        ->toThrow(UnexpectedValueException::class)
        ->and(fn () => $renderer->process([$resource], $context))
        ->toThrow(UnexpectedValueException::class)
        ->and(fn () => $renderer->process([new stdClass()], $context))
        ->toThrow(UnexpectedValueException::class);

    fclose($resource);
});

test('resolved renderer classes must implement the renderer contract', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            expect($className)->toBe(ConcatenateRenderer::class);

            return new FirstProcessor();
        }
    });

    $hooks->addCollector('InvalidResolvedRenderer', fn (): string => 'value');
    $hooks->setRenderer('InvalidResolvedRenderer', ConcatenateRenderer::class);

    expect(fn () => $hooks->render('InvalidResolvedRenderer'))
        ->toThrow(InvalidRendererException::class);
});

test('render validates callable renderer output types explicitly', function () {
    $hooks = new Hooks();
    $hooks->addCollector('InvalidCallableRenderer', fn (): string => 'value');
    $hooks->setRenderer('InvalidCallableRenderer', static fn (array $results, ProcessingContext $context): mixed => 123);

    expect(fn () => $hooks->render('InvalidCallableRenderer'))
        ->toThrow(InvalidRendererException::class, 'Callable renderer for collector hook point "InvalidCallableRenderer" must return string, int returned.');
});

class TestInvokableRenderer
{
    public function __invoke(array $results, ProcessingContext $context): string
    {
        return 'invokable:' . implode('+', $results);
    }
}

class TestRendererCallbacks
{
    public static function render(array $results, ProcessingContext $context): string
    {
        return 'static:' . implode('/', $results);
    }
}

function testRendererFunction(array $results, ProcessingContext $context): string
{
    return 'function:' . implode('-', $results);
}
