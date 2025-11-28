<?php

namespace WooCommerceOmnipay\Tests\WooCommerce;

use WP_UnitTestCase;

/**
 * Admin Settings Tests
 *
 * 測試管理後台 Gateway 設定功能，包含表單渲染、設定儲存、啟用/停用
 */
class AdminSettingsTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 確保 WooCommerce payment gateways 已初始化
        if (empty(WC()->payment_gateways()->payment_gateways)) {
            WC()->payment_gateways()->init();
        }
    }

    /**
     * 測試：Gateway 預設有基本欄位
     */
    public function test_gateway_has_basic_fields()
    {
        $gateway = WC()->payment_gateways->payment_gateways()['omnipay_ecpay'];
        $form_fields = $gateway->form_fields;

        // 驗證基本欄位
        $this->assertArrayHasKey('enabled', $form_fields);
        $this->assertArrayHasKey('title', $form_fields);
        $this->assertArrayHasKey('description', $form_fields);
        $this->assertArrayHasKey('allow_resubmit', $form_fields);
        $this->assertArrayHasKey('transaction_id_prefix', $form_fields);
    }

    // ==================== 設定測試 ====================

    /**
     * 測試：表單欄位可以安全渲染且結構正確
     */
    public function test_form_fields_are_valid_and_renderable()
    {
        $gateway = WC()->payment_gateways->payment_gateways()['omnipay_dummy'];

        // 1. 測試表單可以安全渲染
        ob_start();
        try {
            $gateway->admin_options();
            $output = ob_get_clean();

            $this->assertIsString($output);
            $this->assertNotEmpty($output);
            // Dummy gateway 只驗證有輸出即可
        } catch (\Exception $e) {
            ob_end_clean();
            $this->fail('Form rendering failed: '.$e->getMessage());
        }

        // 2. 測試所有欄位值都是有效類型
        foreach ($gateway->form_fields as $key => $field) {
            // 檢查 default 值
            if (isset($field['default'])) {
                $this->assertTrue(
                    is_string($field['default']) || is_numeric($field['default']),
                    sprintf('Field "%s" default must be string/numeric, got: %s', $key, gettype($field['default']))
                );
            }

            // 檢查字串欄位
            foreach (['description', 'title', 'label'] as $string_field) {
                if (isset($field[$string_field])) {
                    $this->assertIsString(
                        $field[$string_field],
                        sprintf('Field "%s" %s must be string', $key, $string_field)
                    );
                }
            }

            // 3. 檢查 select 欄位結構
            if ($field['type'] === 'select') {
                $this->assertArrayHasKey('options', $field, sprintf('Select field "%s" must have options', $key));
                $this->assertIsArray($field['options'], sprintf('Field "%s" options must be array', $key));

                foreach ($field['options'] as $option_key => $option_value) {
                    $this->assertTrue(
                        is_string($option_key) || is_numeric($option_key),
                        sprintf('Field "%s" option key must be string/numeric', $key)
                    );
                    $this->assertIsString(
                        $option_value,
                        sprintf('Field "%s" option value must be string', $key)
                    );
                }
            }
        }
    }

    /**
     * 測試：模擬表單提交並驗證設定被儲存
     */
    public function test_settings_can_be_saved_via_form_submission()
    {
        $gateway = WC()->payment_gateways->payment_gateways()['omnipay_ecpay'];

        // 清除現有設定
        delete_option('woocommerce_'.$gateway->id.'_settings');

        // 模擬 POST 資料（只有基本欄位，不包含 Omnipay 參數）
        $_POST = [
            'woocommerce_'.$gateway->id.'_enabled' => 'yes',
            'woocommerce_'.$gateway->id.'_title' => 'Test ECPay',
            'woocommerce_'.$gateway->id.'_description' => 'Test Description',
            'woocommerce_'.$gateway->id.'_transaction_id_prefix' => 'TEST_',
        ];

        // 執行儲存（WooCommerce 會調用 process_admin_options）
        $gateway->process_admin_options();

        // 重新載入 gateway 以讀取儲存的設定
        $reloaded_gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'ecpay',
            'title' => 'ECPay',
            'description' => '綠界金流',
            'gateway' => 'ECPay',
        ]);

        // 驗證設定已儲存
        $this->assertEquals('yes', $reloaded_gateway->get_option('enabled'));
        $this->assertEquals('Test ECPay', $reloaded_gateway->get_option('title'));
        $this->assertEquals('Test Description', $reloaded_gateway->get_option('description'));
        $this->assertEquals('TEST_', $reloaded_gateway->get_option('transaction_id_prefix'));

        // 清理
        $_POST = [];
        delete_option('woocommerce_'.$gateway->id.'_settings');
    }

    /**
     * 測試：checkbox 欄位正確儲存
     */
    public function test_checkbox_fields_save_correctly()
    {
        $gateway = WC()->payment_gateways->payment_gateways()['omnipay_ecpay'];

        // 清除現有設定
        delete_option('woocommerce_'.$gateway->id.'_settings');

        // 測試勾選的情況（使用 allow_resubmit 欄位）
        $_POST = [
            'woocommerce_'.$gateway->id.'_allow_resubmit' => 'yes',
        ];

        $gateway->process_admin_options();
        $reloaded_gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'ecpay',
            'gateway' => 'ECPay',
        ]);
        $this->assertEquals('yes', $reloaded_gateway->get_option('allow_resubmit'));

        // 測試未勾選的情況（checkbox 未勾選時不會出現在 POST 資料中）
        $_POST = [];

        $gateway->process_admin_options();
        $reloaded_gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'ecpay',
            'gateway' => 'ECPay',
        ]);
        $this->assertEquals('no', $reloaded_gateway->get_option('allow_resubmit'));

        // 清理
        $_POST = [];
        delete_option('woocommerce_'.$gateway->id.'_settings');
    }

    /**
     * 測試：Gateway 在結帳時可用
     */
    public function test_gateway_is_available_at_checkout()
    {
        // 啟用 Gateway
        update_option('woocommerce_omnipay_dummy_settings', [
            'enabled' => 'yes',
        ]);

        // 重新載入 payment gateways
        WC()->payment_gateways()->init();

        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

        // 驗證 Dummy Gateway 在可用列表中
        $this->assertArrayHasKey('omnipay_dummy', $available_gateways);
    }

    /**
     * 測試：Gateway 可以被停用
     */
    public function test_gateway_can_be_disabled()
    {
        // 停用 Gateway
        update_option('woocommerce_omnipay_dummy_settings', [
            'enabled' => 'no',
        ]);

        // 重新載入 payment gateways
        WC()->payment_gateways()->init();

        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

        // 驗證 Dummy Gateway 不在可用列表中
        $this->assertArrayNotHasKey('omnipay_dummy', $available_gateways);
    }

    // ==================== 設定優先順序測試 ====================

    /**
     * 測試：Gateway 設定優先於共用設定
     */
    public function test_gateway_settings_override_shared_settings()
    {
        // 共用設定
        update_option('woocommerce_omnipay_ecpay_shared_settings', [
            'MerchantID' => 'shared_merchant',
            'HashKey' => 'shared_key',
            'HashIV' => 'shared_iv',
        ]);

        // Gateway 設定覆蓋 MerchantID
        update_option('woocommerce_omnipay_ecpay_settings', [
            'MerchantID' => 'gateway_merchant',
        ]);

        $gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'ecpay',
            'gateway' => 'ECPay',
        ]);
        $omnipayGateway = $gateway->get_gateway();

        // Gateway 設定優先
        $this->assertEquals('gateway_merchant', $omnipayGateway->getMerchantID());
        // 未覆蓋的使用共用設定
        $this->assertEquals('shared_key', $omnipayGateway->getHashKey());
        $this->assertEquals('shared_iv', $omnipayGateway->getHashIV());
    }

    /**
     * 測試：共用設定作為 fallback
     */
    public function test_shared_settings_used_as_fallback()
    {
        // 只有共用設定
        update_option('woocommerce_omnipay_ecpay_shared_settings', [
            'MerchantID' => 'shared_merchant',
            'HashKey' => 'shared_key',
            'HashIV' => 'shared_iv',
        ]);

        // Gateway 沒有設定
        delete_option('woocommerce_omnipay_ecpay_settings');

        $gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'ecpay',
            'gateway' => 'ECPay',
        ]);
        $omnipayGateway = $gateway->get_gateway();

        $this->assertEquals('shared_merchant', $omnipayGateway->getMerchantID());
        $this->assertEquals('shared_key', $omnipayGateway->getHashKey());
        $this->assertEquals('shared_iv', $omnipayGateway->getHashIV());
    }

    /**
     * 測試：預設不顯示 Omnipay 參數欄位
     */
    public function test_omnipay_fields_hidden_by_default()
    {
        // 使用預設 config（override_settings 未設定或為 false）
        $gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'test_hidden',
            'gateway' => 'ECPay',
        ]);

        $form_fields = $gateway->form_fields;

        // 不應該有 Omnipay 參數欄位
        $this->assertArrayNotHasKey('MerchantID', $form_fields);
        $this->assertArrayNotHasKey('HashKey', $form_fields);
        $this->assertArrayNotHasKey('HashIV', $form_fields);

        // 但應該有基本欄位
        $this->assertArrayHasKey('enabled', $form_fields);
        $this->assertArrayHasKey('title', $form_fields);
        $this->assertArrayHasKey('description', $form_fields);
    }

    /**
     * 測試：override_settings = true 時顯示 Omnipay 參數欄位
     */
    public function test_omnipay_fields_shown_when_override_settings_enabled()
    {
        $gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'test_shown',
            'gateway' => 'ECPay',
            'override_settings' => true,
        ]);

        $form_fields = $gateway->form_fields;

        // 應該有 Omnipay 參數欄位
        $this->assertArrayHasKey('MerchantID', $form_fields);
        $this->assertArrayHasKey('HashKey', $form_fields);
        $this->assertArrayHasKey('HashIV', $form_fields);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        delete_option('woocommerce_omnipay_dummy_settings');
        delete_option('woocommerce_omnipay_ecpay_settings');
        delete_option('woocommerce_omnipay_ecpay_shared_settings');
        parent::tearDown();
    }
}
