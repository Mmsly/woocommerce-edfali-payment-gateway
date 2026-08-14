<?php
/*
Plugin Name: EDFALI LIBYA - بوابة إدفع لي (مصرف التجارة والتنمية)
Plugin URI:  https://tahastore.ly
Description: بوابة الدفع الإلكتروني المباشرة والسريعة إدفع لي (مصرف التجارة والتنمية) لـ WooCommerce عبر نظام AJAX الحديث - تطوير وبرمجة شركة متجر طه (TAHA STORE) | صدقة جارية وهدية لروح المرحوم طه الشريف.
Version:     2.0.0
Author:      شركة متجر طه | TAHA STORE
Author URI:  https://tahastore.ly
Text Domain: wc-edfali-pg
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die;

define( 'WC_EDFALI_PG_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_EDFALI_PG_CLASS_PATH', trailingslashit( WC_EDFALI_PG_PLUGIN_PATH . 'classes' ) );
define( 'WC_EDFALI_PG_VER', '2.0.0' );
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