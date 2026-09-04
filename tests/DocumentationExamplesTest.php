<?php

use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\Processor\BooleanAndProcessor;
use Magdicom\Processor\ConcatenateRenderer;
use Magdicom\Processor\FirstProcessor;
use Magdicom\Processor\FlattenProcessor;
use Magdicom\Processor\MergeProcessor;

test('README processor example stays valid', function () {
    $hooks = new Hooks();

    $hooks->addCollector('report', fn (): string => 'first');
    $hooks->addCollector('report', fn (): string => 'second');

    $hooks->setProcessor('report', static function (array $results, ProcessingContext $context): string {
        expect($context->hookPoint())->toBe('report')
            ->and($context->arguments())->toBe([]);

        return implode(', ', $results);
    });

    expect($hooks->collect('report'))->toBe(['first', 'second'])
        ->and($hooks->process('report'))->toBe('first, second');
});

test('README renderer example stays valid', function () {
    $hooks = new Hooks();

    $hooks->addCollector('report', fn (): string => 'first');
    $hooks->addCollector('report', fn (): string => 'second');

    $hooks->setRenderer('report', new ConcatenateRenderer());

    expect($hooks->render('report'))->toBe('firstsecond');

    $hooks->setRenderer('report', new ConcatenateRenderer(' | '));

    expect($hooks->render('report'))->toBe('first | second');

    $hooks->setRenderer('report', static function (array $results, ProcessingContext $context): string {
        expect($context->hookPoint())->toBe('report')
            ->and($context->arguments())->toBe([]);

        return implode(', ', $results);
    });

    expect($hooks->render('report'))->toBe('first, second');

    $hooks->setProcessor('report', new FirstProcessor());

    expect($hooks->process('report'))->toBe('first');
});

test('README flatten processor example stays valid', function () {
    $hooks = new Hooks();

    $hooks->addCollector('navigation', fn (): array => ['home', ['about', 'team']]);
    $hooks->addCollector('navigation', fn (): array => ['contact']);

    $hooks->setProcessor('navigation', new FlattenProcessor());

    expect($hooks->process('navigation'))->toBe(['home', 'about', 'team', 'contact']);
});

test('README merge processor example stays valid', function () {
    $hooks = new Hooks();

    $hooks->addCollector('configuration', fn (): array => ['enabled' => true, 'paths' => ['one']]);
    $hooks->addCollector('configuration', fn (): array => ['paths' => ['two'], 'mode' => 'strict']);

    $hooks->setProcessor('configuration', new MergeProcessor());

    expect($hooks->process('configuration'))->toBe([
        'enabled' => true,
        'paths' => ['two'],
        'mode' => 'strict',
    ]);
});

test('README boolean processor example stays valid', function () {
    $hooks = new Hooks();

    $hooks->addCollector('requirements', fn (): bool => true);
    $hooks->addCollector('requirements', fn (): bool => true);

    $hooks->setProcessor('requirements', new BooleanAndProcessor());

    expect($hooks->process('requirements'))->toBeTrue();
});
