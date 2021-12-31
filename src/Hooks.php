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
     * @param array|null $parameters
     * @return $this
     */
    public function all(string $hookPoint, ?array $parameters = []): self
    {
        $this->prepareForOutput($hookPoint);

        foreach ($this->hookPoints[$hookPoint]["data"] as $data) {
            $this->setOutput(
                call_user_func(
                    $this->prepareCallback($data["callback"]),
                    $this->getParameters($parameters)
                )
            );
        }

        return $this;
    }

    /**
     * @param string $hookPoint
     * @param array|null $parameters
     * @return $this
     */
    public function first(string $hookPoint, ?array $parameters = []): self
    {
        $this->prepareForOutput($hookPoint);

        $this->setOutput(
            call_user_func(
                $this->prepareCallback($this->hookPoints[$hookPoint]["data"][
                    array_key_first(
                        $this->hookPoints[$hookPoint]["data"]
                    )]["callback"]),
                $this->getParameters($parameters)
            )
        );

        return $this;
    }

    /**
     * @param string $hookPoint
     * @param array|null $parameters
     * @return $this
     */
    public function last(string $hookPoint, ?array $parameters = []): self
    {
        $this->prepareForOutput($hookPoint);

        $this->setOutput(call_user_func(
            $this->hookPoints[$hookPoint]["data"][array_key_last(
                $this->hookPoints[$hookPoint]["data"]
            )]["callback"],
            $this->getParameters($parameters)
        ));

        return $this;
    }

    /**
     * @param array $parameters
     * @return array
     */
    private function getParameters(array $parameters): array
    {
        return array_replace_recursive($this->parameters, $parameters);
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
     * @return $this
     */
    private function prepareForOutput(string $hookPoint): self
    {
        # Empty Output Prop.
        $this->resetOutput();

        # No Callback Functions Registered
        if (isset($this->hookPoints[$hookPoint]) == false) {
            return $this;
        }

        # Sort Callback By Priority
        return $this->sort($hookPoint);
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
}
