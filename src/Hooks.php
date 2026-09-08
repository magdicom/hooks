<?php

declare(strict_types=1);

namespace Magdicom;

use Magdicom\Exceptions\InvalidProcessorException;
use Magdicom\Exceptions\InvalidRendererException;
use Magdicom\Exceptions\MissingProcessorException;
use Magdicom\Exceptions\MissingRendererException;
use Magdicom\Resolvers\NativeResolver;

/**
 * @phpstan-type HookCallbackArray array{0: object|string, 1: string}
 * @phpstan-type HookCallable callable(mixed...): mixed
 * @phpstan-type HookCallback HookCallable|HookCallbackArray
 * @phpstan-type ProcessorCallable callable(list<mixed>, ProcessingContext): mixed
 * @phpstan-type RendererCallable callable(list<mixed>, ProcessingContext): string
 * @phpstan-type ProcessorReference ResultProcessor<mixed, mixed>|ProcessorCallable|class-string<ResultProcessor<mixed, mixed>>
 * @phpstan-type RendererReference Renderer<mixed>|RendererCallable|class-string<Renderer<mixed>>
 * @phpstan-type HookData array{id: int, priority: int, callback: HookCallback}
 * @phpstan-type HookPointData array{sorted: bool, data: list<HookData>}
 * @phpstan-type HookType 'action'|'filter'|'collector'
 * @phpstan-type HookRegistries array{
 *     action: array<string, HookPointData>,
 *     filter: array<string, HookPointData>,
 *     collector: array<string, HookPointData>
 * }
 */
class Hooks
{
    private Resolver $resolver;

    /**
     * @var array<string, ProcessorReference>
     */
    private array $processors = [];

    /**
     * @var HookRegistries
     */
    private array $hookPoints = [
        'action' => [],
        'filter' => [],
        'collector' => [],
    ];

    private int $nextRegistrationId = 1;

    private bool $debug = false;

    /**
     * @var (callable(string): void)|null
     */
    private mixed $debugCallback = null;

    private ?string $sourceFile = null;

    public function __construct(?Resolver $resolver = null)
    {
        $this->resolver = $resolver ?? new NativeResolver();
    }

    /**
     * @param HookCallback $callback
     */
    public function addAction(
        string $hookPoint,
        array|callable $callback,
        int $priority = 10
    ): RegistrationHandle {
        return $this->registerListener('action', $hookPoint, $callback, $priority);
    }

    /**
     * @param HookCallback $callback
     */
    public function addFilter(
        string $hookPoint,
        array|callable $callback,
        int $priority = 10
    ): RegistrationHandle {
        return $this->registerListener('filter', $hookPoint, $callback, $priority);
    }

    /**
     * @param HookCallback $callback
     */
    public function addCollector(
        string $hookPoint,
        array|callable $callback,
        int $priority = 10
    ): RegistrationHandle {
        return $this->registerListener('collector', $hookPoint, $callback, $priority);
    }

    public function doAction(string $hookPoint, mixed ...$arguments): void
    {
        foreach ($this->snapshotListeners('action', $hookPoint) as $listener) {
            call_user_func_array($this->prepareCallback($listener['callback']), $arguments);
        }
    }

    public function applyFilters(string $hookPoint, mixed $value, mixed ...$arguments): mixed
    {
        $currentValue = $value;

        foreach ($this->snapshotListeners('filter', $hookPoint) as $listener) {
            $currentValue = call_user_func_array(
                $this->prepareCallback($listener['callback']),
                [$currentValue, ...$arguments]
            );
        }

        return $currentValue;
    }

    /**
     * @return list<mixed>
     */
    public function collect(string $hookPoint, mixed ...$arguments): array
    {
        $results = [];

        foreach ($this->snapshotListeners('collector', $hookPoint) as $listener) {
            $results[] = call_user_func_array($this->prepareCallback($listener['callback']), $arguments);
        }

        return $results;
    }

    /**
     * @param ProcessorReference $processor
     * @return $this
     */
    public function setProcessor(string $hookPoint, ResultProcessor|callable|string $processor): self
    {
        $this->processors[$hookPoint] = $processor;

        return $this;
    }

    public function hasProcessor(string $hookPoint): bool
    {
        return array_key_exists($hookPoint, $this->processors);
    }

    /**
     * @return ProcessorReference|null
     */
    public function processor(string $hookPoint): ResultProcessor|callable|string|null
    {
        return $this->processors[$hookPoint] ?? null;
    }

    public function clearProcessor(string $hookPoint): bool
    {
        if (! array_key_exists($hookPoint, $this->processors)) {
            return false;
        }

        unset($this->processors[$hookPoint]);

        return true;
    }

    /**
     * @param RendererReference $renderer
     * @return $this
     */
    public function setRenderer(string $hookPoint, Renderer|callable|string $renderer): self
    {
        return $this->setProcessor($hookPoint, $renderer);
    }

    public function process(string $hookPoint, mixed ...$arguments): mixed
    {
        $processor = $this->processor($hookPoint);

        if ($processor === null) {
            throw MissingProcessorException::forHookPoint($hookPoint);
        }

        return $this->invokeProcessor(
            $processor,
            $this->collect($hookPoint, ...$arguments),
            new ProcessingContext($hookPoint, ...$arguments)
        );
    }

    public function render(string $hookPoint, mixed ...$arguments): string
    {
        $processor = $this->processor($hookPoint);

        if ($processor === null) {
            throw MissingRendererException::forHookPoint($hookPoint);
        }

        return $this->invokeRenderer(
            $processor,
            $this->collect($hookPoint, ...$arguments),
            new ProcessingContext($hookPoint, ...$arguments)
        );
    }

    /**
     * @param ProcessorReference $processor
     */
    public function processWith(
        string $hookPoint,
        ResultProcessor|callable|string $processor,
        mixed ...$arguments,
    ): mixed {
        return $this->invokeProcessor(
            $processor,
            $this->collect($hookPoint, ...$arguments),
            new ProcessingContext($hookPoint, ...$arguments)
        );
    }

    /**
     * @param RendererReference $renderer
     */
    public function renderWith(
        string $hookPoint,
        Renderer|callable|string $renderer,
        mixed ...$arguments,
    ): string {
        return $this->invokeRenderer(
            $renderer,
            $this->collect($hookPoint, ...$arguments),
            new ProcessingContext($hookPoint, ...$arguments)
        );
    }

    public function has(string $hookPoint): bool
    {
        return $this->findAnyHookPointData($hookPoint) !== null;
    }

    /**
     * @param HookCallback|null $callback
     */
    public function hasAction(
        string $hookPoint,
        array|callable|null $callback = null,
        int $priority = 10
    ): bool {
        return $this->hasListener('action', $hookPoint, $callback, $priority);
    }

    /**
     * @param HookCallback|null $callback
     */
    public function hasFilter(
        string $hookPoint,
        array|callable|null $callback = null,
        int $priority = 10
    ): bool {
        return $this->hasListener('filter', $hookPoint, $callback, $priority);
    }

    /**
     * @param HookCallback|null $callback
     */
    public function hasCollector(
        string $hookPoint,
        array|callable|null $callback = null,
        int $priority = 10
    ): bool {
        return $this->hasListener('collector', $hookPoint, $callback, $priority);
    }

    public function count(?string $hookPoint = null): int
    {
        if ($hookPoint !== null) {
            $count = 0;
            foreach ($this->hookPoints as $registry) {
                $count += count($registry[$hookPoint]['data'] ?? []);
            }

            return $count;
        }

        return array_sum(array_map(
            static fn (array $registry): int => array_sum(array_map(
                static fn (array $hookPointData): int => count($hookPointData['data']),
                $registry
            )),
            $this->hookPoints
        ));
    }

    /**
     * @return list<RegistrationHandle>
     */
    public function listeners(string $hookPoint): array
    {
        $listeners = [];

        foreach ($this->hookTypes() as $type) {
            $listeners = [
                ...$listeners,
                ...array_map(
                    fn (array $listener): RegistrationHandle => $this->createHandleFromRegistration($type, $hookPoint, $listener),
                    $this->snapshotListeners($type, $hookPoint)
                ),
            ];
        }

        usort(
            $listeners,
            static function (RegistrationHandle $left, RegistrationHandle $right): int {
                $priorityComparison = $left->priority() <=> $right->priority();

                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                return $left->id() <=> $right->id();
            }
        );

        return $listeners;
    }

    /**
     * @return list<RegistrationHandle>
     */
    public function actions(string $hookPoint): array
    {
        return $this->typedListeners('action', $hookPoint);
    }

    /**
     * @return list<RegistrationHandle>
     */
    public function filters(string $hookPoint): array
    {
        return $this->typedListeners('filter', $hookPoint);
    }

    /**
     * @return list<RegistrationHandle>
     */
    public function collectors(string $hookPoint): array
    {
        return $this->typedListeners('collector', $hookPoint);
    }

    /**
     * @param HookCallback $callback
     */
    public function removeAction(string $hookPoint, array|callable $callback, int $priority = 10): bool
    {
        return $this->removeListener('action', $hookPoint, $callback, $priority);
    }

    /**
     * @param HookCallback $callback
     */
    public function removeFilter(string $hookPoint, array|callable $callback, int $priority = 10): bool
    {
        return $this->removeListener('filter', $hookPoint, $callback, $priority);
    }

    /**
     * @param HookCallback $callback
     */
    public function removeCollector(string $hookPoint, array|callable $callback, int $priority = 10): bool
    {
        return $this->removeListener('collector', $hookPoint, $callback, $priority);
    }

    public function removeAll(?string $hookPoint = null): int
    {
        if ($hookPoint !== null) {
            $removedCount = 0;
            foreach ($this->hookTypes() as $type) {
                $removedCount += count($this->hookPoints[$type][$hookPoint]['data'] ?? []);
                unset($this->hookPoints[$type][$hookPoint]);
            }

            return $removedCount;
        }

        $removedCount = $this->count();
        foreach ($this->hookTypes() as $type) {
            $this->hookPoints[$type] = [];
        }

        return $removedCount;
    }

    public function removeAllActions(?string $hookPoint = null): int
    {
        return $this->removeAllForType('action', $hookPoint);
    }

    public function removeAllFilters(?string $hookPoint = null): int
    {
        return $this->removeAllForType('filter', $hookPoint);
    }

    public function removeAllCollectors(?string $hookPoint = null): int
    {
        return $this->removeAllForType('collector', $hookPoint);
    }

    /**
     * @param ProcessorReference $processor
     * @param list<mixed> $results
     */
    private function invokeProcessor(
        ResultProcessor|callable|string $processor,
        array $results,
        ProcessingContext $context
    ): mixed {
        if ($processor instanceof ResultProcessor) {
            return $processor->process($results, $context);
        }

        if (is_string($processor) && ! is_callable($processor)) {
            $resolved = $this->resolver->resolve($processor);

            if (! $resolved instanceof ResultProcessor) {
                throw InvalidProcessorException::forResolvedClass($processor);
            }

            return $resolved->process($results, $context);
        }

        return $processor($results, $context);
    }

    /**
     * @param ProcessorReference $processor
     * @param list<mixed> $results
     */
    private function invokeRenderer(
        ResultProcessor|callable|string $processor,
        array $results,
        ProcessingContext $context
    ): string {
        if ($processor instanceof Renderer) {
            return $processor->process($results, $context);
        }

        if (is_string($processor) && ! is_callable($processor)) {
            $resolved = $this->resolver->resolve($processor);

            if (! $resolved instanceof Renderer) {
                if ($resolved instanceof ResultProcessor) {
                    throw InvalidRendererException::forHookPoint($context->hookPoint());
                }

                throw InvalidRendererException::forResolvedClass($processor);
            }

            return $resolved->process($results, $context);
        }

        if (! is_callable($processor)) {
            throw InvalidRendererException::forHookPoint($context->hookPoint());
        }

        $result = $processor($results, $context);

        if (! is_string($result)) {
            throw InvalidRendererException::forReturnedType($context->hookPoint(), $result);
        }

        return $result;
    }

    /**
     * @param HookCallback $callback
     */
    private function prepareCallback(array|callable $callback): callable
    {
        if (is_callable($callback)) {
            return $callback;
        }

        $instance = is_string($callback[0])
            ? $this->resolver->resolve($callback[0])
            : $callback[0];
        $method = $callback[1];

        return static fn (...$arguments) => $instance->$method(...$arguments);
    }

    /**
     * @param array<mixed>|callable $callback
     */
    private function isValidCallback(array|callable $callback): bool
    {
        if (is_callable($callback)) {
            return true;
        }

        return isset($callback[0], $callback[1])
            && (is_object($callback[0]) || is_string($callback[0]))
            && is_string($callback[1])
            && method_exists($callback[0], $callback[1]);
    }

    /**
     * @return HookCallback|null
     */
    private function normalizeCallback(mixed $callback): array|callable|null
    {
        if (is_callable($callback)) {
            return $callback;
        }

        if (! is_array($callback)
            || ! isset($callback[0], $callback[1])
            || (! is_object($callback[0]) && ! is_string($callback[0]))
            || ! is_string($callback[1])
            || ! method_exists($callback[0], $callback[1])) {
            return null;
        }

        return [$callback[0], $callback[1]];
    }

    /**
     * @param HookType $type
     * @param HookData $listener
     */
    private function createHandleFromRegistration(string $type, string $hookPoint, array $listener): RegistrationHandle
    {
        return new RegistrationHandle(
            $this,
            $type,
            $hookPoint,
            $listener['id'],
            $listener['priority'],
            fn (): bool => $this->removeRegistrationById($type, $hookPoint, $listener['id'])
        );
    }

    /**
     * @param HookType $type
     * @param HookCallback|null $callback
     */
    private function hasListener(
        string $type,
        string $hookPoint,
        array|callable|null $callback = null,
        int $priority = 10
    ): bool {
        if ($callback === null) {
            return isset($this->hookPoints[$type][$hookPoint]) && $this->hookPoints[$type][$hookPoint]['data'] !== [];
        }

        return $this->findCallbackRegistrationLocation($type, $hookPoint, $callback, $priority) !== null;
    }

    /**
     * @param HookType $type
     * @return list<RegistrationHandle>
     */
    private function typedListeners(string $type, string $hookPoint): array
    {
        return array_map(
            fn (array $listener): RegistrationHandle => $this->createHandleFromRegistration($type, $hookPoint, $listener),
            $this->snapshotListeners($type, $hookPoint)
        );
    }

    /**
     * @param HookType $type
     * @param HookCallback $callback
     */
    private function removeListener(string $type, string $hookPoint, array|callable $callback, int $priority): bool
    {
        $location = $this->findCallbackRegistrationLocation($type, $hookPoint, $callback, $priority);

        if ($location === null) {
            return false;
        }

        return $this->removeRegistrationById($location['type'], $hookPoint, $location['id']);
    }

    /**
     * @param HookType $type
     */
    private function removeAllForType(string $type, ?string $hookPoint = null): int
    {
        if ($hookPoint !== null) {
            $removedCount = count($this->hookPoints[$type][$hookPoint]['data'] ?? []);
            unset($this->hookPoints[$type][$hookPoint]);

            return $removedCount;
        }

        $removedCount = array_sum(array_map(
            static fn (array $hookPointData): int => count($hookPointData['data']),
            $this->hookPoints[$type]
        ));

        $this->hookPoints[$type] = [];

        return $removedCount;
    }

    /**
     * @param HookType $type
     * @param HookCallback $callback
     * @return array{type: HookType, index: int, id: int}|null
     */
    private function findCallbackRegistrationLocation(
        string $type,
        string $hookPoint,
        array|callable $callback,
        int $priority
    ): ?array {
        $registry = $this->hookPoints[$type];

        if (! isset($registry[$hookPoint])) {
            return null;
        }

        foreach ($registry[$hookPoint]['data'] as $index => $registeredListener) {
            if ($registeredListener['priority'] === $priority
                && $this->callbacksMatch($registeredListener['callback'], $callback)) {
                return ['type' => $type, 'index' => $index, 'id' => $registeredListener['id']];
            }
        }

        return null;
    }

    /**
     * @param HookCallback $registered
     * @param HookCallback $candidate
     */
    private function callbacksMatch(array|callable $registered, array|callable $candidate): bool
    {
        $normalizedRegistered = $this->normalizeCallback($registered);
        $normalizedCandidate = $this->normalizeCallback($candidate);

        if ($normalizedRegistered === null || $normalizedCandidate === null) {
            return false;
        }

        if (is_array($normalizedRegistered) && is_array($normalizedCandidate)) {
            return $normalizedRegistered[0] === $normalizedCandidate[0]
                && $normalizedRegistered[1] === $normalizedCandidate[1];
        }

        if (is_callable($normalizedRegistered) && is_callable($normalizedCandidate)) {
            return $normalizedRegistered === $normalizedCandidate;
        }

        return false;
    }

    /**
     * @param HookType $type
     */
    private function removeRegistrationById(string $type, string $hookPoint, int $registrationId): bool
    {
        if (! isset($this->hookPoints[$type][$hookPoint])) {
            return false;
        }

        $originalCount = count($this->hookPoints[$type][$hookPoint]['data']);
        $this->hookPoints[$type][$hookPoint]['data'] = array_values(array_filter(
            $this->hookPoints[$type][$hookPoint]['data'],
            static fn (array $listener): bool => $listener['id'] !== $registrationId
        ));

        if (count($this->hookPoints[$type][$hookPoint]['data']) === $originalCount) {
            return false;
        }

        if ($this->hookPoints[$type][$hookPoint]['data'] === []) {
            unset($this->hookPoints[$type][$hookPoint]);

            return true;
        }

        $this->hookPoints[$type][$hookPoint]['sorted'] = false;

        return true;
    }

    /**
     * @param HookType $type
     * @param HookCallback $callback
     */
    private function registerListener(string $type, string $hookPoint, array|callable $callback, int $priority): RegistrationHandle
    {
        if (! $this->isValidCallback($callback)) {
            throw new \InvalidArgumentException('The provided callback is not valid.');
        }

        $this->hookPoints[$type][$hookPoint] ??= [
            'sorted' => true,
            'data' => [],
        ];

        $registrationId = $this->nextRegistrationId++;
        $this->hookPoints[$type][$hookPoint]['sorted'] = false;
        $this->hookPoints[$type][$hookPoint]['data'][] = [
            'id' => $registrationId,
            'priority' => $priority,
            'callback' => $callback,
        ];

        $this->log('Register', $hookPoint, $callback, $priority, $type);

        return $this->createHandleFromRegistration($type, $hookPoint, [
            'id' => $registrationId,
            'priority' => $priority,
            'callback' => $callback,
        ]);
    }

    /**
     * @param HookType $type
     * @return list<HookData>
     */
    private function getListeners(string $type, string $hookPoint): array
    {
        if (! isset($this->hookPoints[$type][$hookPoint])) {
            return [];
        }

        $this->sort($type, $hookPoint);

        return $this->hookPoints[$type][$hookPoint]['data'];
    }

    /**
     * @param HookType $type
     * @return $this
     */
    private function sort(string $type, string $hookPoint): self
    {
        if ($this->hookPoints[$type][$hookPoint]['sorted']) {
            return $this;
        }

        usort(
            $this->hookPoints[$type][$hookPoint]['data'],
            static function (array $left, array $right): int {
                $priorityComparison = $left['priority'] <=> $right['priority'];

                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                return $left['id'] <=> $right['id'];
            }
        );

        $this->log('Sort', $hookPoint, $type);

        $this->hookPoints[$type][$hookPoint]['sorted'] = true;

        return $this;
    }

    /**
     * @param HookType $type
     * @return list<HookData>
     */
    private function snapshotListeners(string $type, string $hookPoint): array
    {
        return $this->getListeners($type, $hookPoint);
    }

    /**
     * @return HookPointData|null
     */
    private function findAnyHookPointData(string $hookPoint): ?array
    {
        foreach ($this->hookTypes() as $type) {
            $registry = $this->hookPoints[$type];

            if (isset($registry[$hookPoint]) && $registry[$hookPoint]['data'] !== []) {
                return $registry[$hookPoint];
            }
        }

        return null;
    }

    /**
     * @return array{action: 'action', filter: 'filter', collector: 'collector'}
     */
    private function hookTypes(): array
    {
        return [
            'action' => 'action',
            'filter' => 'filter',
            'collector' => 'collector',
        ];
    }

    /**
     * @return $this
     */
    public function debug(callable|null $callback): self
    {
        $this->debug = is_callable($callback);
        $this->debugCallback = $callback;

        return $this;
    }

    /**
     * @return $this
     */
    public function setSourceFile(?string $path = null): self
    {
        $this->sourceFile = $path;

        $this->log('SourceFile');

        return $this;
    }

    public function getSourceFile(): string
    {
        return $this->sourceFile ?? 'Unknown';
    }

    /**
     * @return $this
     */
    private function log(string $type, mixed ...$data): self
    {
        if (! $this->debug) {
            return $this;
        }

        $message = '';

        switch ($type) {
            case 'SourceFile':
                $message = '+ Added Source File: ' . $this->getSourceFile();

                break;
            case 'Register':
                $callback = $this->normalizeCallback($data[1] ?? null);
                if (! isset($data[0], $data[2]) || $callback === null) {
                    return $this;
                }

                $message = join(PHP_EOL, [
                    '+ Hook Point: ' . $this->stringifyOutput($data[0]) . ', New Callback Defined:',
                    "\t-- Type: " . $this->stringifyOutput($data[3] ?? 'action'),
                    "\t-- Source: " . $this->getSourceFile(),
                    "\t-- Callback: " . $this->getCallbackInfo($callback),
                    "\t-- Priority: " . $this->stringifyOutput($data[2]),
                ]);

                break;
            case 'Sort':
                $message = '+ Hook Point: ' . $this->stringifyOutput($data[0] ?? '') . ', Callback Functions Sorted For ' . $this->stringifyOutput($data[1] ?? 'action') . '!';

                break;
        }

        if ($message === '' || $this->debugCallback === null) {
            return $this;
        }

        call_user_func($this->debugCallback, $message);

        return $this;
    }

    /**
     * @param HookCallback $callback
     * @throws \ReflectionException
     */
    private function getCallbackInfo(array|callable $callback): string
    {
        if (is_array($callback)) {
            if (is_object($callback[0])) {
                return (new \ReflectionClass($callback[0]))->getName() . '::' . $callback[1];
            }

            return $callback[0] . '::' . $callback[1];
        }

        if (is_string($callback)) {
            return $callback;
        }

        if (is_object($callback) && ! $callback instanceof \Closure) {
            return $callback::class;
        }

        if ($callback instanceof \Closure) {
            return (new \ReflectionFunction($callback))->getName();
        }

        return 'callable';
    }

    private function stringifyOutput(mixed $output): string
    {
        if ($output === null || is_scalar($output)) {
            return (string) $output;
        }

        if (is_object($output) && method_exists($output, '__toString')) {
            return (string) $output;
        }

        if (is_array($output)) {
            $encoded = json_encode($output);

            return $encoded === false ? 'Array' : $encoded;
        }

        return get_debug_type($output);
    }
}
