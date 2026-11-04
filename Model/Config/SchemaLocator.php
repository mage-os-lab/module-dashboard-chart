<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Config;

use Magento\Framework\Config\SchemaLocatorInterface;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

/**
 * Merged and per-file XSD schemas of dashboard_charts.xml.
 */
class SchemaLocator implements SchemaLocatorInterface
{
    private const string MODULE_NAME = 'MageOS_DashboardChart';

    private readonly string $etcDir;

    public function __construct(ModuleDirReader $moduleDirReader)
    {
        $this->etcDir = $moduleDirReader->getModuleDir(Dir::MODULE_ETC_DIR, self::MODULE_NAME);
    }

    public function getSchema(): ?string
    {
        return $this->etcDir . '/dashboard_charts.xsd';
    }

    public function getPerFileSchema(): ?string
    {
        return $this->etcDir . '/dashboard_charts_file.xsd';
    }
}
