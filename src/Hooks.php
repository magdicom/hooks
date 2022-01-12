<?php

declare(strict_types=1);

namespace Magdicom;

class Hooks
{
    /**
     * @var array ["HookPoint" => [
     *              "sorted" => bool,
     *              "data" => [
     *                  ["priority" => int, "callback" => callable],
     *              ]],
     *             "SecondHookPoint" => ...]
     */
    private array $hookPoints;

    /**
     * @var array
     */
    private array $parameters = [];

    /**
     * @var array
     */
    private array $output = [];

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
     * @var mixed
     */
    private mixed $debugCallback = null;

    /**
     * @var string
     */
    private ?string $sourceFile = null;

    /**
     * @param array|null $parameters
     */
    public function __construct(?array $parameters = [])
    {
        $this->setParameters($parameters);
    }

    /**
     * @param string $hookPoint
     * @param array|callable $callback
     * @param int $priority
     * @return $this
     */
    public function register(
        string $hookPoint,
        array|callable $callback,
        int $priority = 1
    ): self {
        # Only Callable
        if (is_callable($callback) == false
            && method_exists($callback[0], $callback[1]) == false) {
            return $this;
        }

        # Need To Be Sorted
        $this->hookPoints[$hookPoint]["sorted"] = false;

        # Add Callback To The List
        $this->hookPoints[$hookPoint]["data"][] = [
            "priority" => $priority,
            "callback" => $callback,
        ];

        $this->log("Register", $hookPoint, $callback, $priority);

        return $this;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->output;
    }

    /**
     * @param string|null $separator
     * @return string
     */
    public function toString(?string $separator = ""): string
    {
        return implode($separator, $this->output);
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
     * @param array $parameters
     * @return $this
     */
    public function setParameters(array $parameters = []): self
    {
        # Do Not Merge, Simply Replace
        $this->parameters = array_replace($this->parameters, $parameters);

        return $this;
    }

    /**
     * @param array $parameters
     * @return $this
     */
    public function setParams(array $parameters): self
    {
        return $this->setParameters($parameters);
    }

    /**
     * @param string $hookPoint
     * @param array|object|null $parameters
     * @return $this
     */
    public function all(string $hookPoint, array|object|null $parameters = []): self
    {
        if ($this->preparedForOutput($hookPoint) == false) {
            return $this;
        }

        foreach ($this->hookPoints[$hookPoint]["data"] as $data) {
            $this->setOutput(
                call_user_func_array(
                    $this->prepareCallback($data["callback"]),
                    $this->getParameters($parameters)
                )
            );
        }

        $this->log("Output-All", $hookPoint);

        return $this;
    }

    /**
     * @param string $hookPoint
     * @param array|object|null $parameters
     * @return $this
     */
    public function first(string $hookPoint, array|object|null $parameters = []): self
    {
        if ($this->preparedForOutput($hookPoint) == false) {
            return $this;
        }

        $this->setOutput(
            call_user_func_array(
                $this->prepareCallback($this->hookPoints[$hookPoint]["data"][
                    array_key_first(
                        $this->hookPoints[$hookPoint]["data"]
                    )]["callback"]),
                $this->getParameters($parameters)
            )
        );

        $this->log("Output-First", $hookPoint);

        return $this;
    }

    /**
     * @param string $hookPoint
     * @param array|object|null $parameters
     * @return $this
     */
    public function last(string $hookPoint, array|object|null $parameters = []): self
    {
        if ($this->preparedForOutput($hookPoint) == false) {
            return $this;
        }

        $this->setOutput(
            call_user_func_array(
                $this->hookPoints[$hookPoint]["data"][array_key_last(
                    $this->hookPoints[$hookPoint]["data"]
                )]["callback"],
                $this->getParameters($parameters)
            )
        );

        $this->log("Output-Last", $hookPoint);

        return $this;
    }

    /**
     * @param array|object|null $parameters
     * @return array|object
     */
    private function getParameters(array|object|null $parameters): array|object
    {
        return is_object($parameters) ? [$parameters, $this->parameters] : [array_replace_recursive($this->parameters, $parameters)];
    }

    /**
     * @param array|callable $callback
     * @return array|callable
     */
    private function prepareCallback(array|callable $callback): array|callable
    {
        if (is_callable($callback)) {
            return $callback;
        }

        # For Non-Callable, Create an Object
        return [(new $callback[0]()), $callback[1]];
    }

    /**
     * @param string $hookPoint
     * @return bool
     */
    private function preparedForOutput(string $hookPoint): bool
    {
        # Empty Output Prop.
        $this->resetOutput();

        # No Callback Functions Registered
        if (isset($this->hookPoints[$hookPoint]) == false) {
            return false;
        }

        # Sort Callback By Priority
        $this->sort($hookPoint);

        return true;
    }

    /**
     * @param string $hookPoint
     * @return $this
     */
    private function sort(string $hookPoint): self
    {

        # No Need To Resorting
        if ($this->hookPoints[$hookPoint]["sorted"]) {
            return $this;
        }

        # Sort Via Priority
        usort(
            $this->hookPoints[$hookPoint]["data"],
            function (array $i, array $x) {
                return $i["priority"] <=> $x["priority"];
            }
        );

        $this->log("Sort", $hookPoint);

        $this->hookPoints[$hookPoint]["sorted"] = true;

        return $this;
    }

    /**
     * @param mixed $output
     * @return $this
     */
    private function setOutput(mixed $output): self
    {
        if (is_array($output)) {
            $this->output = array_merge($this->output, $output);
        } else {
            $this->output[] = $output;
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function resetOutput(): self
    {
        # Todo: Need better way to empty/reinitialize the output array
        $this->output = [];

        return $this;
    }

    /**
     * @return string
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
     * @param string $type
     * @param mixed $data
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
                $message = join(PHP_EOL, [
                    "+ Hook Point: " . $data[0] . ", New Callback Defined:",
                    "\t-- Source: " . $this->getSourceFile(),
                    "\t-- Callback: " . $this->getCallbackInfo($data[1]),
                    "\t-- Priority: " . $data[2],
                ]);

                break;
            case "Sort":
                $message = "+ Hook Point: " . $data[0] . ", Callback Functions Sorted!";

                break;
            case "Output-All":
                $message = "+ Hook Point: " . $data[0] . ", Output Generated For All Callback Functions!";

                break;
            case "Output-First":
                $message = "+ Hook Point: " . $data[0] . ", Output Generated For The First Callback Function!";

                break;
            case "Output-Last":
                $message = "+ Hook Point: " . $data[0] . ", Output Generated For The Last Callback Function!";

                break;
        }

        call_user_func($this->debugCallback, $message);

        return $this;
    }

    /**
     * @param array|callable $callback
     * @return string
     * @throws \ReflectionException
     */
    private function getCallbackInfo(array|callable $callback): string
    {
        if (is_array($callback)) {
            if (is_object($callback[0])) {
                return (new \ReflectionClass($callback[0]))->getName() . "::" . $callback[1];
            }

            return $callback[0] . "::" . $callback[1];
        }

        if (is_string($callback)) {
            return $callback;
        }

        return (new \ReflectionFunction($callback))->getName();
    }
}
