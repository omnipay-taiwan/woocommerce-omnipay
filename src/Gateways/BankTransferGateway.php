<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Gateways;

use OmnipayTaiwan\WooCommerce_Omnipay\Constants;
use OmnipayTaiwan\WooCommerce_Omnipay\Helper;
use OmnipayTaiwan\WooCommerce_Omnipay\Repositories\OrderRepository;

/**
 * BankTransfer Gateway
 *
 * 處理銀行轉帳付款，顯示固定的銀行帳號資訊
 */
class BankTransferGateway extends OmnipayGateway
{
    /**
     * 選中的銀行帳號
     *
     * @var array|null
     */
    protected $selectedAccount;

    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        // 註冊匯款帳號後5碼的 AJAX 處理（前台）
        add_action('woocommerce_api_'.$this->id.'_remittance', [$this, 'handleRemittance']);

        // admin 訂單編輯：顯示 inline 欄位 + 儲存
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_remittance_last5_admin_field'], 20);
        add_action('woocommerce_process_shop_order_meta', [$this, 'save_remittance_last5_admin']);       // classic
        add_action('woocommerce_admin_order_save_action', [$this, 'save_remittance_last5_admin']);       // HPOS
    }

    /**
     * 顯示付款欄位
     */
    public function payment_fields()
    {
        parent::payment_fields();

        $config = $this->getBankAccountsConfig();
        $accounts = $config['accounts'];

        if (empty($accounts)) {
            return;
        }

        // 統一使用選單顯示（包含單一帳號情況）
        echo woocommerce_omnipay_get_template('checkout/bank-account-form.php', [
            'accounts' => $accounts,
            'last_digits' => Constants::REMITTANCE_LAST_DIGITS,
        ]);
    }

    /**
     * 取得付款資訊輸出（含匯款帳號後5碼表單）
     *
     * @param  \WC_Order  $order
     * @param  bool  $plainText
     * @return string
     */
    public function getPaymentInfoOutput($order, $plainText = false)
    {
        // 取得銀行資訊
        $bankCode = $order->get_meta(OrderRepository::META_BANK_CODE);
        $bankAccount = $order->get_meta(OrderRepository::META_BANK_ACCOUNT);

        // 格式化銀行帳號：銀行代碼-帳號 (例: 822-xxxxxxxx)
        $formattedAccount = $this->formatBankAccount($bankCode, $bankAccount);

        // 組合付款資訊
        $paymentInfo = [];
        if (! empty($formattedAccount)) {
            $paymentInfo[OrderRepository::META_BANK_ACCOUNT] = $formattedAccount;
        }

        // 加入匯款帳號後5碼（如果有）
        $remittanceLast5 = $order->get_meta(OrderRepository::META_REMITTANCE_LAST5);
        if (! empty($remittanceLast5)) {
            $paymentInfo[OrderRepository::META_REMITTANCE_LAST5] = $remittanceLast5;
        }

        $template = $this->getPaymentInfoTemplate($plainText);
        $output = woocommerce_omnipay_get_template($template, [
            'payment_info' => $paymentInfo,
            'labels' => $this->getBankTransferLabels(),
        ]);

        // 純文字模式、admin 或非此 gateway 的訂單不顯示前台表單
        // admin 另有 display_remittance_last5_admin_field 以 inline 欄位處理
        if ($plainText || is_admin() || $order->get_payment_method() !== $this->id) {
            return $output;
        }

        // 加入匯款帳號後5碼表單（前台）
        $output .= $this->getRemittanceFormOutput($order);

        return $output;
    }

    /**
     * 處理匯款帳號後5碼提交
     */
    public function handleRemittance()
    {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order_key = isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';
        $last5 = isset($_POST['remittance_last5']) ? sanitize_text_field($_POST['remittance_last5']) : '';
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        // 取得訂單以便取得 redirect URL
        $order = $this->orders->findById($order_id);
        $redirect_url = $order ? $order->get_view_order_url() : wc_get_page_permalink('myaccount');

        // 驗證 nonce
        if (! wp_verify_nonce($nonce, 'omnipay_remittance_nonce')) {
            wc_add_notice(__('Security verification failed', 'woocommerce-omnipay'), 'error');
            $this->redirectAndExit($redirect_url);

            return;
        }

        // 驗證訂單
        if (! $order || $order->get_order_key() !== $order_key) {
            wc_add_notice(__('Order verification failed', 'woocommerce-omnipay'), 'error');
            $this->redirectAndExit($redirect_url);

            return;
        }

        // 若輸入超過指定位數且全為數字，自動截取最後 N 碼
        if (strlen($last5) > Constants::REMITTANCE_LAST_DIGITS && ctype_digit($last5)) {
            $last5 = substr($last5, -Constants::REMITTANCE_LAST_DIGITS);
        }

        // 驗證格式（必須是指定位數的數字）
        $pattern = sprintf('/^\d{%d}$/', Constants::REMITTANCE_LAST_DIGITS);
        if (! preg_match($pattern, $last5)) {
            wc_add_notice(
                sprintf(__('Please enter %d digits', 'woocommerce-omnipay'), Constants::REMITTANCE_LAST_DIGITS),
                'error'
            );
            $this->redirectAndExit($redirect_url);

            return;
        }

        // 儲存
        $this->orders->saveRemittanceLast5($order, $last5);

        wc_add_notice(__('Successfully submitted', 'woocommerce-omnipay'), 'success');

        // 從 referer 取得原始頁面 URL，優先導回原頁面
        $referer = wp_get_referer();
        $this->redirectAndExit($referer ?: $redirect_url);
    }

    /**
     * 覆寫 trait 的 display_payment_info_admin，改用 WC admin form-field 格式
     * 避免前台樣式的 <section>/<table> 跑進 admin 帳單欄位
     *
     * @param  \WC_Order  $order
     */
    public function display_payment_info_admin($order)
    {
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        $bankCode = $order->get_meta(OrderRepository::META_BANK_CODE);
        $bankAccount = $order->get_meta(OrderRepository::META_BANK_ACCOUNT);
        $formattedAccount = $this->formatBankAccount($bankCode, $bankAccount);

        if (empty($formattedAccount)) {
            return;
        }

        echo '<p class="form-field">';
        echo '<label>' . esc_html__('Account Number', 'woocommerce-omnipay') . '</label>';
        echo '<strong>' . esc_html($formattedAccount) . '</strong>';
        echo '</p>';
    }

    /**
     * 在管理後台訂單編輯頁顯示匯款帳號後碼 inline 輸入欄位
     * （不使用獨立 <form>，直接嵌入 WC order edit form 以避免巢狀 form 問題）
     *
     * @param  \WC_Order  $order
     */
    public function display_remittance_last5_admin_field($order)
    {
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        $last5 = $order->get_meta(OrderRepository::META_REMITTANCE_LAST5);
        $label = sprintf(
            __('Last %d Digits of Remittance Account', 'woocommerce-omnipay'),
            Constants::REMITTANCE_LAST_DIGITS
        );

        echo '<p class="form-field">';
        echo '<label>' . esc_html($label) . '</label>';
        echo '<input type="text" name="' . esc_attr(OrderRepository::META_REMITTANCE_LAST5) . '"'
            . ' value="' . esc_attr($last5) . '"'
            . ' maxlength="20" class="short">';
        echo '</p>';
    }

    /**
     * 儲存管理後台訂單編輯頁的匯款帳號後碼
     * 同時支援 classic（order ID）與 HPOS（WC_Order）
     *
     * @param  int|\WC_Order  $order_or_id
     */
    public function save_remittance_last5_admin($order_or_id)
    {
        if (! isset($_POST[OrderRepository::META_REMITTANCE_LAST5])) {
            return;
        }

        $order = wc_get_order($order_or_id);
        if (! $order || $order->get_payment_method() !== $this->id) {
            return;
        }

        $last5 = sanitize_text_field($_POST[OrderRepository::META_REMITTANCE_LAST5]);

        // 若輸入超過指定位數且全為數字，自動截取最後 N 碼
        if (strlen($last5) > Constants::REMITTANCE_LAST_DIGITS && ctype_digit($last5)) {
            $last5 = substr($last5, -Constants::REMITTANCE_LAST_DIGITS);
        }

        // 空白 → 清除舊值
        if (empty($last5)) {
            $order->delete_meta_data(OrderRepository::META_REMITTANCE_LAST5);
            $order->save();

            return;
        }

        $pattern = sprintf('/^\d{%d}$/', Constants::REMITTANCE_LAST_DIGITS);
        if (preg_match($pattern, $last5)) {
            $this->orders->saveRemittanceLast5($order, $last5);
        }
    }

    /**
     * 取得已初始化的 Adapter（支援多帳號選擇）
     *
     * @return \OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Contracts\GatewayAdapter
     */
    protected function getAdapter()
    {
        $settings = $this->overrideSettings ? $this->settings : [];
        $allSettings = $this->settingsManager->getAllSettings($settings);

        // 處理多帳號選擇
        $allSettings = $this->applySelectedAccount($allSettings);

        return $this->adapter->initializeFromSettings($allSettings);
    }

    /**
     * 取得銀行帳號池設定
     *
     * @return array{accounts: array, selection_mode: string}
     */
    protected function getBankAccountsConfig(): array
    {
        $settings = $this->settingsManager->getAllSettings(
            $this->overrideSettings ? $this->settings : []
        );

        $bankAccounts = $settings['bank_accounts'] ?? [];
        if (is_string($bankAccounts) && ! empty($bankAccounts)) {
            $bankAccounts = json_decode($bankAccounts, true) ?: [];
        }

        return [
            'accounts' => is_array($bankAccounts) ? $bankAccounts : [],
            'selection_mode' => $settings['selection_mode'] ?? 'random',
        ];
    }

    /**
     * 套用選中的帳號到設定
     *
     * @param  array  $settings  原始設定
     * @return array
     */
    protected function applySelectedAccount(array $settings)
    {
        $config = $this->getBankAccountsConfig();

        // 如果沒有帳號池，使用原本的單一帳號設定
        if (empty($config['accounts'])) {
            return $settings;
        }

        $account = $this->selectAccount($config['accounts'], $config['selection_mode']);

        if ($account) {
            $this->selectedAccount = $account;
            $settings['bank_code'] = $account['bank_code'] ?? '';
            $settings['account_number'] = $account['account_number'] ?? '';
            $settings['secret'] = $account['secret'] ?? '';
        }

        return $settings;
    }

    /**
     * 根據選擇模式選擇帳號
     *
     * @param  array  $accounts  帳號池
     * @param  string  $mode  選擇模式
     * @return array|null
     */
    protected function selectAccount(array $accounts, $mode)
    {
        if (empty($accounts)) {
            return null;
        }

        switch ($mode) {
            case 'user_choice':
                return $this->selectByUserChoice($accounts);

            case 'round_robin':
                return $this->selectByRoundRobin($accounts);

            case 'random':
            default:
                return $this->selectByRandom($accounts);
        }
    }

    /**
     * 隨機選擇帳號
     */
    protected function selectByRandom(array $accounts)
    {
        return $accounts[array_rand($accounts)];
    }

    /**
     * 輪詢選擇帳號
     */
    protected function selectByRoundRobin(array $accounts)
    {
        $lastIndex = (int) get_option('omnipay_banktransfer_last_account_index', -1);
        $nextIndex = ($lastIndex + 1) % count($accounts);
        update_option('omnipay_banktransfer_last_account_index', $nextIndex);

        return $accounts[$nextIndex];
    }

    /**
     * 用戶選擇帳號
     */
    protected function selectByUserChoice(array $accounts)
    {
        // 檢查是否有用戶選擇的帳號索引（Shortcode 模式）
        if (isset($_POST['bank_account_index'])) {
            $index = (int) $_POST['bank_account_index'];

            return $accounts[$index] ?? $accounts[0];
        }

        // Blocks 模式：fallback 到隨機選擇
        return $this->selectByRandom($accounts);
    }

    /**
     * 檢查是否有付款欄位需要顯示
     */
    protected function hasPaymentFields(): bool
    {
        $config = $this->getBankAccountsConfig();

        // 只要有帳號就需要顯示選單
        return count($config['accounts']) >= 1;
    }

    /**
     * 取得付款資訊通知 URL
     *
     * BankTransfer 的 paymentInfoUrl 用於用戶 redirect
     * 因此回傳 thankyou 頁面 URL，付款資訊在 redirect 時儲存
     *
     * @param  \WC_Order  $order  訂單
     * @return string
     */
    protected function getPaymentInfoUrl($order)
    {
        return $this->get_return_url($order);
    }

    /**
     * 需要 redirect 付款事件
     *
     * BankTransfer 在 redirect 時儲存銀行資訊到訂單
     *
     * @param  \WC_Order  $order  訂單
     * @param  \Omnipay\Common\Message\RedirectResponseInterface  $response  Omnipay 回應
     * @return array
     */
    protected function onPaymentRedirect($order, $response)
    {
        $redirect_data = $response->getRedirectData();
        $this->savePaymentInfo($order, $redirect_data);

        return parent::onPaymentRedirect($order, $response);
    }

    /**
     * 儲存付款資訊
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     */
    protected function savePaymentInfo($order, array $data)
    {
        $this->orders->savePaymentInfo($order, [
            'BankCode' => $data['bank_code'] ?? '',
            'BankAccount' => $data['account_number'] ?? '',
        ]);
    }

    /**
     * 格式化銀行帳號顯示
     *
     * @param  string  $bankCode  銀行代碼
     * @param  string  $accountNumber  帳號
     * @return string 格式: 銀行代碼-帳號
     */
    protected function formatBankAccount($bankCode, $accountNumber)
    {
        if (empty($bankCode) && empty($accountNumber)) {
            return '';
        }

        if (empty($accountNumber)) {
            return $bankCode;
        }

        return $bankCode.'-'.$accountNumber;
    }

    /**
     * 取得銀行轉帳的標籤
     *
     * @return array
     */
    protected function getBankTransferLabels()
    {
        return [
            OrderRepository::META_BANK_ACCOUNT => __('Account Number', 'woocommerce-omnipay'),
            OrderRepository::META_REMITTANCE_LAST5 => sprintf(
                __('Last %d Digits of Remittance Account', 'woocommerce-omnipay'),
                Constants::REMITTANCE_LAST_DIGITS
            ),
        ];
    }

    /**
     * 取得匯款帳號後5碼表單輸出
     *
     * @param  \WC_Order  $order
     * @return string
     */
    protected function getRemittanceFormOutput($order)
    {
        $template = $this->isCartFlowsThankYouPage()
            ? 'order/remittance-form-cartflows.php'
            : 'order/remittance-form.php';

        return woocommerce_omnipay_get_template($template, [
            'order' => $order,
            'submitted_last5' => $order->get_meta(OrderRepository::META_REMITTANCE_LAST5),
            'submit_url' => WC()->api_request_url($this->id.'_remittance'),
            'last_digits' => Constants::REMITTANCE_LAST_DIGITS,
        ]);
    }

    /**
     * 執行 redirect 並終止程式
     *
     * @param  string  $url
     */
    protected function redirectAndExit($url)
    {
        wp_safe_redirect($url);
        Helper::terminate();
    }
}
