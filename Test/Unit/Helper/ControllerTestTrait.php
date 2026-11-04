<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Helper;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Backend controller context and JSON result doubles; the result records what the controller set.
 */
trait ControllerTestTrait
{
    /** @var array<string,mixed> */
    private array $resultData = [];

    private int $resultCode = 200;

    /**
     * @param array<string,mixed> $params
     */
    private function context(array $params): Context
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => $params[$name] ?? $default
        );
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);

        return $context;
    }

    private function jsonFactory(): JsonFactory
    {
        $result = $this->createStub(JsonResult::class);
        $result->method('setData')->willReturnCallback(function (mixed $data) use ($result): JsonResult {
            $this->resultData = (array) $data;

            return $result;
        });
        $result->method('setHttpResponseCode')->willReturnCallback(function (int $code) use ($result): JsonResult {
            $this->resultCode = $code;

            return $result;
        });
        $factory = $this->createStub(JsonFactory::class);
        $factory->method('create')->willReturn($result);

        return $factory;
    }
}
