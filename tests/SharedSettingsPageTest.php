<?php

namespace WooCommerceOmnipay\Tests;

use WooCommerceOmnipay\SharedSettingsPage;
use WP_UnitTestCase;

class SharedSettingsPageTest extends WP_UnitTestCase
{
    private $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = new SharedSettingsPage([
            ['gateway' => 'ECPay'],
            ['gateway' => 'NewebPay'],
        ]);
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_omnipay_ecpay_shared_settings');
        delete_option('woocommerce_omnipay_newebpay_shared_settings');
        delete_option('woocommerce_omnipay_banktransfer_shared_settings');
        parent::tearDown();
    }

    public function test_adds_omnipay_tab_to_woocommerce_settings()
    {
        $tabs = $this->page->add_tab([]);

        $this->assertArrayHasKey('omnipay', $tabs);
        $this->assertEquals('Omnipay', $tabs['omnipay']);
    }

    public function test_get_sections_returns_general_and_all_gateways()
    {
        $sections = $this->page->get_sections();

        $this->assertArrayHasKey('', $sections);
        $this->assertArrayHasKey('ecpay', $sections);
        $this->assertArrayHasKey('newebpay', $sections);
        $this->assertEquals('General Settings', $sections['']);
        $this->assertEquals('ECPay', $sections['ecpay']);
        $this->assertEquals('NewebPay', $sections['newebpay']);
    }

    public function test_get_settings_returns_omnipay_fields_for_ecpay()
    {
        $settings = $this->page->get_settings('ecpay');

        $fieldIds = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[MerchantID]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[HashKey]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[HashIV]', $fieldIds);
    }

    public function test_get_settings_for_gateway_excludes_general_fields()
    {
        $settings = $this->page->get_settings('ecpay');

        $fieldIds = array_column($settings, 'id');

        // 這些欄位應該只在通用設定中，不應該在個別 gateway 設定中
        $this->assertNotContains('woocommerce_omnipay_ecpay_shared_settings[testMode]', $fieldIds);
        $this->assertNotContains('woocommerce_omnipay_ecpay_shared_settings[transaction_id_prefix]', $fieldIds);
        $this->assertNotContains('woocommerce_omnipay_ecpay_shared_settings[allow_resubmit]', $fieldIds);
    }

    public function test_get_settings_returns_empty_for_unknown_section()
    {
        $settings = $this->page->get_settings('unknown');

        $this->assertEmpty($settings);
    }

    public function test_first_section_is_general()
    {
        $sections = $this->page->get_sections();

        $firstKey = array_key_first($sections);
        $this->assertEquals('', $firstKey);
    }

    public function test_get_settings_returns_general_settings()
    {
        $settings = $this->page->get_settings('');

        $fieldIds = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_general_settings[testMode]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_general_settings[transaction_id_prefix]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_general_settings[allow_resubmit]', $fieldIds);
    }

    public function test_get_settings_for_banktransfer_includes_all_fields()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'BankTransfer'],
        ]);

        $settings = $page->get_settings('banktransfer');

        $fieldIds = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_banktransfer_shared_settings[bank_accounts]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_banktransfer_shared_settings[selection_mode]', $fieldIds);
        // secret 現在是 bank_accounts 表格內的欄位，不是獨立欄位
        $this->assertNotContains('woocommerce_omnipay_banktransfer_shared_settings[secret]', $fieldIds);
    }

    public function test_bank_accounts_field_uses_table_type()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'BankTransfer'],
        ]);

        $settings = $page->get_settings('banktransfer');

        $bankAccountsField = null;
        foreach ($settings as $field) {
            if (isset($field['id']) && strpos($field['id'], '[bank_accounts]') !== false) {
                $bankAccountsField = $field;
                break;
            }
        }

        $this->assertNotNull($bankAccountsField);
        $this->assertEquals('bank_accounts_table', $bankAccountsField['type']);
    }

    public function test_save_bank_accounts_table_saves_to_shared_settings()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'BankTransfer'],
        ]);
        $page->register();

        // 模擬 POST 資料
        $_POST['woocommerce_omnipay_banktransfer_shared_settings'] = [
            'bank_accounts' => [
                0 => [
                    'bank_code' => '812',
                    'account_number' => '1234567890',
                    'secret' => 'test123',
                ],
                1 => [
                    'bank_code' => '822',
                    'account_number' => '0987654321',
                    'secret' => 'test456',
                ],
            ],
        ];

        // 觸發儲存
        $page->save_bank_accounts_table([
            'id' => 'woocommerce_omnipay_banktransfer_shared_settings[bank_accounts]',
            'type' => 'bank_accounts_table',
        ]);

        // 驗證儲存結果
        $savedSettings = get_option('woocommerce_omnipay_banktransfer_shared_settings', []);

        $this->assertArrayHasKey('bank_accounts', $savedSettings);
        $this->assertCount(2, $savedSettings['bank_accounts']);
        $this->assertEquals('812', $savedSettings['bank_accounts'][0]['bank_code']);
        $this->assertEquals('1234567890', $savedSettings['bank_accounts'][0]['account_number']);
        $this->assertEquals('822', $savedSettings['bank_accounts'][1]['bank_code']);
    }

    public function test_save_bank_accounts_table_filters_empty_accounts()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'BankTransfer'],
        ]);
        $page->register();

        $_POST['woocommerce_omnipay_banktransfer_shared_settings'] = [
            'bank_accounts' => [
                0 => [
                    'bank_code' => '812',
                    'account_number' => '1234567890',
                    'secret' => '',
                ],
                1 => [
                    'bank_code' => '',
                    'account_number' => '',
                    'secret' => '',
                ],
            ],
        ];

        $page->save_bank_accounts_table([
            'id' => 'woocommerce_omnipay_banktransfer_shared_settings[bank_accounts]',
            'type' => 'bank_accounts_table',
        ]);

        $savedSettings = get_option('woocommerce_omnipay_banktransfer_shared_settings', []);

        // 空帳號應被過濾
        $this->assertCount(1, $savedSettings['bank_accounts']);
        $this->assertEquals('812', $savedSettings['bank_accounts'][0]['bank_code']);
    }

    public function test_output_bank_accounts_table_reads_from_shared_settings()
    {
        // 預先儲存資料
        update_option('woocommerce_omnipay_banktransfer_shared_settings', [
            'bank_accounts' => [
                [
                    'bank_code' => '812',
                    'account_number' => '1234567890',
                    'secret' => 'test123',
                ],
            ],
            'selection_mode' => 'random',
        ]);

        $page = new SharedSettingsPage([
            ['gateway' => 'BankTransfer'],
        ]);
        $page->register();

        // 捕獲輸出
        ob_start();
        $page->output_bank_accounts_table([
            'id' => 'woocommerce_omnipay_banktransfer_shared_settings[bank_accounts]',
            'type' => 'bank_accounts_table',
            'title' => 'Bank Accounts',
            'default' => [],
        ]);
        $output = ob_get_clean();

        // 驗證輸出包含儲存的資料
        $this->assertStringContainsString('812', $output);
        $this->assertStringContainsString('1234567890', $output);
    }

    public function test_settings_manager_reads_selection_mode_from_shared_settings()
    {
        // 預先儲存資料
        update_option('woocommerce_omnipay_banktransfer_shared_settings', [
            'bank_accounts' => [
                ['bank_code' => '812', 'account_number' => '123', 'secret' => ''],
            ],
            'selection_mode' => 'user_choice',
        ]);

        $settingsManager = new \WooCommerceOmnipay\WordPress\SettingsManager('BankTransfer');
        $settings = $settingsManager->getAllSettings();

        $this->assertEquals('user_choice', $settings['selection_mode']);
        $this->assertCount(1, $settings['bank_accounts']);
    }

    public function test_save_settings_saves_selection_mode_to_shared_settings()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'BankTransfer'],
        ]);
        $page->register();

        // 模擬 POST 資料
        $_GET['section'] = 'banktransfer';
        $_POST['woocommerce_omnipay_banktransfer_shared_settings'] = [
            'selection_mode' => 'user_choice',
        ];

        // 觸發儲存
        $page->save_settings();

        // 驗證儲存結果
        $savedSettings = get_option('woocommerce_omnipay_banktransfer_shared_settings', []);

        $this->assertArrayHasKey('selection_mode', $savedSettings);
        $this->assertEquals('user_choice', $savedSettings['selection_mode']);
    }

    public function test_save_settings_saves_multiple_bank_accounts()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'BankTransfer'],
        ]);
        $page->register();

        $_GET['section'] = 'banktransfer';
        $_POST['woocommerce_omnipay_banktransfer_shared_settings'] = [
            'bank_accounts' => [
                0 => [
                    'bank_code' => '812',
                    'account_number' => '1234567890',
                    'secret' => 'test123',
                ],
                1 => [
                    'bank_code' => '822',
                    'account_number' => '0987654321',
                    'secret' => 'test456',
                ],
            ],
            'selection_mode' => 'user_choice',
        ];

        // 透過 save_settings() 完整流程儲存
        $page->save_settings();

        $savedSettings = get_option('woocommerce_omnipay_banktransfer_shared_settings', []);

        // 驗證多組帳號都有儲存
        $this->assertArrayHasKey('bank_accounts', $savedSettings);
        $this->assertCount(2, $savedSettings['bank_accounts']);
        $this->assertEquals('812', $savedSettings['bank_accounts'][0]['bank_code']);
        $this->assertEquals('822', $savedSettings['bank_accounts'][1]['bank_code']);
        $this->assertEquals('user_choice', $savedSettings['selection_mode']);
    }

    public function test_output_sections_renders_navigation()
    {
        ob_start();
        $this->page->output_sections();
        $output = ob_get_clean();

        // 驗證有 sections 導航
        $this->assertStringContainsString('<ul class="subsubsub">', $output);
        $this->assertStringContainsString('General Settings', $output);
        $this->assertStringContainsString('ECPay', $output);
        $this->assertStringContainsString('NewebPay', $output);
        $this->assertStringContainsString('section=ecpay', $output);
        $this->assertStringContainsString('section=newebpay', $output);
    }

    public function test_output_sections_marks_current_section()
    {
        $_GET['section'] = 'ecpay';

        ob_start();
        $this->page->output_sections();
        $output = ob_get_clean();

        // 驗證 ECPay 有 current class
        $this->assertMatchesRegularExpression('/<a[^>]+section=ecpay[^>]+class="[^"]*current[^"]*"/', $output);
    }

    public function test_output_settings_renders_general_settings_by_default()
    {
        // 確保 WC_Admin_Settings 類別存在
        if (! class_exists('WC_Admin_Settings')) {
            $this->markTestSkipped('WC_Admin_Settings class not available');
        }

        // 預設 section 為空，應該顯示通用設定
        unset($_GET['section']);

        ob_start();
        $this->page->output_settings();
        $output = ob_get_clean();

        // 應該包含通用設定的欄位
        $this->assertStringContainsString('testMode', $output);
        $this->assertStringContainsString('transaction_id_prefix', $output);
    }

    public function test_output_settings_renders_gateway_settings_for_section()
    {
        // 確保 WC_Admin_Settings 類別存在
        if (! class_exists('WC_Admin_Settings')) {
            $this->markTestSkipped('WC_Admin_Settings class not available');
        }

        $_GET['section'] = 'ecpay';

        ob_start();
        $this->page->output_settings();
        $output = ob_get_clean();

        // 應該包含 ECPay 的欄位
        $this->assertStringContainsString('MerchantID', $output);
        $this->assertStringContainsString('HashKey', $output);
    }

    public function test_get_settings_creates_password_field_for_secret_keys()
    {
        $settings = $this->page->get_settings('ecpay');

        $hashKeyField = null;
        $hashIVField = null;

        foreach ($settings as $field) {
            if (isset($field['id']) && strpos($field['id'], '[HashKey]') !== false) {
                $hashKeyField = $field;
            }
            if (isset($field['id']) && strpos($field['id'], '[HashIV]') !== false) {
                $hashIVField = $field;
            }
        }

        // HashKey 和 HashIV 應該是 password 類型
        $this->assertNotNull($hashKeyField);
        $this->assertNotNull($hashIVField);
        $this->assertEquals('password', $hashKeyField['type']);
        $this->assertEquals('password', $hashIVField['type']);
    }

    public function test_get_settings_creates_text_field_for_merchant_id()
    {
        $settings = $this->page->get_settings('ecpay');

        $merchantIdField = null;
        foreach ($settings as $field) {
            if (isset($field['id']) && strpos($field['id'], '[MerchantID]') !== false) {
                $merchantIdField = $field;
                break;
            }
        }

        $this->assertNotNull($merchantIdField);
        $this->assertEquals('text', $merchantIdField['type']);
    }

    public function test_duplicate_gateways_are_deduplicated()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'ECPay'],
            ['gateway' => 'ECPay'], // 重複
            ['gateway' => 'NewebPay'],
        ]);

        $sections = $page->get_sections();

        // 應該只有 General + ECPay + NewebPay = 3 個 sections
        $this->assertCount(3, $sections);
    }

    public function test_save_settings_handles_checkbox_field()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'ECPay'],
        ]);
        $page->register();

        $_GET['section'] = '';
        $_POST['woocommerce_omnipay_general_settings'] = [
            'testMode' => 'yes',
            'allow_resubmit' => '1', // checkbox 可能是 '1' 或 'yes'
        ];

        $page->save_settings();

        $savedSettings = get_option('woocommerce_omnipay_general_settings', []);

        $this->assertEquals('yes', $savedSettings['testMode']);
        $this->assertEquals('yes', $savedSettings['allow_resubmit']);
    }

    public function test_output_bank_accounts_table_handles_json_string()
    {
        // 以 JSON 字串格式儲存帳號
        update_option('woocommerce_omnipay_banktransfer_shared_settings', [
            'bank_accounts' => '[{"bank_code": "812", "account_number": "1234567890"}]',
        ]);

        $page = new SharedSettingsPage([
            ['gateway' => 'BankTransfer'],
        ]);
        $page->register();

        ob_start();
        $page->output_bank_accounts_table([
            'id' => 'woocommerce_omnipay_banktransfer_shared_settings[bank_accounts]',
            'type' => 'bank_accounts_table',
            'title' => 'Bank Accounts',
            'default' => [],
        ]);
        $output = ob_get_clean();

        // 驗證能正確解析 JSON 字串
        $this->assertStringContainsString('812', $output);
        $this->assertStringContainsString('1234567890', $output);
    }

    public function test_save_bank_accounts_table_skips_invalid_option_id()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'BankTransfer'],
        ]);
        $page->register();

        // 先確認選項不存在
        delete_option('invalid_format_without_brackets');

        // 傳入無效的 option ID 格式
        $page->save_bank_accounts_table([
            'id' => 'invalid_format_without_brackets',
            'type' => 'bank_accounts_table',
        ]);

        // 應該不會建立任何選項
        $this->assertFalse(get_option('invalid_format_without_brackets'));
    }
}
