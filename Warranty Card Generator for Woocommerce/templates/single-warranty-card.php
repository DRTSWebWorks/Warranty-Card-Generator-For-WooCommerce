<?php
if (!defined('ABSPATH')) exit;

get_header();

$plugin  = DRTS_Warranty_Cards::instance();
$card_id = get_the_ID();

if ($plugin && $card_id) {
    echo $plugin->get_card_markup($card_id);
} else {
    echo '<p>' . esc_html__('Гаранционната карта не може да бъде заредена.', 'drts-wc') . '</p>';
}

get_footer();
