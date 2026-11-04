<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\BlockInterface;

/**
 * Default dashboard charts: rows of admin role, chart and opened state, in display order (sortable).
 */
class DashboardCharts extends AbstractFieldArray
{
    /** @var array<string,string> column => renderer block type */
    private const array COLUMNS = [
        'role' => 'MageOS\DashboardChart\Block\Adminhtml\System\Config\Form\Field\RoleColumn',
        'chart' => 'MageOS\DashboardChart\Block\Adminhtml\System\Config\Form\Field\ChartColumn',
        'opened' => 'MageOS\DashboardChart\Block\Adminhtml\System\Config\Form\Field\OpenedColumn',
    ];

    /** @var array<string,BlockInterface> */
    private array $renderers = [];

    /**
     * @return void
     * @SuppressWarnings("PHPMD.CamelCaseMethodName")
     */
    protected function _prepareToRender()
    {
        $this->addColumn('role', ['label' => __('Admin User Role'), 'renderer' => $this->getRenderer('role')]);
        $this->addColumn('chart', ['label' => __('Chart'), 'renderer' => $this->getRenderer('chart')]);
        $this->addColumn('opened', ['label' => __('Opened'), 'renderer' => $this->getRenderer('opened')]);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string) __('Add Chart');
    }

    /**
     * @param DataObject $row
     * @return void
     * @SuppressWarnings("PHPMD.CamelCaseMethodName")
     */
    protected function _prepareArrayRow(DataObject $row)
    {
        $attributes = [];
        foreach (array_keys(self::COLUMNS) as $column) {
            /** @var SelectColumn $renderer */
            $renderer = $this->getRenderer($column);
            $attributes['option_' . $renderer->calcOptionHash((string) $row->getData($column))] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $attributes);
    }

    /**
     * The core field array template plus the sortable rows script (rows are saved in DOM order).
     *
     * @return string
     * @SuppressWarnings("PHPMD.CamelCaseMethodName")
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $init = [
            '*' => [
                'MageOS_DashboardChart/js/config/sortable-rows' => ['tableId' => (string) $element->getId()],
            ],
        ];

        return parent::_getElementHtml($element)
            . '<script type="text/x-magento-init">'
            . json_encode($init, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES)
            . '</script>';
    }

    private function getRenderer(string $column): BlockInterface
    {
        // The column renderers are virtual types of SelectColumn (etc/adminhtml/di.xml), unknown to PHPStan.
        return $this->renderers[$column] ??= $this->getLayout()->createBlock( // @phpstan-ignore argument.templateType
            self::COLUMNS[$column], // @phpstan-ignore argument.type
            '',
            ['data' => ['is_render_to_js_template' => true]]
        );
    }
}
