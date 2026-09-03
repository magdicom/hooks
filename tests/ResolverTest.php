<?php

use Magdicom\Hooks;
use Magdicom\NativeResolver;
use Magdicom\Resolver;

test('default construction uses the native resolver for class callbacks', function () {
    $hooks = new Hooks();

    $hooks->addCollector('ResolverDefault', [ResolverTarget::class, 'describe']);

    expect($hooks->collect('ResolverDefault'))->toBe(['native:default']);
});

test('hooks accepts explicit native resolver injection', function () {
    $hooks = new Hooks(new NativeResolver());

    $hooks->addCollector('ResolverInjected', [ResolverTarget::class, 'describe']);

    expect($hooks->collect('ResolverInjected'))->toBe(['native:default']);
});

test('non-static class callbacks resolve through the configured resolver', function () {
    $hooks = new Hooks(new class () implements Resolver {
        public function resolve(string $className): object
        {
            expect($className)->toBe(ResolverTarget::class);

            return new ResolverTarget('custom');
        }
    });

    $hooks->addCollector('ResolverCustom', [ResolverTarget::class, 'describe']);

    expect($hooks->collect('ResolverCustom'))->toBe(['native:custom']);
});

test('static callbacks continue to bypass instance resolution', function () {
    $resolver = new class () implements Resolver {
        public bool $resolved = false;

        public function resolve(string $className): object
        {
            $this->resolved = true;

            return new ResolverTarget('unexpected');
        }
    };

    $hooks = new Hooks($resolver);

    $hooks->addCollector('ResolverStatic', [ResolverTarget::class, 'staticDescribe']);

    expect($hooks->collect('ResolverStatic'))->toBe(['static'])
        ->and($resolver->resolved)->toBeFalse();
});

class ResolverTarget
{
    public function __construct(private string $value = 'default')
    {
    }

    public function describe(): string
    {
        return 'native:' . $this->value;
    }

    public static function staticDescribe(): string
    {
        return 'static';
    }
}
