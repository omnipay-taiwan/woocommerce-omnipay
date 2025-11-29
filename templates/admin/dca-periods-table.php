<?php
/**
 * DCA Periods Table Template (Admin) - Unified for all gateways
 *
 * @var string $field_key Field key for the setting
 * @var array $data Field data
 * @var array $periods Existing DCA periods
 * @var array $headers Column headers (e.g., ['Period Type (Y/M/D)', 'Frequency', 'Execute Times'])
 * @var array $field_configs Field configuration array with field names and attributes
 * @var string $field_prefix Field name prefix (e.g., 'dca_' or 'newebpay_dca_')
 * @var array $default_period Default period data for new rows
 * @var int $table_width Table width in pixels
 */
defined('ABSPATH') || exit;

$defaults = [
    'title' => '',
    'class' => '',
];

$data = wp_parse_args($data, $defaults);

/**
 * Render a single row
 */
$render_row = function ($index, $period) use ($field_configs, $field_prefix) {
    ?>
    <tr class="account">
        <td class="sort"></td>
        <?php foreach ($field_configs as $config) { ?>
            <td>
                <input
                    type="<?php echo esc_attr($config['type']); ?>"
                    value="<?php echo esc_attr($period[$config['name']] ?? $config['default']); ?>"
                    name="<?php echo esc_attr($field_prefix.$config['name']); ?>[<?php echo esc_attr($index); ?>]"
                    <?php foreach ($config['attributes'] as $attr => $value) { ?>
                        <?php echo esc_attr($attr); ?>="<?php echo esc_attr($value); ?>"
                    <?php } ?>
                />
            </td>
        <?php } ?>
    </tr>
    <?php
};

// Prepare periods data
$periods_to_render = ! empty($periods) && is_array($periods) ? $periods : [$default_period];
$colspan = count($headers) + 1; // +1 for sort column
?>
<tr valign="top">
    <th scope="row" class="titledesc"><?php echo wp_kses_post($data['title']); ?></th>
    <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
        <table class="widefat wc_input_table sortable" cellspacing="0" style="width: <?php echo esc_attr($table_width); ?>px;">
            <thead>
                <tr>
                    <th class="sort">&nbsp;</th>
                    <?php foreach ($headers as $header) { ?>
                        <th><?php echo esc_html($header); ?></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody class="accounts">
                <?php foreach ($periods_to_render as $i => $period) { ?>
                    <?php $render_row($i, $period); ?>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="<?php echo esc_attr($colspan); ?>">
                        <a href="#" class="add button"><?php esc_html_e('Add Period', 'woocommerce-omnipay'); ?></a>
                        <a href="#" class="remove_rows button"><?php esc_html_e('Remove Selected', 'woocommerce-omnipay'); ?></a>
                    </th>
                </tr>
            </tfoot>
        </table>

        <!-- JavaScript template for new rows -->
        <script type="text/template" id="<?php echo esc_attr($field_key); ?>-row-template">
            <?php $render_row('{{INDEX}}', $default_period); ?>
        </script>

        <!-- JavaScript for add/remove functionality -->
        <script type="text/javascript">
            jQuery(function($) {
                $('#<?php echo esc_js($field_key); ?>').on('click', '.add', function(e) {
                    e.preventDefault();
                    var size = $('#<?php echo esc_js($field_key); ?> tbody .account').length;
                    var template = $('#<?php echo esc_js($field_key); ?>-row-template').html();
                    var newRow = template.replace(/\{\{INDEX\}\}/g, size);
                    $(newRow).appendTo('#<?php echo esc_js($field_key); ?> table tbody');
                });

                $('#<?php echo esc_js($field_key); ?>').on('click', '.remove_rows', function(e) {
                    e.preventDefault();
                    $('#<?php echo esc_js($field_key); ?> tbody tr').remove();
                });
            });
        </script>
    </td>
</tr>
