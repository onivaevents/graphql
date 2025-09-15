<?php

declare(strict_types=1);

namespace Oniva\GraphQL\Tests\Functional\Directive;

use Oniva\GraphQL\Directive\CachedDirective;
use Oniva\GraphQL\ResolveCacheInterface;
use Oniva\GraphQL\Tests\Functional\Directive\Fixtures\QueryResolver;
use Oniva\GraphQL\Tests\Functional\GraphQLFunctionTestCase;

class CachedDirectiveTest extends GraphQLFunctionTestCase
{
    /**
     * @var ResolveCacheInterface
     */
    protected $resolveCache;

    public function setUp(): void
    {
        parent::setUp();
        $this->resolveCache = $this->objectManager->get(ResolveCacheInterface::class);
        $this->resolveCache->flush();
    }

    /**
     * @test
     */
    public function cachedDirectiveWillCacheValue(): void
    {
        $schema = __DIR__ . '/Fixtures/schema.graphql';
        $configuration = [
            'resolvers' => [
                'Query' => QueryResolver::class,
            ],
            'schemaDirectives' => [
                'cached' => CachedDirective::class,
            ],
        ];

        $query = '{ cachedValue }';

        $result = $this->executeQuery($schema, $configuration, $query);

        static::assertFalse(isset($result['errors']), 'graphql query did not execute without errors');
        static::assertEquals('cachedResult', $result['data']['cachedValue']);

        /** @var QueryResolver $queryResolver */
        $queryResolver = $this->objectManager->get(QueryResolver::class);
        $queryResolver->currentValue = 'newValue';

        $result = $this->executeQuery($schema, $configuration, $query);
        static::assertEquals('cachedResult', $result['data']['cachedValue']);

        $this->resolveCache->flushByTag('my-test-tag');

        $result = $this->executeQuery($schema, $configuration, $query);
        static::assertEquals('newValue', $result['data']['cachedValue']);
    }
}
