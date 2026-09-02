<?php

declare(strict_types=1);

namespace Magdicom;

class InvocationResult
{
    /**
     * @var array<int|string, mixed>
     */
    private array $output = [];

    public function add(mixed $output): void
    {
        if (is_array($output) && ! array_is_list($output)) {
            $this->output = array_merge($this->output, $output);

            return;
        }

        if (is_array($output)) {
            foreach ($output as $item) {
                $this->output[] = $item;
            }

            return;
        }

        $this->output[] = $output;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(): array
    {
        return $this->output;
    }
}
