<?php

declare(strict_types=1);

namespace Oniva\GraphQL;

use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\ControllerContext;

class Context
{
    protected ActionRequest $request;

    public function __construct(ControllerContext $controllerContext)
    {
        $this->request = $controllerContext->getRequest()->getMainRequest();
    }

    public function getRequest(): ActionRequest
    {
        return $this->request;
    }
}
