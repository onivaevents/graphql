<?php

declare(strict_types=1);

namespace Oniva\GraphQL;

interface ResolverGeneratorInterface
{
    /**
     * Should return a map with this structure:
     *
     * return [
     *    ['typeName' => \Resolver\Class\Name]
     * ];
     *
     * @return mixed[]
     */
    public function generate(): array;
}
