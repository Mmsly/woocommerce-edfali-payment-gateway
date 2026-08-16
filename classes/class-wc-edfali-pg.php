<?php
defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'WC_Edfali_PG' ) ) {
	class WC_Edfali_PG extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'edfali_pg';
			$this->icon               = plugins_url( 'images/edfali_small.png', WC_EDFALI_PG_BASE_FILE );
			$this->has_fields         = true;
			$this->method_title       = __( 'إدفع لي (Edfali)', 'wc-edfali-pg' );
			$this->method_description = __( 'بوابة الدفع الإلكتروني المباشرة والسريعة عبر خدمة إدفع لي (مصرف التجارة والتنمية) - تطوير شركة متجر طه TAHA STORE.', 'wc-edfali-pg' );
			
			$this->init_form_fields();
			$this->init_settings();

			$this->title       = $this->get_option( 'title', 'إدفع لي (Edfali) ⚡' );
			$this->description = $this->get_option( 'description', 'ادفع بأمان وسرعة عبر حسابك في خدمة إدفع لي، سيصلك رمز تأكيد عبر رسالة SMS.' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		}

		public function init_form_fields() {
			// جلب جميع تصنيفات وأقسام المنتجات للاختيار منها
			$categories_options = array();
			$product_cats = get_terms( array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			) );

			if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) {
				foreach ( $product_cats as $cat ) {
					$categories_options[ $cat->term_id ] = $cat->name;
				}
			}

			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'تفعيل / تعطيل', 'wc-edfali-pg' ),
					'type'    => 'checkbox',
					'label'   => __( 'تفعيل وسيلة الدفع "إدفع لي"', 'wc-edfali-pg' ),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __( 'عنوان وسيلة الدفع للعملاء', 'wc-edfali-pg' ),
					'type'        => 'text',
					'description' => __( 'الاسم الذي يظهر للعميل في صفحة الدفع.', 'wc-edfali-pg' ),
					'default'     => 'إدفع لي (Edfali) ⚡',
					'desc_tip'    => true
				),
				'description' => array(
					'title'       => __( 'الوصف في صفحة الشراء', 'wc-edfali-pg' ),
					'type'        => 'textarea',
					'description' => __( 'الوصف الذي يظهر للمشتري عند تحديد وسيلة الدفع.', 'wc-edfali-pg' ),
					'default'     => 'ادفع بأمان عبر حساب إدفع لي، أدخل رقم هاتفك وسيصلك كود التحقق لتأكيد الخصم فورياً.'
				),
				'fee_percent' => array(
					'title'       => __( 'نسبة العمولة الإضافية (%)', 'wc-edfali-pg' ),
					'type'        => 'number',
					'description' => __( 'نسبة مئوية تضاف تلقائياً على إجمالي الطلب عند اختيار الدفع بـ إدفع لي (مثال: 5 تعني إضافة 5%). اتركها 0 إذا لم تكن هناك عمولة.', 'wc-edfali-pg' ),
					'default'     => '0',
					'custom_attributes' => array(
						'step' => '0.1',
						'min'  => '0',
					),
					'desc_tip'    => true
				),
				'fee_name' => array(
					'title'       => __( 'مسمى العمولة في الفاتورة', 'wc-edfali-pg' ),
					'type'        => 'text',
					'description' => __( 'الاسم الذي يظهر للمشتري في تفاصيل السلة والحساب للعمولة.', 'wc-edfali-pg' ),
					'default'     => 'رسوم خدمة إدفع لي',
					'desc_tip'    => true
				),
				'excluded_categories' => array(
					'title'       => __( 'الأقسام المستثناة من العمولة', 'wc-edfali-pg' ),
					'type'        => 'multiselect',
					'class'       => 'wc-enhanced-select',
					'css'         => 'width: 400px;',
					'description' => __( 'المنتجات التابعة لهذه الأقسام لن يتم احتساب نسبة العمولة عليها في السلة.', 'wc-edfali-pg' ),
					'options'     => $categories_options,
					'default'     => array(),
					'desc_tip'    => true
				),
				'mobile' => array(
					'title'       => __( 'رقم هاتف المتجر (Merchant Mobile)', 'wc-edfali-pg' ),
					'type'        => 'text',
					'description' => __( 'رقم هاتف خدمة إدفع لي الخاص بالمتجر (9 أرقام تبدأ بـ 9 مثال: 9xxxxxxxx).', 'wc-edfali-pg' ),
					'default'     => '',
					'desc_tip'    => true
				),
				'pin' => array(
					'title'       => __( 'الرقم السري للمتجر (Merchant Pin)', 'wc-edfali-pg' ),
					'type'        => 'password',
					'description' => __( 'الرقم السري المكون من 4 أرقام المزود من المصرف.', 'wc-edfali-pg' ),
					'default'     => '',
					'desc_tip'    => true
				),
				'pw' => array(
					'title'       => __( 'كلمة سر الخدمة (Service PW)', 'wc-edfali-pg' ),
					'type'        => 'text',
					'description' => __( 'كلمة سر الويب سيرفس الافتراضية (Default: 123@xdsr$#!!).', 'wc-edfali-pg' ),
					'default'     => '123@xdsr$#!!',
					'desc_tip'    => true
				),
				'wsdl_url' => array(
					'title'       => __( 'رابط الـ WSDL للخدمة', 'wc-edfali-pg' ),
					'type'        => 'text',
					'description' => __( 'رابط ويب سيرفس إدفع لي المعتمد والمحدث.', 'wc-edfali-pg' ),
					'default'     => 'https://edfali.bcd.ly/api/BCDUssd/NewEdfali.asmx?WSDL',
					'desc_tip'    => true
				),
				'sandbox_mode' => array(
					'title'       => __( 'الوضع التجريبي (Sandbox Simulation)', 'wc-edfali-pg' ),
					'type'        => 'checkbox',
					'label'       => __( 'تمكين وضع المحاكاة التجريبي لاختبار التدفق وسلاسة النوافذ', 'wc-edfali-pg' ),
					'default'     => 'no',
					'description' => __( 'عند التفعيل، يقبل أي كود SMS مثل 1234 لمحاكاة الدفع بنجاح واختبار المتجر.', 'wc-edfali-pg' ),
					'desc_tip'    => true
				),
			);
		}

		/**
		 * تخصيص صفحة إعدادات البوابة في الأدمن بتصميم أنيق وفاخر وشارة الحقوق والإهداء
		 */
		public function admin_options() {
			?>
			<div class="wrap" style="direction: rtl; font-family: system-ui, -apple-system, sans-serif; max-width: 950px; margin: 20px 0;">
				<!-- الهيدر الفاخر لشركة متجر طه والإهداء -->
				<div style="background: linear-gradient(135deg, #1e1b4b, #4c1d95); border-radius: 18px; padding: 25px 30px; color: #fff; box-shadow: 0 10px 25px rgba(76, 29, 149, 0.25); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
					<div>
						<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
							<h2 style="margin: 0; font-size: 1.7em; color: #fff; font-weight: 800;">
								⚡ بوابة إدفع لي (EDFALI LIBYA)
							</h2>
							<span style="background: #7c3aed; color: #fff; padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 0.82em; font-family: monospace;">
								الإصدار v<?php echo WC_EDFALI_PG_VER; ?>
							</span>
						</div>
						<p style="margin: 0; opacity: 0.9; font-size: 0.95em;">
							بوابة الدفع المباشر لـ مصرف التجارة والتنمية عبر AJAX المطور - تطوير <strong>شركة متجر طه | TAHA STORE</strong>.
						</p>
					</div>
					<div>
						<a href="https://tahastore.ly" target="_blank" style="background: #fff; color: #4c1d95; text-decoration: none; padding: 8px 18px; border-radius: 10px; font-weight: bold; font-size: 0.9em; display: inline-block;">
							زيارة متجر طه 🌐
						</a>
					</div>
				</div>

				<!-- بطاقة الإهداء والصدقة الجارية لروح المرحوم طه الشريف -->
				<div style="background: #faf5ff; border: 1px solid #d8b4fe; border-radius: 14px; padding: 16px 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 14px;">
					<div style="font-size: 2em;">🕊️</div>
					<div>
						<strong style="color: #581c87; font-size: 1.05em; display: block; margin-bottom: 2px;">
							صدقة جارية وهدية لروح المرحوم بإذن الله: طه الشريف
						</strong>
						<span style="color: #6b21a8; font-size: 0.9em;">
							نسأل الله العلي القدير أن يتغمده بواسع رحمته ويسكنه فسيح جناته، وأن ينفع بهذا العمل كل أصحاب المتاجر والمستخدمين.
						</span>
					</div>
				</div>

				<!-- جدول الإعدادات الافتراضي المنسق -->
				<div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
					<h3 style="margin-top: 0; margin-bottom: 20px; color: #1f2937; border-bottom: 2px solid #f3f4f6; padding-bottom: 12px; font-size: 1.2em;">
						⚙️ ضبط بيانات حساب إدفع لي والعمولة والاتصال بالبنك
					</h3>
					<table class="form-table">
						<?php $this->generate_settings_html(); ?>
					</table>
				</div>

				<!-- فوتر الحقوق والتواصل -->
				<div style="margin-top: 20px; text-align: center; color: #6b7280; font-size: 0.9em; padding: 15px; background: #fff; border-radius: 12px; border: 1px solid #e5e7eb;">
					تمت البرمجة والتطوير بكل فخر بواسطة <strong>شركة متجر طه (TAHA STORE)</strong> 🇱🇾 | هاتف الدعم الفني: <span style="direction: ltr; display: inline-block; font-weight: bold; font-family: monospace;">+218 91 3322222</span>
				</div>
			</div>
			<?php
		}

		public function payment_scripts() {
			if ( ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
				return;
			}

			if ( $this->enabled !== 'yes' ) {
				return;
			}

			wp_enqueue_style( 'wc-edfali-ajax-style', plugins_url( 'css/edfali-checkout.css', WC_EDFALI_PG_BASE_FILE ), array(), WC_EDFALI_PG_VER );
			wp_enqueue_script( 'wc-edfali-ajax-script', plugins_url( 'js/edfali-checkout.js', WC_EDFALI_PG_BASE_FILE ), array( 'jquery' ), WC_EDFALI_PG_VER, true );

			wp_localize_script( 'wc-edfali-ajax-script', 'wc_edfali_params', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'edfali_checkout_nonce' ),
				'messages' => array(
					'invalid_phone' => __( 'يرجى إدخال رقم هاتف ليبي صحيح مكون من 9 أرقام يبدأ بـ 9 (مثال: 913322222)', 'wc-edfali-pg' ),
					'enter_otp'     => __( 'يرجى إدخال رمز التحقق المكون من 4 أرقام.', 'wc-edfali-pg' ),
					'server_error'  => __( 'حدث خطأ في الاتصال بالخادم، يرجى المحاولة مرة أخرى.', 'wc-edfali-pg' ),
				)
			) );
		}

		public function payment_fields() {
			if ( $this->description ) {
				echo wpautop( wptexturize( esc_html( $this->description ) ) );
			}
			?>
			<div class="edfali-payment-box" style="margin-top: 15px; direction: rtl; text-align: right;">
				<label for="edfali_customer_mobile" style="display: block; font-weight: bold; margin-bottom: 6px; color: #374151;">
					📱 رقم هاتف خدمة إدفع لي:
				</label>
				<div class="edfali-phone-input-wrapper" style="display: flex; align-items: center; border: 1px solid #d1d5db; border-radius: 8px; overflow: hidden; background: #fff; max-width: 320px;">
					<span style="background: #f3f4f6; color: #4b5563; padding: 10px 14px; font-weight: bold; direction: ltr; font-family: monospace; border-left: 1px solid #e5e7eb;">+218</span>
					<input type="tel" id="edfali_customer_mobile" name="edfali_mobile" class="input-text" placeholder="9xxxxxxxx" maxlength="10" style="border: none; padding: 10px 12px; width: 100%; font-size: 1.1em; font-family: monospace; outline: none;" />
				</div>
				<small style="color: #6b7280; display: block; margin-top: 5px;">مثال: 913322222 أو 0913322222</small>
			</div>
			<?php
		}

		public function validate_fields() {
			if ( empty( $_POST['edfali_mobile'] ) ) {
				wc_add_notice( __( 'يرجى إدخال رقم هاتف خدمة إدفع لي.', 'wc-edfali-pg' ), 'error' );
				return false;
			}

			$phone = $this->sanitize_phone( $_POST['edfali_mobile'] );
			if ( ! preg_match( '/^9\d{8}$/', $phone ) ) {
				wc_add_notice( __( 'رقم هاتف إدفع لي غير صحيح. يجب أن يتكون من 9 أرقام ويبدأ بـ 9 (مثال: 913322222).', 'wc-edfali-pg' ), 'error' );
				return false;
			}

			return true;
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array( 'result' => 'failure' );
			}

			$phone = isset( $_POST['edfali_mobile'] ) ? $this->sanitize_phone( $_POST['edfali_mobile'] ) : '';
			if ( ! preg_match( '/^9\d{8}$/', $phone ) ) {
				wc_add_notice( __( 'رقم الهاتف المدخل غير صحيح.', 'wc-edfali-pg' ), 'error' );
				return array( 'result' => 'failure' );
			}

			$order->update_status( 'pending', __( 'بانتظار تأكيد الدفع عبر إدفع لي (Edfali)...', 'wc-edfali-pg' ) );
			update_post_meta( $order_id, '_edfali_customer_phone', '+218' . $phone );

			// وضع المحاكاة التجريبي (Sandbox Mode)
			if ( $this->get_option( 'sandbox_mode' ) === 'yes' ) {
				$fake_session = 'EDFALI_MOCK_' . time() . '_' . rand( 1000, 9999 );
				update_post_meta( $order_id, '_edfali_session_id', $fake_session );

				return array(
					'result'   => 'success',
					'edfali_ajax' => true,
					'order_id' => $order_id,
					'phone'    => '+218' . $phone,
					'session_id' => $fake_session,
				);
			}

			// الاتصال بالـ Web Service الحقيقي
			$total = number_format( $order->get_total(), 2, '.', '' );
			$trans_res = $this->do_p_trans( $phone, $total );

			if ( is_wp_error( $trans_res ) ) {
				wc_add_notice( $trans_res->get_error_message(), 'error' );
				return array( 'result' => 'failure' );
			}

			$code_error = $this->translate_error_code( $trans_res );
			if ( $code_error !== false ) {
				wc_add_notice( $code_error, 'error' );
				return array( 'result' => 'failure' );
			}

			// نجاح استلام Session ID
			update_post_meta( $order_id, '_edfali_session_id', $trans_res );

			return array(
				'result'      => 'success',
				'edfali_ajax' => true,
				'order_id'    => $order_id,
				'phone'       => '+218' . $phone,
				'session_id'  => $trans_res,
			);
		}

		private function do_p_trans( $customer_phone, $amount ) {
			$wsdl = $this->get_option( 'wsdl_url', 'http://102.211.5.141:6187/BCDUssd/NewEdfali.asmx?WSDL' );
			$merchant_mobile = $this->get_option( 'mobile' );
			$merchant_pin    = $this->get_option( 'pin' );
			$pw              = $this->get_option( 'pw', '123@xdsr$#!!' );

			try {
				$options = array(
					'trace'              => 1,
					'exceptions'         => true,
					'connection_timeout' => 15,
					'cache_wsdl'         => WSDL_CACHE_NONE,
				);

				$client = new SoapClient( $wsdl, $options );
				
				$params = array(
					'Mobile'        => $merchant_mobile,
					'Pin'           => $merchant_pin,
					'Cmobile'       => '+218' . $customer_phone,
					'Amount'        => (float) $amount,
					'PW'            => $pw,
				);

				$response = $client->DoPTrans( $params );

				if ( isset( $response->DoPTransResult ) ) {
					return trim( (string) $response->DoPTransResult );
				}

				return new WP_Error( 'edfali_err', __( 'استجابة غير متوقعة من بوابة إدفع لي.', 'wc-edfali-pg' ) );

			} catch ( SoapFault $fault ) {
				return new WP_Error( 'edfali_soap_fault', sprintf( __( 'خطأ في الاتصال بسيرفر إدفع لي: %s', 'wc-edfali-pg' ), $fault->getMessage() ) );
			} catch ( Exception $e ) {
				return new WP_Error( 'edfali_exception', sprintf( __( 'تعذر إتمام الاتصال: %s', 'wc-edfali-pg' ), $e->getMessage() ) );
			}
		}

		public function confirm_trans( $order_id, $otp_code ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array( 'success' => false, 'message' => __( 'الطلب غير موجود.', 'wc-edfali-pg' ) );
			}

			$session_id = get_post_meta( $order_id, '_edfali_session_id', true );
			if ( empty( $session_id ) ) {
				return array( 'success' => false, 'message' => __( 'جلسة الدفع منتهية الصلاحية.', 'wc-edfali-pg' ) );
			}

			// وضع المحاكاة التجريبي
			if ( $this->get_option( 'sandbox_mode' ) === 'yes' ) {
				$order->payment_complete( $session_id );
				$order->add_order_note( '✅ [وضع تجريبي] تم تأكيد الدفع بنجاح عبر إدفع لي كود المحاكاة: ' . esc_html( $otp_code ) );
				update_post_meta( $order_id, '_edfali_paid', 1 );

				return array(
					'success'      => true,
					'redirect_url' => $this->get_return_url( $order ),
				);
			}

			// الاتصال بالـ Web Service لتأكيد الخصم
			$wsdl = $this->get_option( 'wsdl_url', 'https://edfali.bcd.ly/api/BCDUssd/NewEdfali.asmx?WSDL' );
			$merchant_mobile = $this->get_option( 'mobile' );
			$pw              = $this->get_option( 'pw', '123@xdsr$#!!' );

			try {
				$options = array(
					'trace'              => 1,
					'exceptions'         => true,
					'connection_timeout' => 20,
					'cache_wsdl'         => WSDL_CACHE_NONE,
				);

				$client = new SoapClient( $wsdl, $options );

				$params = array(
					'Mobile'    => $merchant_mobile,
					'Pin'       => trim( (string) $otp_code ),
					'sessionID' => $session_id,
					'PW'        => $pw,
				);

				$response = $client->OnlineConfTrans( $params );

				if ( isset( $response->OnlineConfTransResult ) ) {
					$res = trim( (string) $response->OnlineConfTransResult );
					if ( strtoupper( $res ) === 'OK' ) {
						$order->payment_complete( $session_id );
						$order->add_order_note( '✅ تم استلام وتأكيد الدفع بنجاح عبر إدفع لي (Edfali). رقم الجلسة: ' . $session_id );
						update_post_meta( $order_id, '_edfali_paid', 1 );

						return array(
							'success'      => true,
							'redirect_url' => $this->get_return_url( $order ),
						);
					} else {
						return array(
							'success' => false,
							'message' => sprintf( __( 'فشل تأكيد الرمز: %s', 'wc-edfali-pg' ), $this->translate_confirm_error( $res ) ),
						);
					}
				}

				return array( 'success' => false, 'message' => __( 'استجابة غير صحيحة من سيرفر البنك.', 'wc-edfali-pg' ) );

			} catch ( SoapFault $fault ) {
				return array( 'success' => false, 'message' => sprintf( __( 'خطأ في الاتصال: %s', 'wc-edfali-pg' ), $fault->getMessage() ) );
			} catch ( Exception $e ) {
				return array( 'success' => false, 'message' => $e->getMessage() );
			}
		}

		private function translate_error_code( $code ) {
			$c = strtoupper( trim( (string) $code ) );
			switch ( $c ) {
				case 'PW1':
					return __( 'خطأ في إعدادات كلمة مرور الخدمة (يرجى مراجعة إدارة المتجر).', 'wc-edfali-pg' );
				case 'PW':
					return __( 'الرقم السري لخدمة إدفع لي الخاص بالمتجر غير صحيح.', 'wc-edfali-pg' );
				case 'LIMIT':
					return __( 'المبلغ المطلوب يتجاوز الحد المالي الأقصى المسموح به للعملية في خدمة إدفع لي.', 'wc-edfali-pg' );
				case 'ACC':
					return __( 'رقم الهاتف المدخل غير مسجل في خدمة إدفع لي بمصرف التجارة والتنمية.', 'wc-edfali-pg' );
				case 'BAL':
					return __( 'رصيد الحساب غير كافٍ أو تعذر إتمام المعاملة حالياً.', 'wc-edfali-pg' );
				default:
					// إذا لم تكن الاستجابة رقم جلسة أرقام، نعتبرها خطأ
					if ( ! preg_match( '/^\d+$/', $c ) ) {
						return sprintf( __( 'استجابة غير متوقعة من بوابة إدفع لي: %s', 'wc-edfali-pg' ), esc_html( $code ) );
					}
					return false;
			}
		}

		private function translate_confirm_error( $code ) {
			if ( $code === 'Pin' || strtolower( $code ) === 'invalid pin' ) {
				return __( 'رمز التحقق (SMS Pin) المدخل غير صحيح، يرجى التحقق وإعادة الإدخال.', 'wc-edfali-pg' );
			}
			return $code;
		}

		private function sanitize_phone( $phone ) {
			$clean = preg_replace( '/\D/', '', $phone );
			if ( substr( $clean, 0, 4 ) === '00218' ) {
				$clean = substr( $clean, 4 );
			} elseif ( substr( $clean, 0, 3 ) === '218' ) {
				$clean = substr( $clean, 3 );
			} elseif ( substr( $clean, 0, 1 ) === '0' ) {
				$clean = substr( $clean, 1 );
			}
			return $clean;
		}
	}
}