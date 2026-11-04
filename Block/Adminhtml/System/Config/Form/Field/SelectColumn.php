<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

/**
 * Select cell of the dashboard charts field array; the options come from the injected source model
 * (one virtual type per column, see etc/adminhtml/di.xml).
 */
class SelectColumn extends Select
{
    /**
     * @param Context $context
     * @param OptionSourceInterface $source
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly OptionSourceInterface $source,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setInputName($value): static
    {
        $this->setName($value);

        return $this;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * @return string
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->source->toOptionArray());
        }
        $this->setClass('admin__control-select');

        return parent::_toHtml();
    }
}
