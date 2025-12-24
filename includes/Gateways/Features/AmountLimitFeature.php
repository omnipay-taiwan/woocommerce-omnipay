<?php

namespace WooCommerceOmnipay\Gateways\Features;

use WC_Payment_Gateway;

/**
 * Amount Limit Feature
 *
 * 金額限制功能（最低/最高金額）
 */
class AmountLimitFeature extends AbstractFeature
{
    public const TYPE_MIN = 'min';

    public const TYPE_MAX = 'max';

    /**
     * @var string 限制類型 (min/max)
     */
    private $type;

    /**
     * @var string 表單欄位鍵名
     */
    private $fieldKey;

    /**
     * Constructor
     *
     * @param  string  $type  限制類型 (min/max)
     */
    public function __construct(string $type = self::TYPE_MIN)
    {
        $this->type = $type;
        $this->fieldKey = $type.'_amount';
    }

    /**
     * {@inheritdoc}
     */
    public function initFormFields(array &$formFields): void
    {
        $isMin = $this->type === self::TYPE_MIN;

        $formFields[$this->fieldKey] = [
            'title' => $isMin
                ? __('Minimum Amount', 'woocommerce-omnipay')
                : __('Maximum Amount', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => $isMin
                ? __('Minimum order amount required for this payment method (0 = no limit)', 'woocommerce-omnipay')
                : __('Maximum order amount for this payment method (0 = no limit)', 'woocommerce-omnipay'),
            'default' => '0',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '0'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(WC_Payment_Gateway $gateway): bool
    {
        $limit = (int) $gateway->get_option($this->fieldKey, 0);

        if ($limit <= 0) {
            return true;
        }

        $total = (float) WC()->cart->get_total('edit');

        return $this->type === self::TYPE_MIN
            ? $total >= $limit
            : $total <= $limit;
    }

    /**
     * 建立最低金額限制
     */
    public static function min(): self
    {
        return new self(self::TYPE_MIN);
    }

    /**
     * 建立最高金額限制
     */
    public static function max(): self
    {
        return new self(self::TYPE_MAX);
    }
}
