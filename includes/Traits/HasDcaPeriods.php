<?php

namespace WooCommerceOmnipay\Traits;

/**
 * HasDcaPeriods Trait
 *
 * 提供 DCA (定期定額) 功能的共用邏輯
 */
trait HasDcaPeriods
{
    /**
     * 定期定額方案
     *
     * @var array
     */
    protected $dcaPeriods = [];

    /**
     * Get DCA periods option name
     */
    protected function getDcaPeriodsOptionName(): string
    {
        return 'woocommerce_'.$this->id.'_periods';
    }

    /**
     * Load DCA periods from option
     */
    protected function loadDcaPeriods()
    {
        $this->dcaPeriods = get_option($this->getDcaPeriodsOptionName(), []);
    }

    /**
     * 生成 DCA 設定表格 HTML
     */
    public function generate_periods_html($key, $data)
    {
        return woocommerce_omnipay_get_template('admin/dca-periods-table.php', [
            'fieldKey' => $this->get_field_key($key),
            'data' => $data,
            'periods' => $this->dcaPeriods,
            'fieldConfigs' => $this->getDcaFieldConfigs(),
            'defaultPeriod' => $this->getDefaultPeriod(),
        ]);
    }

    /**
     * 處理管理選項更新
     */
    public function process_admin_options()
    {
        // Validate DCA settings
        if (! $this->validateDcaFields()) {
            return false;
        }

        // Save DCA periods
        $this->saveDcaPeriods();

        return parent::process_admin_options();
    }

    /**
     * 檢查付款方式是否可用
     */
    public function is_available()
    {
        if (! parent::is_available()) {
            return false;
        }

        // 未設定定期定額選項時，不開放此付款方式
        if (! (function_exists('is_checkout') && is_checkout())) {
            return true;
        }

        // 新版 WooCommerce Blocks - 檢查單一方案設定
        if (function_exists('has_block') && has_block('woocommerce/checkout')) {
            foreach ($this->getRequiredDcaFields() as $field) {
                if (empty($this->get_option($field))) {
                    return false;
                }
            }

            return true;
        }

        // 舊版傳統結帳 - 檢查多組方案設定
        return ! empty($this->dcaPeriods);
    }

    /**
     * Check if current checkout is using Blocks mode
     */
    protected function isBlocksMode(): bool
    {
        return ! isset($_POST['omnipay_period']);
    }

    /**
     * Get required DCA fields for Blocks mode validation
     */
    abstract protected function getRequiredDcaFields(): array;

    /**
     * Get DCA field configurations
     */
    abstract protected function getDcaFieldConfigs(): array;

    /**
     * Get default period data
     */
    abstract protected function getDefaultPeriod(): array;

    /**
     * Save DCA periods from POST data
     */
    abstract protected function saveDcaPeriods();

    /**
     * 驗證 DCA 欄位
     *
     * @return bool
     */
    abstract protected function validateDcaFields();
}
