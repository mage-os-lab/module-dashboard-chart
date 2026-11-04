<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Helper;

use Magento\Authorization\Model\Role;
use Magento\User\Model\User;

/**
 * Admin user double without database: records what saveExtra() received.
 */
final class AdminUser extends User
{
    public ?int $userId = null;

    public ?int $roleId = null;

    /** @var array<string,mixed>|null */
    public ?array $savedExtra = null;

    public function __construct()
    {
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->userId;
    }

    /**
     * @param int|string $modelId
     * @param string|null $field
     * @return $this
     */
    public function load($modelId, $field = null)
    {
        return $this;
    }

    /**
     * @param array<string,mixed>|string $data
     * @return $this
     */
    public function saveExtra($data)
    {
        $this->savedExtra = \is_array($data) ? $data : null;
        $this->setData('extra', $data);

        return $this;
    }

    /**
     * @return Role
     */
    public function getRole()
    {
        $role = new class() extends Role {
            public function __construct()
            {
            }
        };
        $role->setData('role_id', $this->roleId);
        $role->setIdFieldName('role_id');

        return $role;
    }
}
