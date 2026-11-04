<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Config\Source;

use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;
use MageOS\DashboardChart\Model\Config;

/**
 * Admin user roles, plus "All Roles".
 */
class Roles implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $roleCollectionFactory)
    {
    }

    /**
     * @return array<int,array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => Config::ALL_ROLES, 'label' => (string) __('All Roles')]];
        foreach ($this->roleCollectionFactory->create()->setRolesFilter()->setOrder('role_name', 'ASC') as $role) {
            $options[] = ['value' => (string) $role->getId(), 'label' => (string) $role->getRoleName()];
        }

        return $options;
    }
}
