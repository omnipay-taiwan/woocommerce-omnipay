<?php
/**
 * DCA (定期定額) Form Template - Unified for all gateways
 *
 * @var array $periods Available DCA periods
 * @var float $total Order total amount
 * @var string $warning_message Warning message to display
 * @var array $period_type_labels Period type labels (e.g., ['Y' => 'year', 'M' => 'month', 'W' => 'week', 'D' => 'day'])
 * @var array $period_fields Period field names (e.g., ['periodType', 'frequency', 'execTimes'] for ECPay)
 */
defined('ABSPATH') || exit;

/**
 * Build period value string from period data
 */
$build_period_value = function ($period) use ($period_fields) {
    $values = [];
    foreach ($period_fields as $field) {
        $values[] = $period[$field] ?? '';
    }

    return implode('_', $values);
};

/**
 * Build period label based on period structure
 */
$build_period_label = function ($period, $total, $labels) use ($period_fields) {
    // ECPay format: has 'frequency' field
    if (in_array('frequency', $period_fields)) {
        return sprintf(
            __('%s / %s %s, up to a maximum of %s', 'woocommerce-omnipay'),
            wc_price($total),
            $period['frequency'],
            $labels[$period['periodType']] ?? $period['periodType'],
            $period['execTimes']
        );
    }

    // NewebPay format: has 'periodTimes' field
    return sprintf(
        __('%s / %s, up to a maximum of %s', 'woocommerce-omnipay'),
        wc_price($total),
        $labels[$period['periodType']] ?? $period['periodType'],
        $period['periodTimes']
    );
};
?>
<select id="omnipay_dca_period" name="omnipay_dca_period">
<?php foreach ($periods as $period) { ?>
    <?php
    $value = $build_period_value($period);
    $label = $build_period_label($period, $total, $period_type_labels);
    ?>
    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
<?php } ?>
</select>
<div id="omnipay_dca_show"></div>
<hr style="margin: 12px 0px;background-color: #eeeeee;">
<p style="font-size: 0.8em;color: #c9302c;">
    <?php echo wp_kses_post($warning_message); ?>
</p>
