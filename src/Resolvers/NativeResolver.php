<?php

declare(strict_types=1);

namespace Magdicom\Resolvers;

use Magdicom\Resolver;

class NativeResolver implements Resolver
{
    public function resolve(string $className): object
    {
        return new $className();
    }
}
