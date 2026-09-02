<?php

declare(strict_types=1);

namespace Magdicom;

class RegistrationHandle
{
    /**
     * @param \Closure(): bool $remover
     */
    public function __construct(
        private readonly Hooks $hooks,
        private readonly string $type,
        private readonly string $hookPoint,
        private readonly int $id,
        private readonly int $priority,
        private readonly \Closure $remover
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function hookPoint(): string
    {
        return $this->hookPoint;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function remove(): bool
    {
        return ($this->remover)();
    }

    public function belongsTo(Hooks $hooks): bool
    {
        return $this->hooks === $hooks;
    }
}
