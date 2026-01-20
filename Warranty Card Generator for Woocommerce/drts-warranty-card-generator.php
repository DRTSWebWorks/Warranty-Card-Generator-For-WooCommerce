<?php
/**
 * Plugin Name: Warranty Card Generator for Woocommerce
 * Description: Warranty Cards Generation For WooCommerce
 * Author: DRTSWebWorks
 * Author URI: https://drtswebworks.com
 * Version: 1.0.2
 */

if (!defined('ABSPATH')) exit;

class DRTS_Warranty_Cards {

    const CPT           = 'drts_warranty_card';
    const META_PREFIX   = 'drts_wc_';
    const OPTION_GROUP  = 'drts_wc_settings';
    const OPTION_ENABLE = 'drts_wc_enable';

    private static $instance = null;
    private $enabled = true;

    public static function instance() {
        return self::$instance;
    }

    public function __construct() {
        self::$instance = $this;

        $this->enabled = get_option(self::OPTION_ENABLE, 'yes') === 'yes';

        add_action('init',        [$this, 'register_cpt']);
        add_action('admin_init',  [$this, 'register_settings']);
        add_action('admin_menu',  [$this, 'add_settings_page']);

        add_action('woocommerce_order_status_completed', [$this, 'handle_order_completed']);

        add_filter('template_include', [$this, 'maybe_use_template']);

        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'add_my_account_actions'], 10, 2);

        add_action('template_redirect', [$this, 'maybe_output_pdf']);
    }

    /* НАСТРОЙКИ */

    public function register_settings() {

        $text_fields = [
            self::OPTION_ENABLE,
            'drts_wc_company_name',
            'drts_wc_company_id',
            'drts_wc_company_city',
            'drts_wc_company_address',
            'drts_wc_company_phone',
        ];

        foreach ($text_fields as $field) {
            register_setting(self::OPTION_GROUP, $field, [
                'sanitize_callback' => 'sanitize_text_field',
            ]);
        }

        // URL 
        $url_fields = [
            'drts_wc_logo_url',
            'drts_wc_signature_url',
            'drts_wc_badge_url',
        ];

        foreach ($url_fields as $field) {
            register_setting(self::OPTION_GROUP, $field, [
                'sanitize_callback' => 'esc_url_raw',
            ]);
        }

        add_settings_section(
            'drts_wc_main',
            __('Warranty Cards Settings', 'drts-wc'),
            function () {
                echo '<p>' . esc_html__('Control the automatic generation and branding of warranty cards.', 'drts-wc') . '</p>';
            },
            self::OPTION_GROUP
        );

        add_settings_field(
            self::OPTION_ENABLE,
            __('Enable warranty cards', 'drts-wc'),
            function () {
                $value = get_option(self::OPTION_ENABLE, 'yes');
                ?>
                <label>
                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_ENABLE); ?>" value="yes" <?php checked($value, 'yes'); ?>>
                    <?php esc_html_e('Generate warranty cards for completed WooCommerce orders', 'drts-wc'); ?>
                </label>
                <?php
            },
            self::OPTION_GROUP,
            'drts_wc_main'
        );

        $company_fields = [
            'drts_wc_company_name'    => __('Company name', 'drts-wc'),
            'drts_wc_company_id'      => __('Company ID (ЕИК)', 'drts-wc'),
            'drts_wc_company_city'    => __('Company city', 'drts-wc'),
            'drts_wc_company_address' => __('Company address', 'drts-wc'),
            'drts_wc_company_phone'   => __('Company phone', 'drts-wc'),
        ];

        foreach ($company_fields as $key => $label) {
            add_settings_field(
                $key,
                $label,
                function () use ($key) {
                    $val = get_option($key, '');
                    printf(
                        '<input type="text" name="%1$s" value="%2$s" class="regular-text" />',
                        esc_attr($key),
                        esc_attr($val)
                    );
                },
                self::OPTION_GROUP,
                'drts_wc_main'
            );
        }

        // Logo
        add_settings_field(
            'drts_wc_logo_url',
            __('Logo URL', 'drts-wc'),
            function () {
                $val = get_option('drts_wc_logo_url', '');
                printf(
                    '<input type="text" name="drts_wc_logo_url" value="%s" class="regular-text" />
                     <p class="description">%s</p>',
                    esc_attr($val),
                    esc_html__('Paste URL from Media Library (primary logo used on the warranty card).', 'drts-wc')
                );
            },
            self::OPTION_GROUP,
            'drts_wc_main'
        );

        add_settings_field(
            'drts_wc_signature_url',
            __('Signature image URL', 'drts-wc'),
            function () {
                $val = get_option('drts_wc_signature_url', '');
                printf(
                    '<input type="text" name="drts_wc_signature_url" value="%s" class="regular-text" />
                     <p class="description">%s</p>',
                    esc_attr($val),
                    esc_html__('Paste URL of a transparent PNG signature. It will appear above the "Подпис" line.', 'drts-wc')
                );
            },
            self::OPTION_GROUP,
            'drts_wc_main'
        );

        add_settings_field(
            'drts_wc_badge_url',
            __('Warranty Badge Image URL', 'drts-wc'),
            function () {
                $val = get_option('drts_wc_badge_url', '');
                printf(
                    '<input type="text" name="drts_wc_badge_url" value="%s" class="regular-text" placeholder="https://yourdomain.com/uploads/badge.png" />
                     <p class="description">Recommended size: <strong>350x350 PNG</strong>. Avoid WebP (PDF cannot render it).</p>',
                    esc_attr($val)
                );
            },
            self::OPTION_GROUP,
            'drts_wc_main'
        );
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Warranty Cards', 'drts-wc'),
            __('Warranty Cards', 'drts-wc'),
            'manage_woocommerce',
            'drts-warranty-cards',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Warranty Cards', 'drts-wc'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::OPTION_GROUP);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /* В Менюто */

    public function register_cpt() {
        $labels = [
            'name'               => __('Гаранционни карти', 'drts-wc'),
            'singular_name'      => __('Гаранционна карта', 'drts-wc'),
            'add_new'            => __('Добави нова', 'drts-wc'),
            'add_new_item'       => __('Добави нова гаранционна карта', 'drts-wc'),
            'edit_item'          => __('Редакция на гаранционна карта', 'drts-wc'),
            'new_item'           => __('Нова гаранционна карта', 'drts-wc'),
            'view_item'          => __('Разгледай гаранционна карта', 'drts-wc'),
            'search_items'       => __('Търсене в гаранционните карти', 'drts-wc'),
            'not_found'          => __('Няма налични гаранционни карти.', 'drts-wc'),
            'not_found_in_trash' => __('Няма налични гаранционни карти в Кошчето', 'drts-wc'),
            'menu_name'          => __('Гаранционни карти', 'drts-wc'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => false,
            'exclude_from_search'=> true,
            'has_archive'        => false,
            'rewrite'            => [
                'slug'       => 'warranty-card',
                'with_front' => false,
            ],
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'show_in_rest'       => false,
        ];

        register_post_type(self::CPT, $args);
    }


    public function handle_order_completed($order_id) {
        if (!$this->enabled) {
            return;
        }
        if (!function_exists('wc_get_order')) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $existing = get_posts([
                'post_type'   => self::CPT,
                'fields'      => 'ids',
                'numberposts' => 1,
                'meta_query'  => [
                    [
                        'key'   => self::META_PREFIX . 'order_item_id',
                        'value' => $item_id,
                    ],
                ],
            ]);

            if (!empty($existing)) {
                continue;
            }

            $this->create_warranty_card($order, $item_id, $product);
        }
    }

    protected function create_warranty_card($order, $item_id, $product) {
        $order_id    = $order->get_id();
        $product_id  = $product->get_id();
        $sku         = $product->get_sku();
        $product_url = get_permalink($product_id);

        $customer_name = $order->get_formatted_billing_full_name();
        $phone         = $order->get_billing_phone();
        $email         = $order->get_billing_email();
        $city          = $order->get_billing_city();
        $address       = $order->get_billing_address_1();
        $completed_dt  = $order->get_date_completed();
        $start_date    = $completed_dt ? $completed_dt->date_i18n('d.m.Y') : date_i18n('d.m.Y');

        $start_ts = strtotime($start_date);
        $end_ts   = strtotime('+24 months', $start_ts);
        $end_date = date('d.m.Y', $end_ts);

        $model_attr = $product->get_attribute('pa_model');
        $model      = $model_attr ? $model_attr : '';

        $post_title = sprintf(
            __('Warranty Card #%1$s – %2$s', 'drts-wc'),
            $order_id . '-' . $item_id,
            $customer_name
        );

        $card_id = wp_insert_post([
            'post_type'   => self::CPT,
            'post_status' => 'publish',
            'post_title'  => $post_title,
        ]);

        if (!$card_id) return;

        $warranty_number = 'W' . date('Ymd') . '-' . $card_id;

        $meta = [
            'order_id'         => $order_id,
            'order_item_id'    => $item_id,
            'customer_name'    => $customer_name,
            'customer_email'   => $email,
            'customer_phone'   => $phone,
            'customer_city'    => $city,
            'customer_address' => $address,
            'product_id'       => $product_id,
            'product_title'    => $product->get_name(),
            'product_sku'      => $sku,
            'product_model'    => $model,
            'product_url'      => $product_url,
            'start_date'       => $start_date,
            'end_date'         => $end_date,
            'warranty_number'  => $warranty_number,
            'access_token'     => wp_generate_password(16, false),
        ];

        foreach ($meta as $key => $value) {
            update_post_meta($card_id, self::META_PREFIX . $key, $value);
        }
    }

    public function maybe_use_template($template) {
        if (is_singular(self::CPT)) {
            $plugin_template = plugin_dir_path(__FILE__) . 'templates/single-warranty-card.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }


    public function add_my_account_actions($actions, $order) {
        if (!$order->has_status('completed') || !$this->enabled) {
            return $actions;
        }

        $cards = get_posts([
            'post_type'   => self::CPT,
            'numberposts' => -1,
            'meta_query'  => [
                [
                    'key'   => self::META_PREFIX . 'order_id',
                    'value' => $order->get_id(),
                ],
            ],
        ]);

        if (empty($cards)) return $actions;

        foreach ($cards as $card) {
            $url = get_permalink($card->ID);
            $product_title = get_post_meta($card->ID, self::META_PREFIX . 'product_title', true);
            $actions['warranty_' . $card->ID] = [
                'url'  => $url,
                'name' => sprintf(__('Warranty (%s)', 'drts-wc'), $product_title ?: '#'),
            ];
        }

        return $actions;
    }

    /* ХЕЛПЕРИ */

    public function get_card_data($card_id) {
        $fields = [
            'order_id',
            'customer_name',
            'customer_email',
            'customer_phone',
            'customer_city',
            'customer_address',
            'product_title',
            'product_sku',
            'product_model',
            'product_url',
            'start_date',
            'end_date',
            'warranty_number',
            'product_id',
        ];

        $data = [];
        foreach ($fields as $field) {
            $data[$field] = get_post_meta($card_id, self::META_PREFIX . $field, true);
        }
        return $data;
    }

    public function get_company_settings() {
        return [
            'name'    => get_option('drts_wc_company_name', 'БЕБИ ГРУП ЕООД'),
            'id'      => get_option('drts_wc_company_id', '206222651'),
            'city'    => get_option('drts_wc_company_city', 'Пловдив'),
            'address' => get_option('drts_wc_company_address', 'бул. Македония №99А'),
            'phone'   => get_option('drts_wc_company_phone', '0877 873 654'),
            'logo'    => get_option('drts_wc_logo_url', ''),
            'sign'    => get_option('drts_wc_signature_url', ''),
            'badge'   => get_option('drts_wc_badge_url', ''),
        ];
    }

    public function get_card_markup($card_id) {
        $data = $this->get_card_data($card_id);
        if (empty($data['product_title'])) {
            return '<p>' . esc_html__('Invalid warranty card.', 'drts-wc') . '</p>';
        }

        $company = $this->get_company_settings();
        $site_name = get_bloginfo('name');
        $site_url  = home_url('/');

        $product_img = '';
        if (!empty($data['product_id'])) {
            $thumb = get_the_post_thumbnail_url($data['product_id'], 'large');
            if ($thumb) {
                $product_img = $thumb;
            }
        }

        $qr_src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($data['product_url']);

        $pdf_url = add_query_arg([
            'drts_wc_pdf' => 1,
            'card_id'     => $card_id,
        ], home_url('/'));

        ob_start();
        ?>
        <div class="drts-warranty-card">
            <style>
                body,
                .drts-warranty-card,
                .drts-warranty-card * {
                    font-family: 'DejaVu Sans', sans-serif;
                }
                
                .drts-warranty-card,
                .drts-warranty-card *,
                .drts-footer-area,
                .drts-product-block {
                    page-break-inside: avoid !important;
                }
                
                .drts-warranty-badge img {
                    width: 130px;
                    height: auto;
                    display: block;
                }

                .drts-warranty-card {
                    width: 100%;
                    box-sizing: border-box;
                    overflow: hidden;
                }
                img {
                    max-width: 100% !important;
                    height: auto !important;
                }
                
                .drts-signature-box {
                    background-image: url('<?php echo esc_url($company['sign']); ?>');
                }
                
                body {
                    background: #ffffff;
                }
                .drts-warranty-card {
                    max-width: 900px;
                    margin: 30px auto;
                    padding: 40px 50px;
                    background: #fff;
                    color: #000;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                .drts-warranty-card-inner {
                    width: 100%;
                    box-sizing: border-box;
                }
                .drts-warranty-title {
                    text-align: center;
                    font-size: 40px;
                    font-weight: 700;
                    margin-bottom: 10px;
                }
                .drts-warranty-badge span {
                    display: block;
                }
                .drts-warranty-pdf-btn {
                    display: inline-block;
                    margin-bottom: 15px;
                    padding: 8px 14px;
                    background: #000;
                    color: #fff;
                    text-decoration: none;
                    font-size: 13px;
                    border-radius: 4px;
                }
                .drts-warranty-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    font-size: 14px;
                }
                .drts-warranty-table th,
                .drts-warranty-table td {
                    border: 1px solid #000;
                    padding: 6px 8px;
                    text-align: left;
                }
                .drts-warranty-table th {
                    background: #eee;
                    width: 20%;
                    font-weight: 600;
                }

                .drts-product-block {
                    width: 100%;
                    margin-top: 25px;
                    border-collapse: collapse;
                }
                .drts-product-block td {
                    vertical-align: middle;
                }
                .drts-product-block img {
                    width: 220px;
                    border: 1px solid #ddd;
                    padding: 5px;
                    background: #fff;
                }
                .drts-product-title {
                    font-size: 20px;
                    font-weight: bold;
                    margin-bottom: 5px;
                    line-height: 1.4;
                }
                .drts-product-code {
                    font-size: 18px;
                    font-weight: bold;
                    margin-top: 5px;
                }

                .drts-section-title {
                    margin-top: 25px;
                    font-weight: 700;
                    font-size: 14px;
                    text-transform: uppercase;
                }
                .drts-section-text {
                    font-size: 14px;
                    margin-top: 8px;
                    margin-bottom: 8px;
                    line-height: 1.5;
                }
                .drts-section-list {
                    margin-left: 20px;
                    margin-bottom: 8px;
                    font-size: 14px;
                }

                .drts-footer-table {
                    width: 100%;
                    margin-top: 40px;
                    border-collapse: collapse;
                }
                .drts-footer-table td {
                    vertical-align: bottom;
                }
                .drts-footer-logo-area {
                    max-width: 260px;
                    font-size: 13px;
                }
                .drts-footer-logo-area img {
                    max-width: 220px;
                    height: auto;
                    display: block;
                    margin-bottom: 8px;
                }
                .drts-footer-date-sign {
                    text-align: right;
                    font-size: 14px;
                }
                .drts-footer-date-sign strong {
                    display: block;
                    margin-bottom: 5px;
                }
                .drts-signature-box {
                    width: 220px;
                    height: 60px;
                    border-bottom: 1px solid #000;
                    margin-top: 10px;
                    background-size: contain;
                    background-repeat: no-repeat;
                    background-position: left center;
                }
                .drts-qr-box img {
                    width: 130px;
                    height: 130px;
                }
                @media print {
                    .drts-warranty-pdf-btn { display:none; }
                    @page { size: A4; margin: 12mm; }
                    body { background:#fff; margin: 0; }
                    .drts-warranty-card {
                        max-width: 100%;
                        margin: 0;
                        padding: 0;
                        box-shadow: none;
                    }
                    .drts-warranty-card-inner {
                        padding: 30px 0px;
                    }
                    .drts-warranty-card {
                        font-size: 13px;
                    }
                    .drts-warranty-title {
                        font-size: 32px;
                    }
                    .drts-product-block img {
                        width: 180px;
                    }
                    .drts-footer-logo-area img {
                        max-width: 180px;
                    }
                    .drts-signature-box {
                        width: 180px;
                    }
                    .drts-qr-box img {
                        width: 110px;
                        height: 110px;
                    }
                }

            </style>

            <div class="drts-warranty-card-inner">
                <table style="width:100%; margin-bottom:20px; page-break-inside:avoid;">
                    <tr>
                        <td style="text-align:left;">
                            <div class="drts-warranty-title">ГАРАНЦИОННА КАРТА</div>
                        </td>
                        <td style="text-align:right; width:150px;">
                            <?php if (!empty($company['badge'])): ?>
                                <img src="<?php echo esc_url($company['badge']); ?>" alt="badge" style="width:130px;">
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

            <?php if (!isset($_GET['drts_wc_pdf'])): ?>
                <button class="drts-warranty-print-btn" onclick="window.print();" style="margin-right:10px; padding:8px 14px; background:#555; color:#fff; border:none; border-radius:4px; cursor:pointer;">
                    Принтирай
                </button>
            <?php endif; ?>

            <?php if (!isset($_GET['drts_wc_pdf'])): ?>
                <a class="drts-warranty-pdf-btn" href="<?php echo esc_url($pdf_url); ?>" target="_blank">
                    <?php esc_html_e('Свали гаранционна карта (PDF)', 'drts-wc'); ?>
                </a>
            <?php endif; ?>

            <table class="drts-warranty-table">
                <tr>
                    <th>Клиент:</th>
                    <td><?php echo esc_html($data['customer_name']); ?></td>
                    <th>Доставчик:</th>
                    <td><?php echo esc_html($company['name']); ?></td>
                </tr>
                <tr>
                    <th>Идент. №:</th>
                    <td><?php echo esc_html($data['customer_email']); ?></td>
                    <th>Идент. №:</th>
                    <td><?php echo esc_html($company['id']); ?></td>
                </tr>
                <tr>
                    <th>Град:</th>
                    <td><?php echo esc_html($data['customer_city']); ?></td>
                    <th>Град:</th>
                    <td><?php echo esc_html($company['city']); ?></td>
                </tr>
                <tr>
                    <th>Адрес:</th>
                    <td><?php echo esc_html($data['customer_address']); ?></td>
                    <th>Адрес:</th>
                    <td><?php echo esc_html($company['address']); ?></td>
                </tr>
                <tr>
                    <th>Телефон:</th>
                    <td><?php echo esc_html($data['customer_phone']); ?></td>
                    <th>Телефон:</th>
                    <td><?php echo esc_html($company['phone']); ?></td>
                </tr>
            </table>

            <table class="drts-product-block">
                <tr>
                    <td style="width:240px;">
                        <?php if ($product_img): ?>
                            <img src="<?php echo esc_url($product_img); ?>" alt="">
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="drts-product-title">
                            <?php echo esc_html($data['product_title']); ?>
                        </div>
                        <div class="drts-product-code">
                            <?php esc_html_e('Код:', 'drts-wc'); ?>
                            <?php echo esc_html($data['product_sku'] ?: $data['warranty_number']); ?>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="drts-section-title">
                СРОК НА ГАРАНЦИЯТА: 24 месеца от датата на закупуване.
            </div>
            <div class="drts-section-text">
                Гаранционният срок започва да тече от датата на подписване на гаранционната карта.
                Всички описани по-долу стоки се ремонтират безплатно в рамките на гаранционния срок.
                Гаранционното обслужване се извършва в офиса на фирмата. В случай че не можете да
                доставите продукта лично, транспортните разходи са за ваша сметка.
            </div>

            <div class="drts-section-title">Гаранцията отпада в следните случаи:</div>
            <ul class="drts-section-list">
                <li>Неправилна експлоатация или съхранение на продукта;</li>
                <li>Силно замърсяване или неправилна поддръжка;</li>
                <li>Наличие на механични повреди;</li>
                <li>Повреди, причинени от инцидентни въздействия;</li>
                <li>Разпломбиране на стоката или нейните модули;</li>
                <li>Опит за ремонт от неоторизирано лице.</li>
            </ul>

            <div class="drts-section-title">Гаранцията покрива следните компоненти на детската акумулаторна кола:</div>
            <ul class="drts-section-list">
                <li>Двигател;</li>
                <li>Електрическа система (бутони, датчици и други електронни компоненти);</li>
                <li>Управление;</li>
                <li>Аудио система;</li>
                <li>LED светлини;</li>
                <li>Други механични повреди, причинени от фабрични дефекти.</li>
            </ul>

            <div class="drts-section-title">Гаранцията НЕ покрива:</div>
            <ul class="drts-section-list">
                <li>Подмяна или износване на гуми;</li>
                <li>Акумулатор (електрическа батерия) след изтичане на експлоатационния срок;</li>
                <li>Нарушения във външния вид (счупени пластмасови елементи, надраскана боя и др.);</li>
                <li>Износване на кожени компоненти.</li>
            </ul>

            <div class="drts-section-title">Важно:</div>
            <ul class="drts-section-list">
                <li>При опит за разглобяване (разпломбиране) на продукта гаранцията отпада.</li>
                <li>Опцията за връщане в 14-дневен срок не важи, ако продуктът е сглобен.</li>
                <li>Всички транспортни разходи, свързани с връщане в 14-дневен срок или при гаранционен ремонт, са за сметка на купувача.</li>
            </ul>

                <table class="drts-footer-table">
                    <tr>
                        <td class="drts-footer-logo-area">
                            <?php if (!empty($company['logo'])): ?>
                                <img src="<?php echo esc_url($company['logo']); ?>" alt="Logo">
                            <?php endif; ?>
                            <strong><?php echo esc_html($site_name); ?></strong><br>
                            <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_url); ?></a><br>
                            <?php esc_html_e('Гаранционна карта №', 'drts-wc'); ?>
                            <?php echo esc_html($data['warranty_number']); ?>
                        </td>
                        <td class="drts-footer-date-sign">
                            <strong><?php printf(esc_html__('Дата: %s', 'drts-wc'), esc_html($data['start_date'])); ?></strong>
                            <div class="drts-signature-box" style="<?php echo $company['sign'] ? 'background-image:url(' . esc_url($company['sign']) . ');' : ''; ?>"></div>
                            Подпис:
                        </td>
                        <td class="drts-qr-box" style="width:140px; text-align:right;">
                            <img src="<?php echo esc_url($qr_src); ?>" alt="QR">
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* PDF */

    public function maybe_output_pdf() {
        if (!isset($_GET['drts_wc_pdf'], $_GET['card_id'])) {
            return;
        }

        $card_id = absint($_GET['card_id']);
        $card    = get_post($card_id);

        if (!$card || $card->post_type !== self::CPT) {
            wp_die(__('Невалидна гаранционна карта.', 'drts-wc'));
        }

        if (!function_exists('wc_get_order')) {
            wp_die(__('WooCommerce не е включен.', 'drts-wc'));
        }

        $order_id = (int) get_post_meta($card_id, self::META_PREFIX . 'order_id', true);
        $order    = wc_get_order($order_id);
        if ($order) {
            $user_id = get_current_user_id();
            if ($order->get_user_id() && (int) $order->get_user_id() !== (int) $user_id) {
                wp_die(__('Нямате право да сваляте тази гаранционна карта.', 'drts-wc'));
            }
        }

        $html_body = $this->get_card_markup($card_id);
        $html = '<html><head><meta charset="utf-8"><title>Warranty</title></head><body>' . $html_body . '</body></html>';

        if (!class_exists('\Dompdf\Dompdf')) {
            $vendor_autoload = plugin_dir_path(__FILE__) . 'vendor/dompdf/autoload.inc.php';
            if (file_exists($vendor_autoload)) {
                require_once $vendor_autoload;
            }
        }

        if (!class_exists('\Dompdf\Dompdf')) {
            wp_die('<p><strong>dompdf</strong> library not found. Place dompdf in <code>wp-content/plugins/drts-warranty-cards/vendor/dompdf/</code> to enable PDF downloads.</p><hr>' . $html);
        }
        
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('defaultMediaType', 'print');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);


        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();


        $filename = 'warranty-' . $card_id . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    }
    public static function activate() {
        $instance = new self();
        $instance->register_cpt();
        flush_rewrite_rules();
    }
}

register_activation_hook(__FILE__, ['DRTS_Warranty_Cards', 'activate']);

new DRTS_Warranty_Cards();
