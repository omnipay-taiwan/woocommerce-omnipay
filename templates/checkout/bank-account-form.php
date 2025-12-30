<?php
/**
 * Bank Account Selection Form Template
 *
 * @var array $accounts Available bank accounts
 */
defined('ABSPATH') || exit;

if (empty($accounts)) {
    return;
}
?>
<div class="form-row form-row-wide omnipay-bank-account-selection">
    <label><?php esc_html_e('Select Bank Account', 'woocommerce-omnipay'); ?></label>
    <ul class="omnipay-bank-accounts-list">
    <?php foreach ($accounts as $index => $account) { ?>
        <?php
        $bankCode = $account['bank_code'] ?? '';
        $accountNumber = $account['account_number'] ?? '';

        // 格式: 銀行代碼-帳號 (例: 822-xxxxxxxx)
        $label = $bankCode;
        if ($accountNumber) {
            $label .= '-'.$accountNumber;
        }
        $inputId = 'bank_account_'.$index;
        ?>
        <li>
            <input type="radio"
                   id="<?php echo esc_attr($inputId); ?>"
                   name="bank_account_index"
                   value="<?php echo esc_attr($index); ?>"
                   <?php checked($index, 0); ?> />
            <label for="<?php echo esc_attr($inputId); ?>"><?php echo esc_html($label); ?></label>
        </li>
    <?php } ?>
    </ul>
</div>
