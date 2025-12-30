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
<p class="form-row form-row-wide">
    <label><?php esc_html_e('Select Bank Account', 'woocommerce-omnipay'); ?></label>
    <select id="bank_account_index" name="bank_account_index" class="select">
    <?php foreach ($accounts as $index => $account) { ?>
        <?php
        $bankCode = $account['bank_code'] ?? '';
        $accountNumber = $account['account_number'] ?? '';

        // 格式: 銀行代碼-帳號 (例: 822-xxxxxxxx)
        $label = $bankCode;
        if ($accountNumber) {
            $label .= '-'.$accountNumber;
        }
        ?>
        <option value="<?php echo esc_attr($index); ?>"><?php echo esc_html($label); ?></option>
    <?php } ?>
    </select>
</p>
