<?php
/**
 * Settings Sections Navigation Template (Admin)
 *
 * @var array $sections       Array of section id => label
 * @var string $currentSection Current active section key
 */
defined('ABSPATH') || exit;

if (empty($sections)) {
    return;
}

$firstSection = array_key_first($sections);
?>
<ul class="subsubsub">
<?php
$links = [];
foreach ($sections as $id => $label) {
    $url = admin_url('admin.php?page=wc-settings&tab=omnipay&section='.$id);
    $class = ($currentSection === $id || (empty($currentSection) && $id === $firstSection)) ? 'current' : '';
    $links[] = '<li><a href="'.esc_url($url).'" class="'.esc_attr($class).'">'.esc_html($label).'</a></li>';
}
echo implode(' | ', $links);
?>
</ul>
<br class="clear" />
