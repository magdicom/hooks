<?php

use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\Processors\BooleanAndProcessor;
use Magdicom\Processors\ConcatenateRenderer;
use Magdicom\Processors\FirstProcessor;
use Magdicom\Processors\FlattenProcessor;
use Magdicom\Processors\MergeProcessor;

test('README processor example stays valid', function () {
    $hooks = new Hooks();

    $hooks->addCollector('dashboard.widgets', fn (): string => 'invoices');
    $hooks->addCollector('dashboard.widgets', fn (): string => 'revenue');

    $hooks->setProcessor('dashboard.widgets', static function (array $results, ProcessingContext $context): string {
        expect($context->hookPoint())->toBe('dashboard.widgets')
            ->and($context->arguments())->toBe([]);

        return implode(', ', $results);
    });

    expect($hooks->collect('dashboard.widgets'))->toBe(['invoices', 'revenue'])
        ->and($hooks->process('dashboard.widgets'))->toBe('invoices, revenue');
});

test('README renderer example stays valid', function () {
    $hooks = new Hooks();

    $hooks->addCollector('dashboard.widgets', fn (): string => 'invoices');
    $hooks->addCollector('dashboard.widgets', fn (): string => 'revenue');

    $hooks->setRenderer('dashboard.widgets', new ConcatenateRenderer());

    expect($hooks->render('dashboard.widgets'))->toBe('invoicesrevenue');

    $hooks->setRenderer('dashboard.widgets', new ConcatenateRenderer(' | '));

    expect($hooks->render('dashboard.widgets'))->toBe('invoices | revenue');

    $hooks->setRenderer('dashboard.widgets', static function (array $results, ProcessingContext $context): string {
        expect($context->hookPoint())->toBe('dashboard.widgets')
            ->and($context->arguments())->toBe([]);

        return implode(', ', $results);
    });

    expect($hooks->render('dashboard.widgets'))->toBe('invoices, revenue');

    $hooks->setProcessor('dashboard.widgets', new FirstProcessor());

    expect($hooks->process('dashboard.widgets'))->toBe('invoices');
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
