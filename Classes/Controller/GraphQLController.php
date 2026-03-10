<?php

declare(strict_types=1);

namespace Oniva\GraphQL\Controller;

use GraphQL\Error\SyntaxError;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use Neos\Cache\Frontend\FrontendInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Oniva\GraphQL\Context;
use Oniva\GraphQL\Exception\InvalidContextException;
use Oniva\GraphQL\Log\RequestLoggerInterface;
use Oniva\GraphQL\Service\DefaultFieldResolver;
use Oniva\GraphQL\Service\SchemaService;
use Oniva\GraphQL\Service\ValidationRuleService;

class GraphQLController extends ActionController
{
    /**
     * @Flow\Inject
     *
     * @var SchemaService
     */
    protected $schemaService;

    /**
     * @Flow\Inject
     *
     * @var FrontendInterface
     */
    protected $persistedQueryCache;

    /**
     * @Flow\Inject
     *
     * @var ValidationRuleService
     */
    protected $validationRuleService;

    /**
     * @Flow\InjectConfiguration("context")
     *
     * @var string
     */
    protected $contextClassName;

    /**
     * @Flow\InjectConfiguration("cacheNonPersistedQueries")
     *
     * @var bool
     */
    protected $cacheNonPersistedQueries;

    /**
     * @Flow\InjectConfiguration("nonCachableValidationRules")
     *
     * @var string[]
     */
    protected $nonCachableValidationRules;

    /**
     * @Flow\InjectConfiguration("endpoints")
     *
     * @var mixed[]
     */
    protected $endpointConfigurations;

    /**
     * @Flow\Inject
     *
     * @var RequestLoggerInterface
     */
    protected $requestLogger;

    /**
     * A list of IANA media types which are supported by this controller
     *
     * @see http://www.iana.org/assignments/media-types/index.html
     *
     * @var string[]
     */
    protected $supportedMediaTypes = ['application/json'];

    /**
     * @Flow\SkipCsrfProtection
     *
     * @param array|null $variables
     * @param array|null $extensions
     *
     * @throws \Neos\Flow\Mvc\Exception\NoSuchArgumentException
     * @throws InvalidContextException
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress PEAR.Commenting.FunctionComment.MissingParamTag
     */
    public function queryAction(string $endpoint, ?string $query = null, ?array $variables = null, ?string $operationName = null, ?array $extensions = null): string
    {
        if ($variables !== null && is_string($this->request->getArgument('variables'))) {
            $variables = json_decode($this->request->getArgument('variables'), true);
        }

        $persistedQueryHash = $extensions['persistedQuery']['sha256Hash'] ?? null;
        $persistedQuery = is_string($persistedQueryHash);
        if ($query === null && $persistedQuery === false) {
            $this->response->setContentType('application/json');
            return json_encode(['errors' => [['message' => 'Must provide query string.']]]);
        }

        $schema = $this->schemaService->getSchemaForEndpoint($endpoint);
        $validationRules = $this->validationRuleService->getValidationRulesForEndpoint($endpoint);

        $documentNode = null;
        if ($persistedQuery || $this->cacheNonPersistedQueries) {
            $cacheKey = $persistedQuery ? $persistedQueryHash : hash('sha256', $query);
            $documentNode = $this->persistedQueryCache->get($cacheKey);

            $validationErrorCount = 0;
            if ($documentNode instanceof DocumentNode === false) {
                if ($persistedQuery) {
                    if ($query === null) {
                        $this->response->setContentType('application/json');
                        return json_encode([
                            'errors' => [[
                                'message' => 'PersistedQueryNotFound',
                                'extensions' => ['code' => 'PERSISTED_QUERY_NOT_FOUND'],
                            ]],
                        ]);
                    } elseif (hash('sha256', $query) !== $persistedQueryHash) {
                        $this->response->setContentType('application/json');
                        return json_encode([
                            'errors' => [[
                                'message' => 'Provided sha256Hash does not match query',
                                'extensions' => ['code' => 'PERSISTED_QUERY_HASH_MISMATCH'],
                            ]],
                        ]);
                    }
                }

                try {
                    $documentNode = Parser::parse(new Source($query, 'GraphQL'));
                } catch (SyntaxError) {
                    $documentNode = null;
                }

                if ($documentNode !== null) {
                    // Validate the query against the schema before writing the cache.
                    $cachableRules = $this->filterCachableRules($validationRules, true);
                    // Caching the complexity rule only is reliable if no cost directives are set on variable definitions.
                    $this->prepareQueryComplexityRule($validationRules, $variables);
                    $validationErrorCount = count(DocumentValidator::validate($schema, $documentNode, $cachableRules));
                    if ($validationErrorCount === 0) {
                        $this->persistedQueryCache->set($cacheKey, $documentNode);
                    }
                }
            }

            // If there were no validation errors of the cachable rules, we do not need to validate them again
            if ($validationErrorCount === 0) {
                $validationRules = $this->filterCachableRules($validationRules, false);
            }
        }

        $endpointConfiguration = $this->endpointConfigurations[$endpoint] ?? [];

        if (isset($endpointConfiguration['context'])) {
            $contextClassname = $endpointConfiguration['context'];
        } else {
            $contextClassname = $this->contextClassName;
        }

        $context = new $contextClassname($this->controllerContext);
        if (! $context instanceof Context) {
            throw new InvalidContextException('The configured Context must extend \Oniva\GraphQL\Context', 1545945332);
        }

        if (isset($endpointConfiguration['logRequests']) && $endpointConfiguration['logRequests'] === true) {
            $this->requestLogger->info('Incoming graphql request', ['endpoint' => $endpoint, 'query' => $persistedQuery ? $persistedQueryHash : json_encode($query), 'variables' => empty($variables) ? 'none' : $variables]);
        }

        GraphQL::setDefaultFieldResolver([DefaultFieldResolver::class, 'resolve']);

        $result  = GraphQL::executeQuery(
            $schema,
            $documentNode instanceof DocumentNode ? $documentNode : $query,
            null,
            $context,
            $variables,
            $operationName,
            null,
            $validationRules
        );

        $this->response->setContentType('application/json');
        return json_encode($result->toArray());
    }

    protected function filterCachableRules(array $validationRules, bool $cachable): array
    {
        return array_filter($validationRules, function ($rule) use ($cachable) {
            return in_array($rule::class, $this->nonCachableValidationRules) !== $cachable;
        });
    }

    /**
     * The QueryComplexity validation rule requires the raw variable values to be set.
     * @see GraphQL::promiseToExecute()
     */
    protected function prepareQueryComplexityRule(array $validationRules, array $variableValues): void
    {
        foreach ($validationRules as $rule) {
            if ($rule instanceof QueryComplexity) {
                $rule->setRawVariableValues($variableValues);
            }
        }
    }
}
