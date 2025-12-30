<?php
/**
 * Bank Accounts List Template (user_choice mode)
 *
 * Shows all available bank accounts for the customer to choose from.
 * Uses WooCommerce BACS standard HTML structure.
 *
 * @var array $accounts Available bank accounts
 * @var bool $plainText Whether to output plain text (for emails)
 */
defined('ABSPATH') || exit;

if (empty($accounts)) {
    return;
}

if ($plainText) {
    // 純文字模式（Email）
    echo esc_html__('Bank Details', 'woocommerce-omnipay')."\n\n";
    foreach ($accounts as $account) {
        $bankCode = $account['bank_code'] ?? '';
        $accountNumber = $account['account_number'] ?? '';
        if ($bankCode) {
            echo esc_html__('Bank Code', 'woocommerce-omnipay').': '.$bankCode."\n";
        }
        if ($accountNumber) {
            echo esc_html__('Account Number', 'woocommerce-omnipay').': '.$accountNumber."\n";
        }
        echo "\n";
    }

    return;
}
?>
<section class="woocommerce-bacs-bank-details">
    <h2 class="wc-bacs-bank-details-heading"><?php esc_html_e('Bank Details', 'woocommerce-omnipay'); ?></h2>
    <?php foreach ($accounts as $index => $account) { ?>
        <?php
        $bankCode = $account['bank_code'] ?? '';
        $accountNumber = $account['account_number'] ?? '';
        ?>
        <ul class="wc-bacs-bank-details order_details bacs_details">
            <?php if ($bankCode) { ?>
                <li class="bank_code">
                    <?php esc_html_e('Bank Code', 'woocommerce-omnipay'); ?>:
                    <strong><?php echo esc_html($bankCode); ?></strong>
                </li>
            <?php } ?>
            <?php if ($accountNumber) { ?>
                <li class="account_number">
                    <?php esc_html_e('Account Number', 'woocommerce-omnipay'); ?>:
                    <strong><?php echo esc_html($accountNumber); ?></strong>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</section>
