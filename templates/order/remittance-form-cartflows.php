<?php
/**
 * Remittance Form Template for CartFlows
 *
 * 顯示匯款帳號後碼輸入表單（CartFlows 樣式）
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-omnipay/order/remittance-form-cartflows.php
 *
 * @var WC_Order $order 訂單
 * @var string $submitted_last5 已填寫的後碼（如有）
 * @var string $submit_url 表單提交 URL
 * @var int $last_digits 帳號後碼位數
 */
defined('ABSPATH') || exit;
?>
<?php if ($submitted_last5) {
    // 已提交時不顯示，由 payment-info-cartflows.php 負責顯示
    return;
} ?>
    <section class="woocommerce-order-details">
        <h2 class="woocommerce-order-details__title"><?php esc_html_e('Remittance Confirmation', 'woocommerce-omnipay'); ?></h2>
        <div class="woocommerce-info" role="status">
            <?php echo sprintf(esc_html__('Please enter the last %d digits of your remittance account after payment to help us confirm the transaction.', 'woocommerce-omnipay'), $last_digits); ?>
        </div>
        <form method="post" action="<?php echo esc_url($submit_url); ?>">
            <input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>">
            <input type="hidden" name="order_key" value="<?php echo esc_attr($order->get_order_key()); ?>">
            <?php wp_nonce_field('omnipay_remittance_nonce', 'nonce'); ?>
            <p class="form-row form-row-wide">
                <label for="remittance_last5"><?php echo sprintf(esc_html__('Last %d Digits of Remittance Account', 'woocommerce-omnipay'), $last_digits); ?> <span class="required">*</span></label>
                <input type="text" id="remittance_last5" name="remittance_last5" class="input-text" maxlength="<?php echo esc_attr($last_digits); ?>" pattern="\d{<?php echo esc_attr($last_digits); ?>}" required>
            </p>
            <p class="form-row">
                <button type="submit" class="button alt"><?php esc_html_e('Submit', 'woocommerce-omnipay'); ?></button>
            </p>
        </form>
    </section>
