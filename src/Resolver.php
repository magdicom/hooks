<?php

declare(strict_types=1);

namespace Magdicom;

interface Resolver
{
    public function resolve(string $className): object;
}
