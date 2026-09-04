<?php

use Magdicom\Hooks;
use Magdicom\NativeResolver;
use Magdicom\Resolver;

test('default construction uses the native resolver for class callbacks', function () {
    $hooks = new Hooks();

    $hooks->addCollector('ResolverDefault', [ResolverTarget::class, 'describe']);

    expect($hooks->collect('ResolverDefault'))->toBe(['native:default']);
});

test('default construction uses the native resolver for action and filter callbacks', function () {
    $hooks = new Hooks();
    ResolverTarget::$events = [];

    $hooks->addAction('ResolverActionDefault', [ResolverTarget::class, 'record']);
    $hooks->addFilter('ResolverFilterDefault', [ResolverTarget::class, 'append']);

    $hooks->doAction('ResolverActionDefault', 'boot');

    expect(ResolverTarget::$events)->toBe(['native:default:boot'])
        ->and($hooks->applyFilters('ResolverFilterDefault', 'value'))->toBe('value:native:default');
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

test('non-static action filter and collector callbacks all resolve through the configured resolver', function () {
    $resolver = new class () implements Resolver {
        public int $calls = 0;

        public function resolve(string $className): object
        {
            expect($className)->toBe(ResolverTarget::class);

            $this->calls++;

            return new ResolverTarget('custom');
        }
    };

    $hooks = new Hooks($resolver);
    ResolverTarget::$events = [];

    $hooks->addAction('ResolverActionCustom', [ResolverTarget::class, 'record']);
    $hooks->addFilter('ResolverFilterCustom', [ResolverTarget::class, 'append']);
    $hooks->addCollector('ResolverCollectorCustom', [ResolverTarget::class, 'describe']);

    $hooks->doAction('ResolverActionCustom', 'boot');

    expect(ResolverTarget::$events)->toBe(['native:custom:boot'])
        ->and($hooks->applyFilters('ResolverFilterCustom', 'value'))->toBe('value:native:custom')
        ->and($hooks->collect('ResolverCollectorCustom'))->toBe(['native:custom'])
        ->and($resolver->calls)->toBe(3);
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
    /**
     * @var list<string>
     */
    public static array $events = [];

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

    public function record(string $event): void
    {
        self::$events[] = 'native:' . $this->value . ':' . $event;
    }

    public function append(string $value): string
    {
        return $value . ':native:' . $this->value;
    }
}
