<?php

declare(strict_types=1);

namespace Oniva\GraphQL\Tests\Functional\Directive\Fixtures;

use Neos\Flow\Annotations as Flow;
use Oniva\GraphQL\ResolverInterface;

/**
 * @Flow\Scope("singleton")
 */
class QueryResolver implements ResolverInterface
{
    /** @var string */
    public $currentValue = 'cachedResult';

    public function cachedValue(): string
    {
        return $this->currentValue;
    }

    public function secureValue1(): string
    {
        return 'secret1';
    }

    public function secureValue2(): string
    {
        return 'secret2';
    }
}
