<?php

declare(strict_types=1);

namespace Oniva\GraphQL\Tests\Unit\Transform;

use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Exception;
use Oniva\GraphQL\Transform\FlowErrorTransform;

class FlowErrorTransformTest extends UnitTestCase
{
    protected FlowErrorTransform $obj;
    protected ThrowableStorageInterface&MockObject $throwableStorage;
    protected LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->obj = new FlowErrorTransform();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->inject($this->obj, 'logger', $this->logger);
        $this->throwableStorage = $this->createMock(ThrowableStorageInterface::class);
        $this->inject($this->obj, 'throwableStorage', $this->throwableStorage);

        parent::setUp();
    }

    /**
     * @test
     */
    public function transformGraphQLErrorSkipsTransformationTest(): void
    {
        $this->inject($this->obj, 'includeExceptionMessageInOutput', false);

        $error = new Error('Previous');
        $executionResult = new ExecutionResult(errors: [
            new Error('Error', previous: $error),
        ]);

        $this->throwableStorage->expects($this->never())->method('logThrowable');

        $this->logger->expects($this->never())->method('error');

        $result = $this->obj->transformResult($executionResult);

        self::assertSame($error, $result->errors[0]->getPrevious());
    }

    /**
     * @test
     */
    public function transformExceptionContainsInternalErrorOnlyIfIncludeExceptionMessageInOutputIsFalseTest(): void
    {
        $this->inject($this->obj, 'includeExceptionMessageInOutput', false);

        $exception = new Exception();
        $executionResult = new ExecutionResult(errors: [
            new Error('Error', previous: $exception),
        ]);

        $this->throwableStorage->expects($this->once())->method('logThrowable')
            ->with($exception)
            ->willReturn('Foo - See also: 123.txt');

        $this->logger->expects($this->once())->method('error')
            ->with('GraphQL response with error. The error has bubbled up to the next nullable field: Foo - See also: 123.txt');

        $result = $this->obj->transformResult($executionResult);

        self::assertSame('Internal error (123)', $result->errors[0]->getMessage());
    }

    /**
     * @test
     */
    public function transformExceptionContainsMessageFromThrowableStorageIfIncludeExceptionMessageInOutputIsTrueTest(): void
    {
        $this->inject($this->obj, 'includeExceptionMessageInOutput', true);

        $exception = new Exception();
        $executionResult = new ExecutionResult(errors: [
            new Error('Error', previous: $exception),
        ]);

        $this->throwableStorage->expects($this->once())->method('logThrowable')
            ->with($exception)
            ->willReturn('Foo - See also: 123.txt');

        $this->logger->expects($this->once())->method('error')
            ->with('GraphQL response with error. The error has bubbled up to the next nullable field: Foo - See also: 123.txt');

        $result = $this->obj->transformResult($executionResult);

        self::assertStringContainsString('See also', $result->errors[0]->getMessage());
    }
}
