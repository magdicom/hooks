<?php

declare(strict_types=1);

namespace Magdicom;

final class ProcessingContext
{
    /**
     * @var list<mixed>
     */
    private array $arguments;

    public function __construct(
        private string $hookPoint,
        mixed ...$arguments
    ) {
        $this->arguments = array_values($arguments);
    }

    public function hookPoint(): string
    {
        return $this->hookPoint;
    }

    /**
     * @return list<mixed>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }
}
