<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;

/**
 * NewebPay 定期定額 Gateway
 */
class NewebPayDCAGateway extends NewebPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'CREDIT';

    /**
     * 定期定額方案
     *
     * @var array
     */
    protected $dca_periods = [];

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'newebpay_dca';
        $config['title'] = $config['title'] ?? __('NewebPay Recurring Payment', 'woocommerce-omnipay');
        $config['description'] = $config['description'] ?? __('Pay with credit card recurring payment', 'woocommerce-omnipay');

        parent::__construct($config);

        // Load DCA periods from option
        $this->dca_periods = get_option($this->getDcaPeriodsOptionName(), []);
    }

    /**
     * Get DCA periods option name
     */
    protected function getDcaPeriodsOptionName(): string
    {
        return 'woocommerce_omnipay_newebpay_dca_periods';
    }

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        // Blocks mode settings (single period)
        $this->form_fields['dca_blocks_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $this->form_fields['dca_blocks_caption'] = [
            'title' => '',
            'type' => 'title',
            'description' => __('There are two section fields for DCA settings: WooCommerce Blocks and Woocommerce Shortcode. Please fill out the section that matches your current page configuration. If you are uncertain about which page configuration you are using, input the identical setting in both sections.', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_blocks_title'] = [
            'title' => __('DCA (Support WooCommerce Blocks)', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('The following settings support the WooCommerce Blocks checkout page and do not support the use of the traditional shortcode-based checkout. Please configure carefully', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_periodType'] = [
            'title' => __('Period Type', 'woocommerce-omnipay'),
            'type' => 'select',
            'default' => 'M',
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'options' => [
                'Y' => __('Year', 'woocommerce-omnipay'),
                'M' => __('Month', 'woocommerce-omnipay'),
                'W' => __('Week', 'woocommerce-omnipay'),
                'D' => __('Day', 'woocommerce-omnipay'),
            ],
        ];

        $this->form_fields['dca_periodPoint'] = [
            'title' => __('Period Point', 'woocommerce-omnipay'),
            'type' => 'text',
            'default' => '',
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_periodTimes'] = [
            'title' => __('Period Times', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 12,
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'custom_attributes' => [
                'min' => 1,
                'step' => 1,
            ],
        ];

        $this->form_fields['dca_periodStartType'] = [
            'title' => __('Period Start Type', 'woocommerce-omnipay'),
            'type' => 'select',
            'default' => 2,
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'options' => [
                '1' => __('1 - Authorize and start immediately', 'woocommerce-omnipay'),
                '2' => __('2 - Authorize only, start manually', 'woocommerce-omnipay'),
                '3' => __('3 - Delegate to merchant', 'woocommerce-omnipay'),
            ],
        ];

        // Shortcode mode settings (multiple periods table)
        $this->form_fields['dca_shortcode_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $this->form_fields['dca_shortcode_title'] = [
            'title' => __('DCA (Support WooCommerce Shortcode)', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('The following settings support the traditional shortcode-based checkout page and do not support the use of the WooCommerce Blocks checkout. Please configure carefully', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_periods'] = [
            'title' => __('DCA Periods', 'woocommerce-omnipay'),
            'type' => 'dca_periods_newebpay',
            'default' => '',
            'description' => '',
        ];
    }

    /**
     * 生成 DCA 設定表格 HTML
     */
    public function generate_dca_periods_newebpay_html($key, $data)
    {
        return woocommerce_omnipay_get_template('admin/dca-periods-table.php', [
            'field_key' => $this->get_field_key($key),
            'data' => $data,
            'periods' => $this->dca_periods,
            'headers' => [
                __('Period Type (Y/M/W/D)', 'woocommerce-omnipay'),
                __('Period Point', 'woocommerce-omnipay'),
                __('Period Times', 'woocommerce-omnipay'),
                __('Start Type (1/2/3)', 'woocommerce-omnipay'),
            ],
            'field_configs' => [
                [
                    'name' => 'periodType',
                    'type' => 'text',
                    'default' => 'M',
                    'attributes' => ['maxlength' => '1', 'required' => 'required'],
                ],
                [
                    'name' => 'periodPoint',
                    'type' => 'text',
                    'default' => '',
                    'attributes' => [],
                ],
                [
                    'name' => 'periodTimes',
                    'type' => 'number',
                    'default' => 12,
                    'attributes' => ['min' => '1', 'required' => 'required'],
                ],
                [
                    'name' => 'periodStartType',
                    'type' => 'number',
                    'default' => 2,
                    'attributes' => ['min' => '1', 'max' => '3', 'required' => 'required'],
                ],
            ],
            'field_prefix' => 'newebpay_dca_',
            'default_period' => ['periodType' => 'M', 'periodPoint' => '', 'periodTimes' => 12, 'periodStartType' => 2],
            'table_width' => 700,
        ]);
    }

    /**
     * 處理管理選項更新
     */
    public function process_admin_options()
    {
        // Save DCA periods
        $this->saveDcaPeriods();

        return parent::process_admin_options();
    }

    /**
     * Save DCA periods from POST data
     */
    protected function saveDcaPeriods()
    {
        $dca_periods = [];
        if (isset($_POST['newebpay_dca_periodType']) && is_array($_POST['newebpay_dca_periodType'])) {
            $periodTypes = array_map('sanitize_text_field', $_POST['newebpay_dca_periodType']);
            $periodPoints = isset($_POST['newebpay_dca_periodPoint']) && is_array($_POST['newebpay_dca_periodPoint'])
                ? array_map('sanitize_text_field', $_POST['newebpay_dca_periodPoint'])
                : [];
            $periodTimes = isset($_POST['newebpay_dca_periodTimes']) && is_array($_POST['newebpay_dca_periodTimes'])
                ? array_map('absint', $_POST['newebpay_dca_periodTimes'])
                : [];
            $periodStartTypes = isset($_POST['newebpay_dca_periodStartType']) && is_array($_POST['newebpay_dca_periodStartType'])
                ? array_map('absint', $_POST['newebpay_dca_periodStartType'])
                : [];

            foreach ($periodTypes as $i => $periodType) {
                if (! empty($periodType)) {
                    $dca_periods[] = [
                        'periodType' => $periodType,
                        'periodPoint' => $periodPoints[$i] ?? '',
                        'periodTimes' => $periodTimes[$i] ?? 0,
                        'periodStartType' => $periodStartTypes[$i] ?? 0,
                    ];
                }
            }
        }
        update_option($this->getDcaPeriodsOptionName(), $dca_periods);
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
            return ! (empty($this->get_option('dca_periodType'))
                || empty($this->get_option('dca_periodTimes'))
                || empty($this->get_option('dca_periodStartType')));
        }

        // 舊版傳統結帳 - 檢查多組方案設定
        return ! empty($this->dca_periods);
    }

    /**
     * 顯示付款欄位
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo '<p>'.wp_kses_post($this->description).'</p>';
        }

        // 只有 Shortcode 版本才顯示下拉選單
        // Blocks 版本不需要顯示（直接使用設定的方案）
        if (is_checkout() && ! is_wc_endpoint_url('order-pay')) {
            $total = WC()->cart ? WC()->cart->total : 0;

            echo woocommerce_omnipay_get_template('checkout/dca-form.php', [
                'periods' => $this->dca_periods,
                'total' => $total,
                'period_type_labels' => [
                    'Y' => __('year', 'woocommerce-omnipay'),
                    'M' => __('month', 'woocommerce-omnipay'),
                    'W' => __('week', 'woocommerce-omnipay'),
                    'D' => __('day', 'woocommerce-omnipay'),
                ],
                'period_fields' => ['periodType', 'periodPoint', 'periodTimes', 'periodStartType'],
                'warning_message' => __('You will use <strong>NewebPay recurring credit card payment</strong>. Please note that the products you purchased are <strong>non-single payment</strong> products.', 'woocommerce-omnipay'),
            ]);
        }
    }

    /**
     * 準備付款資料
     *
     * @param  \WC_Order  $order  訂單
     * @return array
     */
    protected function preparePaymentData($order)
    {
        $data = parent::preparePaymentData($order);
        $data['CREDIT'] = '1';

        // Get DCA settings based on mode
        $dcaData = $this->isBlocksMode()
            ? $this->getBlocksModeDcaData()
            : $this->getShortcodeModeDcaData();

        $data = array_merge($data, $dcaData);
        $data['PeriodAmt'] = (int) $order->get_total();

        // PayerEmail is required for recurring payment
        $data['PayerEmail'] = $order->get_billing_email() ?: get_bloginfo('admin_email');

        return $data;
    }

    /**
     * Check if current checkout is using Blocks mode
     */
    protected function isBlocksMode(): bool
    {
        return ! isset($_POST['omnipay_dca_period']);
    }

    /**
     * Get DCA data for Blocks mode
     */
    protected function getBlocksModeDcaData(): array
    {
        return [
            'PeriodType' => $this->get_option('dca_periodType', 'M'),
            'PeriodPoint' => $this->get_option('dca_periodPoint', ''),
            'PeriodTimes' => (int) $this->get_option('dca_periodTimes', 12),
            'PeriodStartType' => (int) $this->get_option('dca_periodStartType', 2),
        ];
    }

    /**
     * Get DCA data for Shortcode mode
     */
    protected function getShortcodeModeDcaData(): array
    {
        $selectedPeriod = sanitize_text_field($_POST['omnipay_dca_period']);
        $parts = explode('_', $selectedPeriod);

        if (count($parts) === 4) {
            [$periodType, $periodPoint, $periodTimes, $periodStartType] = $parts;

            return [
                'PeriodType' => $periodType,
                'PeriodPoint' => $periodPoint,
                'PeriodTimes' => (int) $periodTimes,
                'PeriodStartType' => (int) $periodStartType,
            ];
        }

        // Fallback to default values if format is invalid
        return [
            'PeriodType' => 'M',
            'PeriodPoint' => '',
            'PeriodTimes' => 12,
            'PeriodStartType' => 2,
        ];
    }
}
