<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Helper;

use Magento\Backend\Model\Auth\Session;
use Magento\User\Model\User;

/**
 * Admin session and user doubles: getUser() and getExtra() are magic methods, which PHPUnit can no longer stub.
 */
trait AdminSessionTestTrait
{
    private static function session(?User $user): Session
    {
        $session = new AdminSession();
        $session->user = $user;

        return $session;
    }

    /**
     * @param array<string,mixed>|null $extra
     */
    private static function user(int $id, ?array $extra = null, ?int $roleId = null): AdminUser
    {
        $user = new AdminUser();
        $user->userId = $id;
        $user->roleId = $roleId;
        $user->setData('extra', $extra);

        return $user;
    }
}
