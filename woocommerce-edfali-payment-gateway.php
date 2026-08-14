<?php
/*
Plugin Name: EDFALI LIBYA - بوابة إدفع لي (مصرف التجارة والتنمية)
Plugin URI:  https://tahastore.ly
Description: بوابة الدفع الإلكتروني المباشرة والسريعة إدفع لي (مصرف التجارة والتنمية) لـ WooCommerce عبر نظام AJAX الحديث مع دعم العمولات واستثناء الأقسام - تطوير وبرمجة شركة متجر طه (TAHA STORE) | صدقة جارية وهدية لروح المرحوم طه الشريف.
Version:     2.1.0
Author:      شركة متجر طه | TAHA STORE
Author URI:  https://tahastore.ly
Text Domain: wc-edfali-pg
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die;

define( 'WC_EDFALI_PG_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_EDFALI_PG_CLASS_PATH', trailingslashit( WC_EDFALI_PG_PLUGIN_PATH . 'classes' ) );
define( 'WC_EDFALI_PG_VER', '2.1.0' );
define( 'WC_EDFALI_PG_BASE_FILE', __FILE__ );

if ( ! class_exists( 'WC_Edfali_PG_Wrapper' ) ) {
    class WC_Edfali_PG_Wrapper {
        private static $instance = null;

        public static function getInstance() {
            if ( self::$instance === null ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __clone() { }
        public function __wakeup() { }

        private function __construct() {
            add_action( 'plugins_loaded', array( $this, 'check_for_woocommerce' ) );
            add_action( 'wp_ajax_wc_edfali_confirm_otp', array( $this, 'ajax_confirm_otp' ) );
            add_action( 'wp_ajax_nopriv_wc_edfali_confirm_otp', array( $this, 'ajax_confirm_otp' ) );
            
            // هوك احتساب عمولة إدفع لي المئوية مع استثناء الأقسام المحددة
            add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_edfali_fee' ), 20, 1 );
        }

        public function check_for_woocommerce() {
            if ( ! class_exists( 'WooCommerce' ) ) {
                add_action( 'admin_notices', array( $this, 'missing_wc_notice' ) );
                return;
            }

            require_once( WC_EDFALI_PG_CLASS_PATH . 'class-wc-edfali-pg.php' );

            add_filter( 'woocommerce_payment_gateways', array( $this, 'load_gateway_class' ) );
        }

        public function missing_wc_notice() {
            echo '<div class="error"><p><strong>' . esc_html__( 'بوابة إدفع لي (EDFALI LIBYA) تتطلب تفعيل إضافة WooCommerce أولاً.', 'wc-edfali-pg' ) . '</strong></p></div>';
        }

        public function load_gateway_class( $methods ) {
            $methods[] = 'WC_Edfali_PG';
            return $methods;
        }

        /**
         * احتساب نسبة العمولة تلقائياً على إجمالي المنتجات غير المستثناة
         */
        public function calculate_edfali_fee( $cart ) {
            if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
                return;
            }

            // التحقق من أن وسيلة الدفع المحددة حالياً هي إدفع لي
            $chosen_payment_method = WC()->session->get( 'chosen_payment_method' );
            if ( $chosen_payment_method !== 'edfali_pg' ) {
                return;
            }

            $settings = get_option( 'woocommerce_edfali_pg_settings', array() );
            $fee_percent = isset( $settings['fee_percent'] ) ? floatval( $settings['fee_percent'] ) : 0;

            if ( $fee_percent <= 0 ) {
                return;
            }

            $fee_name = ! empty( $settings['fee_name'] ) ? sanitize_text_field( $settings['fee_name'] ) : 'رسوم خدمة إدفع لي';
            $excluded_cats = isset( $settings['excluded_categories'] ) && is_array( $settings['excluded_categories'] ) ? array_map( 'intval', $settings['excluded_categories'] ) : array();

            $applicable_subtotal = 0;

            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                $product_id = $cart_item['product_id'];
                $product_cat_ids = wc_get_product_term_ids( $product_id, 'product_cat' );

                // التحقق هل ينتمي المنتج لأحد الأقسام المستثناة
                $is_excluded = false;
                if ( ! empty( $excluded_cats ) ) {
                    foreach ( $product_cat_ids as $cat_id ) {
                        if ( in_array( (int) $cat_id, $excluded_cats, true ) ) {
                            $is_excluded = true;
                            break;
                        }
                    }
                }

                // إذا لم يكن مستثنى، نضيف سعره الإجمالي للخاضع للعمولة
                if ( ! $is_excluded ) {
                    $applicable_subtotal += floatval( $cart_item['line_total'] );
                }
            }

            if ( $applicable_subtotal > 0 ) {
                $fee_amount = ( $applicable_subtotal * $fee_percent ) / 100;
                $fee_title  = sprintf( '%s (%s%%)', esc_html( $fee_name ), $fee_percent );
                $cart->add_fee( $fee_title, $fee_amount, true, 'standard' );
            }
        }

        public function ajax_confirm_otp() {
            check_ajax_referer( 'edfali_checkout_nonce', 'nonce' );

            $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
            $otp_code = isset( $_POST['otp_code'] ) ? sanitize_text_field( $_POST['otp_code'] ) : '';

            if ( ! $order_id || empty( $otp_code ) ) {
                wp_send_json_error( array( 'message' => __( 'بيانات تأكيد غير مكتملة.', 'wc-edfali-pg' ) ) );
            }

            $gateway = new WC_Edfali_PG();
            $result  = $gateway->confirm_trans( $order_id, $otp_code );

            if ( $result['success'] ) {
                wp_send_json_success( array(
                    'message'      => __( 'تم تأكيد الدفع بنجاح!', 'wc-edfali-pg' ),
                    'redirect_url' => $result['redirect_url'],
                ) );
            } else {
                wp_send_json_error( array(
                    'message' => $result['message'],
                ) );
            }
        }
    }
}

WC_Edfali_PG_Wrapper::getInstance();