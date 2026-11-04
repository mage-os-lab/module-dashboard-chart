<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Config;

use Magento\Framework\Config\Dom;
use Magento\Framework\Config\FileResolverInterface;
use Magento\Framework\Config\Reader\Filesystem;
use Magento\Framework\Config\ValidationStateInterface;

/**
 * Reads dashboard_charts.xml of every module, merged by section and chart id.
 */
class Reader extends Filesystem
{
    /** @var array<string,string> Merge keys: sections and charts are both children of <config>. */
    public const array ID_ATTRIBUTES = [
        '/config/section' => 'id',
        '/config/chart' => 'id',
        '/config/chart/metric' => 'code',
        '/config/chart/dimension' => 'code',
    ];

    /**
     * @var array<string,string>
     */
    protected $_idAttributes = self::ID_ATTRIBUTES;

    /**
     * @param FileResolverInterface $fileResolver
     * @param Converter $converter
     * @param SchemaLocator $schemaLocator
     * @param ValidationStateInterface $validationState
     * @param string $fileName
     * @param array<string,string> $idAttributes
     * @param string $domDocumentClass
     * @param string $defaultScope
     */
    public function __construct(
        FileResolverInterface $fileResolver,
        Converter $converter,
        SchemaLocator $schemaLocator,
        ValidationStateInterface $validationState,
        string $fileName = 'dashboard_charts.xml',
        array $idAttributes = [],
        string $domDocumentClass = Dom::class,
        string $defaultScope = 'global',
    ) {
        parent::__construct(
            $fileResolver,
            $converter,
            $schemaLocator,
            $validationState,
            $fileName,
            $idAttributes,
            $domDocumentClass,
            $defaultScope,
        );
    }
}
