<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use MageOS\DashboardChart\Model\Config;
use MageOS\DashboardChart\Model\Config\Source\Roles;

/**
 * Default dashboard charts: refuses a configuration that opens more charts at dashboard load than
 * "Max Charts Opened at Load" for a role (its own rows plus the "All Roles" rows, first row of a chart wins,
 * exactly as {@see \MageOS\DashboardChart\Model\Dashboard\LayoutResolver} reads them).
 */
class DashboardCharts extends ArraySerialized
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param Roles $roles
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string,mixed> $data
     * @param Json|null $serializer
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly Roles $roles,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?Json $serializer = null,
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data, $serializer);
    }

    /**
     * @throws LocalizedException
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        if (\is_array($value)) {
            $this->validateOpened($value);
        }

        return parent::beforeSave();
    }

    /**
     * @param array<mixed> $rows
     * @throws LocalizedException
     */
    private function validateOpened(array $rows): void
    {
        $max = $this->getFieldsetDataValue('advanced_report_charts_max_opened');
        $max = $max === null || $max === ''
            ? (int) $this->_config->getValue(Config::XML_PATH_MAX_OPENED)
            : (int) $max;

        $roleIds = [Config::ALL_ROLES => true];
        foreach ($rows as $row) {
            if (\is_array($row) && isset($row['role'])) {
                $roleIds[(string) $row['role']] = true;
            }
        }

        foreach (array_keys($roleIds) as $roleId) {
            $opened = $this->countOpened($rows, (string) $roleId);
            if ($opened > $max) {
                throw new LocalizedException(new Phrase(
                    'Default Advanced Report Charts: %1 charts are opened at dashboard load for "%2", the maximum is %3.',
                    [$opened, $this->getRoleLabel((string) $roleId), $max]
                ));
            }
        }
    }

    /**
     * Opened charts for a role: its rows and the "All Roles" rows, first row of a chart wins.
     *
     * @param array<mixed> $rows
     */
    private function countOpened(array $rows, string $roleId): int
    {
        $seen = [];
        $opened = 0;
        foreach ($rows as $row) {
            if (!\is_array($row) || empty($row['chart'])) {
                continue;
            }
            $role = (string) ($row['role'] ?? Config::ALL_ROLES);
            if (($role !== Config::ALL_ROLES && $role !== $roleId) || isset($seen[$row['chart']])) {
                continue;
            }
            $seen[$row['chart']] = true;
            $opened += (int) (bool) ($row['opened'] ?? false);
        }

        return $opened;
    }

    private function getRoleLabel(string $roleId): string
    {
        foreach ($this->roles->toOptionArray() as $option) {
            if ($option['value'] === $roleId) {
                return $option['label'];
            }
        }

        return $roleId;
    }
}
