<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Helper;

use Magento\Backend\Model\Auth\Session;
use Magento\User\Model\User;

/**
 * Admin session double: getUser() is a magic method, which PHPUnit can no longer stub.
 */
final class AdminSession extends Session
{
    public ?User $user = null;

    public function __construct()
    {
    }

    /**
     * @param string $method
     * @param mixed[] $args
     */
    public function __call($method, $args): mixed
    {
        return $method === 'getUser' ? $this->user : null;
    }
}
