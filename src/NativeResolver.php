<?php

declare(strict_types=1);

namespace Magdicom;

class NativeResolver implements Resolver
{
    public function resolve(string $className): object
    {
        return new $className();
    }
}
