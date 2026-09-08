<?php

use Magdicom\Exceptions\InvalidProcessorException;
use Magdicom\Exceptions\InvalidRendererException;
use Magdicom\Exceptions\MissingProcessorException;
use Magdicom\Exceptions\MissingRendererException;
use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\Processors\BooleanAndProcessor;
use Magdicom\Processors\BooleanOrProcessor;
use Magdicom\Processors\ConcatenateRenderer;
use Magdicom\Processors\FirstNonNullProcessor;
use Magdicom\Processors\FirstProcessor;
use Magdicom\Processors\FlattenProcessor;
use Magdicom\Processors\LastProcessor;
use Magdicom\Processors\MergeProcessor;
use Magdicom\RegistrationHandle;
use Magdicom\Renderer;
use Magdicom\Resolver;
use Magdicom\Resolvers\NativeResolver;
use Magdicom\ResultProcessor;

test('source namespaces match their psr-4 paths', function () {
    $root = dirname(__DIR__);
    $sourceRoot = $root . '/src';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($sourceRoot) + 1);
        $expectedClass = 'Magdicom\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

        expect(class_exists($expectedClass) || interface_exists($expectedClass))
            ->toBeTrue(sprintf('%s should autoload as %s.', $relativePath, $expectedClass));
    }
});

test('all public classes and interfaces are autoloadable from their stable namespaces', function () {
    $publicTypes = [
        Hooks::class,
        RegistrationHandle::class,
        ProcessingContext::class,
        Resolver::class,
        ResultProcessor::class,
        Renderer::class,
        NativeResolver::class,
        InvalidProcessorException::class,
        InvalidRendererException::class,
        MissingProcessorException::class,
        MissingRendererException::class,
        BooleanAndProcessor::class,
        BooleanOrProcessor::class,
        ConcatenateRenderer::class,
        FirstNonNullProcessor::class,
        FirstProcessor::class,
        FlattenProcessor::class,
        LastProcessor::class,
        MergeProcessor::class,
    ];

    foreach ($publicTypes as $type) {
        expect(class_exists($type) || interface_exists($type))
            ->toBeTrue(sprintf('%s should be autoloadable.', $type));
    }
});

test('hooks exposes the expected public methods without processor-specific shortcuts', function () {
    $methods = get_class_methods(Hooks::class);

    expect($methods)->toContain(
        'addAction',
        'doAction',
        'addFilter',
        'applyFilters',
        'addCollector',
        'collect',
        'setProcessor',
        'hasProcessor',
        'processor',
        'clearProcessor',
        'process',
        'processWith',
        'setRenderer',
        'render',
        'renderWith',
        'has',
        'hasAction',
        'hasFilter',
        'hasCollector',
        'count',
        'listeners',
        'actions',
        'filters',
        'collectors',
        'removeAction',
        'removeFilter',
        'removeCollector',
        'removeAll',
        'removeAllActions',
        'removeAllFilters',
        'removeAllCollectors',
        'debug',
        'setSourceFile',
        'getSourceFile',
    )
        ->and($methods)->not->toContain('first')
        ->and($methods)->not->toContain('last')
        ->and($methods)->not->toContain('firstNonNull')
        ->and($methods)->not->toContain('flatten')
        ->and($methods)->not->toContain('merge')
        ->and($methods)->not->toContain('booleanAnd')
        ->and($methods)->not->toContain('booleanOr')
        ->and($methods)->not->toContain('concatenate');
});

test('old beta namespaces are not retained through aliases or shims', function () {
    $oldTypes = [
        'Magdicom\\NativeResolver',
        'Magdicom\\InvalidProcessorException',
        'Magdicom\\InvalidRendererException',
        'Magdicom\\MissingProcessorException',
        'Magdicom\\MissingRendererException',
        'Magdicom\\Processor\\BooleanAndProcessor',
        'Magdicom\\Processor\\BooleanOrProcessor',
        'Magdicom\\Processor\\ConcatenateRenderer',
        'Magdicom\\Processor\\FirstNonNullProcessor',
        'Magdicom\\Processor\\FirstProcessor',
        'Magdicom\\Processor\\FlattenProcessor',
        'Magdicom\\Processor\\LastProcessor',
        'Magdicom\\Processor\\MergeProcessor',
    ];

    foreach ($oldTypes as $type) {
        expect(class_exists($type) || interface_exists($type))
            ->toBeFalse(sprintf('%s should not be retained.', $type));
    }
});

test('source tree does not recreate obsolete beta structure', function () {
    $root = dirname(__DIR__);

    expect(is_dir($root . '/src/Processor'))->toBeFalse()
        ->and(file_exists($root . '/src/InvalidProcessorException.php'))->toBeFalse()
        ->and(file_exists($root . '/src/InvalidRendererException.php'))->toBeFalse()
        ->and(file_exists($root . '/src/MissingProcessorException.php'))->toBeFalse()
        ->and(file_exists($root . '/src/MissingRendererException.php'))->toBeFalse()
        ->and(file_exists($root . '/src/NativeResolver.php'))->toBeFalse();
});

test('documentation and fixtures do not contain old beta namespace examples', function () {
    $root = dirname(__DIR__);
    $paths = [
        '/AGENTS.md',
        '/README.md',
        '/CHANGELOG.md',
        '/phpstan/fixtures/ConsumerHooksUsage.php',
        '/phpstan/fixtures/ConsumerImplementations.php',
    ];

    foreach ($paths as $path) {
        $contents = file_get_contents($root . $path);

        expect($contents)->not->toContain('Magdicom\\Processor\\')
            ->and($contents)->not->toContain('Magdicom\\NativeResolver')
            ->and($contents)->not->toContain('Magdicom\\InvalidProcessorException')
            ->and($contents)->not->toContain('Magdicom\\InvalidRendererException')
            ->and($contents)->not->toContain('Magdicom\\MissingProcessorException')
            ->and($contents)->not->toContain('Magdicom\\MissingRendererException');
    }
});
