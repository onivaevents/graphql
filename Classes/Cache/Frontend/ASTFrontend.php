<?php
declare(strict_types=1);

namespace Oniva\GraphQL\Cache\Frontend;

use GraphQL\Language\AST\Node;
use GraphQL\Utils\AST;
use Neos\Cache\Exception\InvalidDataException;
use Neos\Cache\Frontend\PhpFrontend;

/**
 * A cache frontend tailored to GraphQL AST nodes.
 *
 * Entries are stored as generated PHP files returning the AST array
 * representation. This allows efficient loading through the PHP-based cache
 * backend and Opcache, while restoring the original AST lazily via
 * {@see AST::fromArray()}.
 *
 * @see https://webonyx.github.io/graphql-php/schema-definition-language/#performance-considerations
 *
 * @api
 */
class ASTFrontend extends PhpFrontend
{
    /**
     * Loads a cached GraphQL AST node.
     *
     * The cached PHP file returns the array representation of the AST, which is
     * then converted back into a node tree via {@see AST::fromArray()}.
     *
     * @param string $entryIdentifier Identifier of the cache entry to fetch
     * @return Node|bool The restored AST node or FALSE if no cache entry exists
     * @throws \InvalidArgumentException
     * @api
     */
    public function get(string $entryIdentifier)
    {
        if (!$this->isValidEntryIdentifier($entryIdentifier)) {
            throw new \InvalidArgumentException('"' . $entryIdentifier . '" is not a valid cache entry identifier.', 1773050051);
        }
        $code = $this->backend->get($entryIdentifier);

        if ($code === false) {
            return false;
        }

        return AST::fromArray($this->requireOnce($entryIdentifier));
    }

    /**
     * Stores a GraphQL AST node as generated PHP code.
     *
     * The node is converted to its array representation via
     * {@see AST::toArray()} and written as a PHP file returning that array.
     *
     * @param string $entryIdentifier An identifier used for this cache entry
     * @param mixed $sourceCode GraphQL AST node to cache
     * @param array<string> $tags Tags to associate with this cache entry
     * @param int|null $lifetime Lifetime of this cache entry in seconds. NULL uses the default lifetime, 0 means unlimited lifetime.
     * @return void
     * @throws InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \Neos\Cache\Exception
     * @api
     */
    public function set(string $entryIdentifier, $sourceCode, array $tags = [], ?int $lifetime = null)
    {
        if (!$this->isValidEntryIdentifier($entryIdentifier)) {
            throw new \InvalidArgumentException('"' . $entryIdentifier . '" is not a valid cache entry identifier.', 1773050052);
        }
        if (!$sourceCode instanceof Node) {
            throw new InvalidDataException('The given data is not a valid Node object.', 1773050053);
        }
        foreach ($tags as $tag) {
            if (!$this->isValidTag($tag)) {
                throw new \InvalidArgumentException('"' . $tag . '" is not a valid tag for a cache entry.', 1773050054);
            }
        }

        $sourceCode = "<?php\nreturn " . var_export(AST::toArray($sourceCode), true) . ";\n#";
        $this->backend->set($entryIdentifier, $sourceCode, $tags, $lifetime);
    }
}
