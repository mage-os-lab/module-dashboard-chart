<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Stores > Configuration > Advanced > Admin > Dashboard > Advanced Report Charts.
 */
class Config
{
    public const string XML_PATH_ENABLED = 'admin/dashboard/advanced_report_charts_enabled';
    public const string XML_PATH_DEFAULTS = 'admin/dashboard/advanced_report_charts';
    public const string XML_PATH_MAX_OPENED = 'admin/dashboard/advanced_report_charts_max_opened';

    /** Role value matching every admin role. */
    public const string ALL_ROLES = '*';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $serializer,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    /**
     * Maximum number of charts opened (and therefore loaded) when the dashboard is opened.
     */
    public function getMaxOpened(): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_OPENED));
    }

    /**
     * Default dashboard rows in configuration order.
     *
     * @return array<int,array{role: string, chart: string, opened: bool}>
     */
    public function getDefaultRows(): array
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_DEFAULTS);
        if (!\is_string($value) || $value === '') {
            return [];
        }
        try {
            $rows = $this->serializer->unserialize($value);
        } catch (\InvalidArgumentException) {
            return [];
        }

        $result = [];
        foreach (\is_array($rows) ? $rows : [] as $row) {
            if (!\is_array($row) || empty($row['chart'])) {
                continue;
            }
            $result[] = [
                'role' => (string) ($row['role'] ?? self::ALL_ROLES),
                'chart' => (string) $row['chart'],
                'opened' => (bool) ($row['opened'] ?? true),
            ];
        }

        return $result;
    }
}
