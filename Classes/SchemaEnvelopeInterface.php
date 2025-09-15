<?php

declare(strict_types=1);

namespace Oniva\GraphQL;

use GraphQL\Type\Schema;

interface SchemaEnvelopeInterface
{
    public function getSchema(): Schema;
}
