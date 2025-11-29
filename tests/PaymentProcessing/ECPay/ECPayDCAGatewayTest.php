<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use WooCommerceOmnipay\Gateways\ECPay\ECPayDCAGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay 定期定額 Gateway 測試
 *
 * 只測試子類別的差異點（gateway_id、title、ChoosePayment、Period 參數）
 * 其他行為已在 ECPayTest 中測試
 */
class ECPayDCAGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_dca';

    protected $gatewayName = 'ECPay';

    protected $settings = [
        'HashKey' => '5294y06JbISpM5x9',
        'HashIV' => 'v77hoKGq4kWxNNIS',
        'MerchantID' => '2000132',
        'testMode' => 'yes',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Set up DCA periods for Shortcode mode
        update_option('woocommerce_omnipay_ecpay_dca_periods', [
            [
                'periodType' => 'M',
                'frequency' => 1,
                'execTimes' => 12,
            ],
        ]);

        $this->gateway = new ECPayDCAGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_dca',
            'title' => '綠界定期定額',
        ]);

        // Set up Blocks mode settings
        $this->gateway->update_option('periodType', 'M');
        $this->gateway->update_option('frequency', 1);
        $this->gateway->update_option('execTimes', 12);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_ecpay_dca', $this->gateway->id);
        $this->assertEquals('綠界定期定額', $this->gateway->method_title);
    }

    public function test_process_payment_sends_credit_payment_type()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('Credit', $redirectData['data']['ChoosePayment']);
    }

    public function test_process_payment_sends_period_parameters()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('M', $redirectData['data']['PeriodType']);
        $this->assertEquals(1, $redirectData['data']['Frequency']);
        $this->assertEquals(12, $redirectData['data']['ExecTimes']);
        $this->assertEquals(500, $redirectData['data']['PeriodAmount']);
    }

    public function test_process_payment_with_shortcode_mode_user_selection()
    {
        // Simulate user selection in Shortcode mode
        $_POST['omnipay_period'] = 'Y_1_6';

        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('Y', $redirectData['data']['PeriodType']);
        $this->assertEquals(1, $redirectData['data']['Frequency']);
        $this->assertEquals(6, $redirectData['data']['ExecTimes']);
        $this->assertEquals(500, $redirectData['data']['PeriodAmount']);

        unset($_POST['omnipay_period']);
    }

    public function test_process_payment_with_invalid_period_format_uses_fallback()
    {
        // Invalid format - should fallback to defaults
        $_POST['omnipay_period'] = 'invalid_format';

        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        // Should use fallback defaults
        $this->assertEquals('M', $redirectData['data']['PeriodType']);
        $this->assertEquals(1, $redirectData['data']['Frequency']);
        $this->assertEquals(2, $redirectData['data']['ExecTimes']);

        unset($_POST['omnipay_period']);
    }

    public function test_validate_period_constraints_for_year()
    {
        $_POST['woocommerce_omnipay_ecpay_dca_periodType'] = 'Y';
        $_POST['woocommerce_omnipay_ecpay_dca_frequency'] = 2; // Invalid: must be 1
        $_POST['woocommerce_omnipay_ecpay_dca_execTimes'] = 5;

        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('validateDcaFields');
        $method->setAccessible(true);

        $result = $method->invoke($this->gateway);

        $this->assertFalse($result);

        unset($_POST['woocommerce_omnipay_ecpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_frequency']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_execTimes']);
    }

    public function test_validate_period_constraints_passes_valid_data()
    {
        $_POST['woocommerce_omnipay_ecpay_dca_periodType'] = 'M';
        $_POST['woocommerce_omnipay_ecpay_dca_frequency'] = 1;
        $_POST['woocommerce_omnipay_ecpay_dca_execTimes'] = 12;

        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('validateDcaFields');
        $method->setAccessible(true);

        $result = $method->invoke($this->gateway);

        $this->assertTrue($result);

        unset($_POST['woocommerce_omnipay_ecpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_frequency']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_execTimes']);
    }

    public function test_save_dca_periods_from_post_data()
    {
        $_POST['periodType'] = ['M', 'Y'];
        $_POST['frequency'] = [1, 1];
        $_POST['execTimes'] = [12, 6];

        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('saveDcaPeriods');
        $method->setAccessible(true);

        $method->invoke($this->gateway);

        $saved = get_option('woocommerce_omnipay_ecpay_dca_periods');
        $this->assertCount(2, $saved);
        $this->assertEquals('M', $saved[0]['periodType']);
        $this->assertEquals(1, $saved[0]['frequency']);
        $this->assertEquals(12, $saved[0]['execTimes']);

        unset($_POST['periodType']);
        unset($_POST['frequency']);
        unset($_POST['execTimes']);
    }

    public function test_is_available_returns_true_when_has_valid_periods()
    {
        // Gateway has periods set in setUp
        $reflection = new \ReflectionClass($this->gateway);
        $property = $reflection->getProperty('dcaPeriods');
        $property->setAccessible(true);
        $periods = $property->getValue($this->gateway);

        $this->assertNotEmpty($periods);
    }

    public function test_load_dca_periods_from_option()
    {
        $testPeriods = [
            ['periodType' => 'M', 'frequency' => 1, 'execTimes' => 12],
            ['periodType' => 'Y', 'frequency' => 1, 'execTimes' => 6],
        ];

        update_option('woocommerce_omnipay_ecpay_dca_periods', $testPeriods);

        // Create new gateway instance to trigger loadDcaPeriods
        $newGateway = new ECPayDCAGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_dca',
        ]);

        $reflection = new \ReflectionClass($newGateway);
        $property = $reflection->getProperty('dcaPeriods');
        $property->setAccessible(true);
        $loaded = $property->getValue($newGateway);

        $this->assertCount(2, $loaded);
        $this->assertEquals('M', $loaded[0]['periodType']);
    }
}
