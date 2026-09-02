<?php

declare(strict_types=1);

namespace Magdicom;

/**
 * @phpstan-type HookCallbackArray array{0: object|string, 1: string}
 * @phpstan-type HookCallback callable(): mixed|HookCallbackArray
 * @phpstan-type HookData array{id: int, priority: int, callback: callable|HookCallbackArray}
 * @phpstan-type HookPointData array{sorted: bool, data: list<HookData>}
 * @phpstan-type HookType 'legacy'|'action'|'filter'|'collector'
 * @phpstan-type HookRegistries array{
 *     legacy: array<string, HookPointData>,
 *     action: array<string, HookPointData>,
 *     filter: array<string, HookPointData>,
 *     collector: array<string, HookPointData>
 * }
 */
class Hooks
{
    /**
     * @var HookRegistries
     */
    private array $hookPoints = [
        'legacy' => [],
        'action' => [],
        'filter' => [],
        'collector' => [],
    ];

    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];

    private int $nextRegistrationId = 1;

    private ?InvocationResult $activeResult = null;

    private ?InvocationResult $lastResult = null;

    /**
     * @var bool
     */
    private bool $debug = false;

    /**
     * When debug option enabled, the defined function will be called
     * everytime there is an action hook callback function get registered
     * optionally you can use the $this->setSource($filePath) to register
     * the full path to the file holding these callbacks for better debugging
     *
     * @var (callable(string): void)|null
     */
    private mixed $debugCallback = null;

    /**
     * @var string
     */
    private ?string $sourceFile = null;

    /**
     * @param array<string, mixed>|null $parameters
     */
    public function __construct(?array $parameters = [])
    {
        $this->setParameters($parameters ?? []);
    }

    /**
     * @param HookCallback $callback
     * @deprecated Use addAction(), addFilter(), or addCollector() for new code.
     */
    public function register(
        string $hookPoint,
        array|callable $callback,
        int $priority = 1
    ): RegistrationHandle {
        return $this->registerListener('legacy', $hookPoint, $callback, $priority);
    }

    /**
     * @param HookCallback $callback
     */
    public function addAction(
        string $hookPoint,
        array|callable $callback,
        int $priority = 1
    ): RegistrationHandle {
        return $this->registerListener('action', $hookPoint, $callback, $priority);
    }

    /**
     * @param HookCallback $callback
     */
    public function addFilter(
        string $hookPoint,
        array|callable $callback,
        int $priority = 1
    ): RegistrationHandle {
        return $this->registerListener('filter', $hookPoint, $callback, $priority);
    }

    /**
     * @param HookCallback $callback
     */
    public function addCollector(
        string $hookPoint,
        array|callable $callback,
        int $priority = 1
    ): RegistrationHandle {
        return $this->registerListener('collector', $hookPoint, $callback, $priority);
    }

    /**
     * @param array<string, mixed>|object|null $parameters
     * @return $this
     */
    public function doAction(string $hookPoint, array|object|null $parameters = []): self
    {
        $listeners = $this->snapshotListeners('action', $hookPoint);
        $previousLastResult = $this->lastResult;
        $completed = false;

        try {
            foreach ($listeners as $listener) {
                call_user_func_array(
                    $this->prepareCallback($listener['callback']),
                    $this->getParameters($parameters)
                );
            }

            $completed = true;

            return $this;
        } finally {
            $this->lastResult = $completed ? new InvocationResult() : $previousLastResult;
        }
    }

    /**
     * @param array<string, mixed>|object|null $parameters
     */
    public function applyFilters(string $hookPoint, mixed $value, array|object|null $parameters = []): mixed
    {
        $listeners = $this->snapshotListeners('filter', $hookPoint);
        $previousLastResult = $this->lastResult;
        $currentValue = $value;
        $completed = false;

        try {
            foreach ($listeners as $listener) {
                $currentValue = call_user_func_array(
                    $this->prepareCallback($listener['callback']),
                    $this->getFilterParameters($currentValue, $parameters)
                );
            }

            $completed = true;

            return $currentValue;
        } finally {
            $this->lastResult = $completed ? new InvocationResult() : $previousLastResult;
        }
    }

    /**
     * @param array<string, mixed>|object|null $parameters
     * @return list<mixed>
     */
    public function collect(string $hookPoint, array|object|null $parameters = []): array
    {
        $listeners = $this->snapshotListeners('collector', $hookPoint);
        $previousLastResult = $this->lastResult;
        $results = [];
        $completed = false;

        try {
            foreach ($listeners as $listener) {
                $results[] = call_user_func_array(
                    $this->prepareCallback($listener['callback']),
                    $this->getParameters($parameters)
                );
            }

            $completed = true;

            return $results;
        } finally {
            $this->lastResult = $completed ? new InvocationResult() : $previousLastResult;
        }
    }

    /**
     * @param RegistrationHandle|HookCallbackArray|callable|null $listener
     */
    public function has(
        string $hookPoint,
        RegistrationHandle|array|callable|null $listener = null
    ): bool {
        if ($listener === null) {
            return $this->findAnyHookPointData($hookPoint) !== null;
        }

        return $this->findRegistrationLocation($hookPoint, $listener) !== null;
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
     * @param RegistrationHandle|HookCallbackArray|callable $listener
     */
    public function remove(string $hookPoint, RegistrationHandle|array|callable $listener): bool
    {
        $location = $this->findRegistrationLocation($hookPoint, $listener);

        if ($location === null) {
            return false;
        }

        return $this->removeRegistrationById($location['type'], $hookPoint, $location['id']);
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

    /**
     * @return array<int|string, mixed>
     * @deprecated Legacy compatibility API. Prefer doAction(), applyFilters(), or collect().
     */
    public function toArray(): array
    {
        return $this->lastResult?->toArray() ?? [];
    }

    /**
     * @return string
     * @deprecated Legacy compatibility API. Prefer doAction(), applyFilters(), or collect().
     */
    public function toString(?string $separator = ""): string
    {
        return implode(
            $separator ?? '',
            array_map(fn (mixed $output): string => $this->stringifyOutput($output), $this->toArray())
        );
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setParameter(string $name, mixed $value): self
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setParam(string $name, mixed $value): self
    {
        return $this->setParameter($name, $value);
    }

    /**
     * @param array<string, mixed> $parameters
     * @return $this
     */
    public function setParameters(array $parameters = []): self
    {
        # Do Not Merge, Simply Replace
        $this->parameters = array_replace($this->parameters, $parameters);

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return $this
     */
    public function setParams(array $parameters): self
    {
        return $this->setParameters($parameters);
    }

    /**
     * @param array<string, mixed>|object|null $parameters
     * @return $this
     * @deprecated Use doAction(), applyFilters(), or collect() for new code.
     */
    public function all(string $hookPoint, array|object|null $parameters = []): self
    {
        $listeners = $this->snapshotListeners('legacy', $hookPoint);

        if ($listeners === []) {
            $this->completeEmptyInvocation();

            return $this;
        }

        $this->invokeHookPoint(
            'legacy',
            $hookPoint,
            $listeners,
            $parameters,
            'Output-All'
        );

        return $this;
    }

    /**
     * @param array<string, mixed>|object|null $parameters
     * @return $this
     * @deprecated Use doAction(), applyFilters(), or collect() for new code.
     */
    public function first(string $hookPoint, array|object|null $parameters = []): self
    {
        $listeners = $this->snapshotListeners('legacy', $hookPoint);

        if ($listeners === []) {
            $this->completeEmptyInvocation();

            return $this;
        }

        $this->invokeHookPoint(
            'legacy',
            $hookPoint,
            array_slice($listeners, 0, 1),
            $parameters,
            'Output-First'
        );

        return $this;
    }

    /**
     * @param array<string, mixed>|object|null $parameters
     * @return $this
     * @deprecated Use doAction(), applyFilters(), or collect() for new code.
     */
    public function last(string $hookPoint, array|object|null $parameters = []): self
    {
        $listeners = $this->snapshotListeners('legacy', $hookPoint);

        if ($listeners === []) {
            $this->completeEmptyInvocation();

            return $this;
        }

        $this->invokeHookPoint(
            'legacy',
            $hookPoint,
            array_slice($listeners, -1),
            $parameters,
            'Output-Last'
        );

        return $this;
    }

    /**
     * @param array<string, mixed>|object|null $parameters
     * @return array<int, mixed>
     */
    private function getParameters(array|object|null $parameters): array
    {
        if (is_object($parameters)) {
            return [$parameters, $this->parameters];
        }

        return [array_replace_recursive($this->parameters, $parameters ?? [])];
    }

    /**
     * @param array<string, mixed>|object|null $parameters
     * @return array<int, mixed>
     */
    private function getFilterParameters(mixed $value, array|object|null $parameters): array
    {
        if (is_object($parameters)) {
            return [$value, $parameters, $this->parameters];
        }

        return [$value, array_replace_recursive($this->parameters, $parameters ?? [])];
    }

    /**
     * @param callable|HookCallbackArray $callback
     */
    private function prepareCallback(array|callable $callback): callable
    {
        if (is_callable($callback)) {
            return $callback;
        }

        # For Non-Callable, Create an Object
        $instance = new $callback[0]();
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

    private function isCallbackValue(mixed $callback): bool
    {
        return (is_array($callback) || is_callable($callback))
            && $this->isValidCallback($callback);
    }

    /**
     * @return callable|HookCallbackArray|null
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
     * @param list<HookData> $listeners
     * @param array<string, mixed>|object|null $parameters
     * @return $this
     */
    private function invokeHookPoint(
        string $type,
        string $hookPoint,
        array $listeners,
        array|object|null $parameters,
        string $logType
    ): self {
        $result = new InvocationResult();
        $previousActiveResult = $this->activeResult;
        $previousLastResult = $this->lastResult;
        $this->activeResult = $result;
        $completed = false;

        try {
            foreach ($listeners as $listener) {
                $result->add(
                    call_user_func_array(
                        $this->prepareCallback($listener['callback']),
                        $this->getParameters($parameters)
                    )
                );
            }

            $completed = true;

            return $this;
        } finally {
            $this->activeResult = $previousActiveResult;
            $this->lastResult = $completed ? $result : $previousLastResult;

            if ($completed) {
                $this->log($logType, $hookPoint, $type);
            }
        }
    }

    /**
     * @param HookType $type
     * @return $this
     */
    private function sort(string $type, string $hookPoint): self
    {
        # No Need To Resorting
        if ($this->hookPoints[$type][$hookPoint]["sorted"]) {
            return $this;
        }

        # Sort Via Priority
        usort(
            $this->hookPoints[$type][$hookPoint]["data"],
            function (array $i, array $x) {
                $priorityComparison = $i["priority"] <=> $x["priority"];

                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                return $i["id"] <=> $x["id"];
            }
        );

        $this->log("Sort", $hookPoint, $type);

        $this->hookPoints[$type][$hookPoint]["sorted"] = true;

        return $this;
    }

    private function completeEmptyInvocation(): self
    {
        $this->lastResult = new InvocationResult();

        return $this;
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
     * @param RegistrationHandle|HookCallbackArray|callable $listener
     * @return array{type: HookType, index: int, id: int}|null
     */
    private function findRegistrationLocation(
        string $hookPoint,
        RegistrationHandle|array|callable $listener
    ): ?array {
        foreach ($this->hookTypes() as $type) {
            $registry = $this->hookPoints[$type];

            if (! isset($registry[$hookPoint])) {
                continue;
            }

            foreach ($registry[$hookPoint]['data'] as $index => $registeredListener) {
                if ($listener instanceof RegistrationHandle) {
                    if ($listener->type() === $type && $listener->id() === $registeredListener['id']) {
                        return ['type' => $type, 'index' => $index, 'id' => $registeredListener['id']];
                    }

                    continue;
                }

                if ($this->callbacksMatch($registeredListener['callback'], $listener)) {
                    return ['type' => $type, 'index' => $index, 'id' => $registeredListener['id']];
                }
            }
        }

        return null;
    }

    /**
     * @param HookCallbackArray|callable $registered
     * @param HookCallbackArray|callable $candidate
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
     * @return array{legacy: 'legacy', action: 'action', filter: 'filter', collector: 'collector'}
     */
    private function hookTypes(): array
    {
        return [
            'legacy' => 'legacy',
            'action' => 'action',
            'filter' => 'filter',
            'collector' => 'collector',
        ];
    }

    /**
     * @return string
     * @deprecated Legacy compatibility API. Prefer to consume new API return values directly.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Provide a callable function to enable debugging
     * or null to disable it
     * @param callable|null $callback
     * @return $this
     */
    public function debug(callable|null $callback): self
    {
        $this->debug = is_callable($callback);
        $this->debugCallback = $callback;

        return $this;
    }

    /**
     * @param string|null $path
     * @return $this
     */
    public function setSourceFile(?string $path = null): self
    {
        $this->sourceFile = $path;

        $this->log("SourceFile");

        return $this;
    }

    /**
     * @return string
     */
    public function getSourceFile(): string
    {
        return $this->sourceFile ?? "Unknown";
    }

    /**
     * @return $this
     */
    private function log(string $type, mixed ...$data): self
    {
        if (! $this->debug) {
            return $this;
        }

        $message = "";

        switch ($type) {
            case "SourceFile":
                $message = "+ Added Source File: " . $this->getSourceFile();

                break;
            case "Register":
                $callback = $this->normalizeCallback($data[1] ?? null);
                if (! isset($data[0], $data[2]) || $callback === null) {
                    return $this;
                }

                $message = join(PHP_EOL, [
                    '+ Hook Point: ' . $this->stringifyOutput($data[0]) . ', New Callback Defined:',
                    "\t-- Type: " . $this->stringifyOutput($data[3] ?? 'legacy'),
                    "\t-- Source: " . $this->getSourceFile(),
                    "\t-- Callback: " . $this->getCallbackInfo($callback),
                    "\t-- Priority: " . $this->stringifyOutput($data[2]),
                ]);

                break;
            case "Sort":
                $message = '+ Hook Point: ' . $this->stringifyOutput($data[0] ?? '') . ', Callback Functions Sorted For ' . $this->stringifyOutput($data[1] ?? 'legacy') . '!';

                break;
            case "Output-All":
                $message = '+ Hook Point: ' . $this->stringifyOutput($data[0] ?? '') . ', Output Generated For All Callback Functions!';

                break;
            case "Output-First":
                $message = '+ Hook Point: ' . $this->stringifyOutput($data[0] ?? '') . ', Output Generated For The First Callback Function!';

                break;
            case "Output-Last":
                $message = '+ Hook Point: ' . $this->stringifyOutput($data[0] ?? '') . ', Output Generated For The Last Callback Function!';

                break;
        }

        if ($this->debugCallback === null) {
            return $this;
        }

        call_user_func($this->debugCallback, $message);

        return $this;
    }

    /**
     * @param callable|HookCallbackArray $callback
     * @return string
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
