<?php

use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\Processor\ConcatenateRenderer;
use Magdicom\Processor\FirstProcessor;

test('collector callbacks can nest collect process and render safely', function () {
    $hooks = new Hooks();

    $hooks->addCollector('ChildCollect', fn (): string => 'child-collect');
    $hooks->addCollector('ChildProcess', fn (): string => 'child-process');
    $hooks->addCollector('ChildRender', fn (): string => 'child-render');
    $hooks->setProcessor('ChildProcess', new FirstProcessor());
    $hooks->setRenderer('ChildRender', new ConcatenateRenderer());

    $hooks->addCollector('Parent', function () use ($hooks): array {
        return [
            'collect' => $hooks->collect('ChildCollect'),
            'process' => $hooks->process('ChildProcess'),
            'render' => $hooks->render('ChildRender'),
        ];
    });
    $hooks->addCollector('Parent', fn (): string => 'parent');

    expect($hooks->collect('Parent'))->toBe([
        [
            'collect' => ['child-collect'],
            'process' => 'child-process',
            'render' => 'child-render',
        ],
        'parent',
    ]);
});

test('processors can trigger nested collect process and render safely', function () {
    $hooks = new Hooks();

    $hooks->addCollector('NestedCollect', fn (): string => 'nested-collect');
    $hooks->addCollector('NestedProcess', fn (): string => 'nested-process');
    $hooks->addCollector('NestedRender', fn (): string => 'nested-render');
    $hooks->setProcessor('NestedProcess', new FirstProcessor());
    $hooks->setRenderer('NestedRender', new ConcatenateRenderer());

    $hooks->addCollector('OuterProcess', fn (): string => 'outer');
    $hooks->setProcessor('OuterProcess', new class ($hooks) implements \Magdicom\ResultProcessor {
        public function __construct(private Hooks $hooks)
        {
        }

        public function process(array $results, ProcessingContext $context): mixed
        {
            return [
                'hook' => $context->hookPoint(),
                'results' => $results,
                'collect' => $this->hooks->collect('NestedCollect'),
                'process' => $this->hooks->process('NestedProcess'),
                'render' => $this->hooks->render('NestedRender'),
            ];
        }
    });

    expect($hooks->process('OuterProcess'))->toBe([
        'hook' => 'OuterProcess',
        'results' => ['outer'],
        'collect' => ['nested-collect'],
        'process' => 'nested-process',
        'render' => 'nested-render',
    ]);
});

test('rendering remains isolated after nested processor exceptions', function () {
    $hooks = new Hooks();

    $hooks->addCollector('Boom', fn (): string => 'boom');
    $hooks->setProcessor('Boom', new class () implements \Magdicom\ResultProcessor {
        public function process(array $results, ProcessingContext $context): mixed
        {
            throw new RuntimeException('nested processor failed');
        }
    });

    $hooks->addCollector('OuterRender', fn (): string => 'outer');
    $hooks->setRenderer('OuterRender', new class ($hooks) extends ConcatenateRenderer {
        public function __construct(private Hooks $hooks)
        {
            parent::__construct();
        }

        public function process(array $results, ProcessingContext $context): string
        {
            try {
                $this->hooks->process('Boom');
            } catch (RuntimeException) {
            }

            return parent::process($results, $context);
        }
    });

    expect($hooks->render('OuterRender'))->toBe('outer')
        ->and($hooks->render('OuterRender'))->toBe('outer');
});
