<?php
/**
 * DCA Periods Table Template (Admin)
 *
 * @var string $field_key Field key for the setting
 * @var array $data Field data
 * @var array $periods Existing DCA periods
 */
defined('ABSPATH') || exit;

$defaults = [
    'title' => '',
    'class' => '',
];

$data = wp_parse_args($data, $defaults);
?>
<tr valign="top">
    <th scope="row" class="titledesc"><?php echo wp_kses_post($data['title']); ?></th>
    <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
        <table class="widefat wc_input_table sortable" cellspacing="0" style="width: 600px;">
            <thead>
                <tr>
                    <th class="sort">&nbsp;</th>
                    <th><?php esc_html_e('Period Type (Y/M/D)', 'woocommerce-omnipay'); ?></th>
                    <th><?php esc_html_e('Frequency', 'woocommerce-omnipay'); ?></th>
                    <th><?php esc_html_e('Execute Times', 'woocommerce-omnipay'); ?></th>
                </tr>
            </thead>
            <tbody class="accounts">
                <?php
                $i = -1;
if (! empty($periods) && is_array($periods)) {
    foreach ($periods as $period) {
        $i++;
        echo '<tr class="account">
                            <td class="sort"></td>
                            <td><input type="text" value="'.esc_attr($period['periodType']).'" name="dca_periodType['.$i.']" maxlength="1" required /></td>
                            <td><input type="number" value="'.esc_attr($period['frequency']).'" name="dca_frequency['.$i.']" min="1" max="365" required /></td>
                            <td><input type="number" value="'.esc_attr($period['execTimes']).'" name="dca_execTimes['.$i.']" min="2" max="999" required /></td>
                        </tr>';
    }
} else {
    // Default periods
    echo '<tr class="account">
                        <td class="sort"></td>
                        <td><input type="text" value="M" name="dca_periodType[0]" maxlength="1" required /></td>
                        <td><input type="number" value="1" name="dca_frequency[0]" min="1" max="365" required /></td>
                        <td><input type="number" value="12" name="dca_execTimes[0]" min="2" max="999" required /></td>
                    </tr>';
}
?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4">
                        <a href="#" class="add button"><?php esc_html_e('Add Period', 'woocommerce-omnipay'); ?></a>
                        <a href="#" class="remove_rows button"><?php esc_html_e('Remove Selected', 'woocommerce-omnipay'); ?></a>
                    </th>
                </tr>
            </tfoot>
        </table>
        <script type="text/javascript">
            jQuery(function($) {
                $('#<?php echo esc_js($field_key); ?>').on('click', '.add', function(e) {
                    e.preventDefault();
                    var size = $('#<?php echo esc_js($field_key); ?> tbody .account').length;
                    $('<tr class="account">\
                        <td class="sort"></td>\
                        <td><input type="text" value="M" name="dca_periodType[' + size + ']" maxlength="1" required /></td>\
                        <td><input type="number" value="1" name="dca_frequency[' + size + ']" min="1" max="365" required /></td>\
                        <td><input type="number" value="12" name="dca_execTimes[' + size + ']" min="2" max="999" required /></td>\
                    </tr>').appendTo('#<?php echo esc_js($field_key); ?> table tbody');
                });

                $('#<?php echo esc_js($field_key); ?>').on('click', '.remove_rows', function(e) {
                    e.preventDefault();
                    $('#<?php echo esc_js($field_key); ?> tbody tr').remove();
                });
            });
        </script>
    </td>
</tr>
