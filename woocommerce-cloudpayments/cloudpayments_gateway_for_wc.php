<?php
/**
 * Plugin Name: CloudPayments Gateway for WooCommerce
 * Version: 2.2
 * Description: Extends WooCommerce with CloudPayments Gateway.
 * Plugin URI: https://github.com/cloudpayments/CloudPayments_WooCommerce
 * Text Domain: cloudpayments
 * Domain Path: /languages/
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Register New Order Statuses
function cpgwwc_register_post_statuses()
{
    register_post_status( 'wc-pay_au', array(
        'label'                     => _x( 'Payment authorized', 'WooCommerce Order status', 'cloudpayments' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Approved (%s)', 'Approved (%s)', 'cloudpayments' )
    ) );
    register_post_status( 'wc-pay_delivered', array(
        'label'                     => _x( 'Delivered', 'WooCommerce Order status', 'cloudpayments' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Approved (%s)', 'Approved (%s)', 'cloudpayments' )
    ) );
}
add_filter( 'init', 'cpgwwc_register_post_statuses' );

// Register script
function cpgwwc_my_scripts_method(){
    wp_enqueue_script( 'cpgwwc_CloudPayments_script', "https://widget.cloudpayments.ru/bundles/cloudpayments?cms=WordPress");
};
add_action( 'init', 'cpgwwc_my_scripts_method' );

// Add New Order Statuses to WooCommerce
function cpgwwc_add_order_statuses( $order_statuses )
{
    $order_statuses['wc-pay_au'] = _x( 'Payment authorized', 'WooCommerce Order status', 'cloudpayments' );
    $order_statuses['wc-pay_delivered'] = _x( 'Delivered', 'WooCommerce Order status', 'cloudpayments' );
    return $order_statuses;
}
add_filter( 'wc_order_statuses', 'cpgwwc_add_order_statuses' );

add_action('plugins_loaded', 'cpgwwc_CloudPayments', 0);
function cpgwwc_CloudPayments()
{
	if ( !class_exists( 'WC_Payment_Gateway' ) ) {
	    echo __( 'CloudPayments Gateway for WooCommerce plugin is disabled. Check to see if this plugin is active.', 'cloudpayments' );
	    return;
	}

	load_plugin_textdomain( 'cloudpayments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	add_filter( 'woocommerce_payment_gateways', 'cpgwwc_add_cpgwwc' );
    function cpgwwc_add_cpgwwc( $methods )
    {
		$methods[] = 'WC_CloudPayments';
		return $methods;
	}
	class WC_CloudPayments extends WC_Payment_Gateway {

		public function __construct() {

			$this->id                 =  'cpgwwc';
			$this->has_fields         =  true;
			$this->icon 			  =  plugin_dir_url( __FILE__ ) . 'visa-mastercard.png';
			$this->method_title       =  __( 'CloudPayments', 'cloudpayments' );
			$this->method_description =  __( 'CloudPayments is the easiest and most convenient way to pay. No commissions.', 'cloudpayments' );
			$this->supports           =  array( 'products','pre-orders' );
            $this->enabled            =  $this->get_option( 'enabled' );
            $this->enabledDMS         =  $this->get_option( 'enabledDMS' );
            $this->DMS_AU_status      =  $this->get_option( 'DMS_AU_status' );
            $this->DMS_CF_status      =  $this->get_option( 'DMS_CF_status' );
            $this->skin               =  $this->get_option( 'skin' );
            $this->language           =  $this->get_option( 'language' );
            $this->status_chancel     =  $this->get_option( 'status_chancel' );
            $this->status_pay         =  $this->get_option( 'status_pay' );
			$this->init_form_fields();
			$this->init_settings();
			$this->title          	= $this->get_option( 'title' );
			$this->description    	= $this->get_option( 'description' );
			$this->public_id    	= $this->get_option( 'public_id' );
			$this->api_pass    	  	= $this->get_option( 'api_pass' );
			$this->currency    	  	= $this->get_option( 'currency' );
			$this->kassa_enabled    = $this->get_option( 'kassa_enabled' );
			$this->kassa_taxtype    = $this->get_option( 'kassa_taxtype' );
			$this->delivery_taxtype = $this->get_option( 'delivery_taxtype' );
			$this->kassa_taxsystem  = $this->get_option( 'kassa_taxsystem' );
			$this->kassa_skubarcode = $this->get_option( 'kassa_skubarcode' );
			$this->kassa_method     = $this->get_option( 'kassa_method' );
            $this->kassa_object     = $this->get_option( 'kassa_object' );
            $this->status_delivered = $this->get_option( 'status_delivered' );
            $this->inn              = $this->get_option( 'inn' );

		    add_action( 'woocommerce_receipt_cpgwwc', 	array( $this, 'payment_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_'. strtolower( get_class( $this ) ), array( $this, 'cpgwwc_handle_callback' ) );
            add_action('woocommerce_order_status_changed', array( $this, 'cpgwwc_update_order_status'), 10, 3);
		}

		// Check SSL
		public function cpgwwc_ssl_check() {
			if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && $this->enabled == 'yes' ) {
				$this->msg = sprintf( __( 'Включена поддержка оплаты через CloudPayments и не активирована опция <a href="%s">"Принудительная защита оформления заказа"</a>; безопасность заказов находится под угрозой! Пожалуйста, включите SSL и проверьте корректность установленных сертификатов.', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
				$this->enabled = false;
				return false;
			}
			return true;
		}

		// Admin options
		public function admin_options() {
			if ( !$this->cpgwwc_ssl_check() ) {
				?>
				<div class="inline error"><p><strong><?php echo __( 'Warning', 'cloudpayments' ); ?></strong>: <?php echo $this->msg; ?></p></div>
				<?php
			}
			?>
				<h3>CloudPayments</h3>
				<p>CloudPayments – прямой и простой прием платежей с кредитных карт</p>
		        <p><strong>В личном кабинете включите Check-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))."?action=check"?></p>
		        <p><strong>В личном кабинете включите Pay-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))."?action=pay"?></p>
		        <p><strong>В личном кабинете включите Fail-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))."?action=fail"?></p>
		        <p><strong>В личном кабинете включите Confirm-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))."?action=confirm"?></p>
		        <p><strong>В личном кабинете включите Refund-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))."?action=refund"?></p>
		        <p><strong>В личном кабинете включите Cancel-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))."?action=cancel"?></p>
		        <p>Кодировка UTF-8, HTTP-метод POST, Формат запроса CloudPayments.</p>
			<?php

			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
        }

		// Initialize fields
		public function init_form_fields() {
			$array_status = wc_get_order_statuses();
			$this->form_fields = array(
				'enabled' => array(
					'title' 	=> __( 'Enable/Disable', 'cloudpayments' ),
					'type' 		=> 'checkbox',
					'label' 	=> __( 'Enable CloudPayments', 'cloudpayments' ),
					'default' 	=> 'yes'
				),
				'enabledDMS' => array(
					'title' 	=> __( 'Enable DMS', 'cloudpayments' ),
					'type' 		=> 'checkbox',
					'label' 	=> __( 'Enable DMS', 'cloudpayments' ),
					'default' 	=> 'no'
				),
				'status_pay' => array(
					'title'       => __( 'Status for paid order', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( '', 'cloudpayments' ),
					'default'     => 'wc-completed',
					'desc_tip'    => true,
					'options'     => $array_status,
				),
				'status_chancel' => array(
					'title'       => __( 'Status for canceled order', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( '', 'cloudpayments' ),
					'default'     => 'wc-cancelled',
					'desc_tip'    => true,
					'options'     => $array_status,
				),
				'DMS_AU_status' => array(
					'title'       => __( 'Authorized Payment Status (DMS)', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( '', 'cloudpayments' ),
					'default'     => 'wc-pay_au',
					'desc_tip'    => true,
					'options'     => $array_status,
				),
				'title' => array(
					'title' 		=> __( 'Title', 'cloudpayments' ),
					'type' 			=> 'text',
					'description' 	=> __( 'This controls the title which the user sees during checkout.', 'cloudpayments' ),
					'default' 		=> __( 'Bank card', 'cloudpayments' ),
					'desc_tip'   	 => true,
				),
				'description' 	=> array(
					'title'       => __( 'Description', 'cloudpayments' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'cloudpayments' ),
					'default'     => __( 'CloudPayments is the easiest and most convenient way to pay. No commissions.', 'cloudpayments' ),
					'desc_tip'    => true,
				),
				'public_id' => array(
					'title' 		=> __( 'Public ID', 'cloudpayments' ),
					'type' 			=> 'text',
					'description'	=> __( 'Take from your CloudPayments personal account', 'cloudpayments' ),
					'default' 		=> '',
					'desc_tip' 		=> false,
				),
				'api_pass' => array(
					'title' 		=> __( 'API Password', 'cloudpayments' ),
					'type' 			=> 'text',
					'description'	=> __( 'Take from your CloudPayments personal account', 'cloudpayments' ),
					'default' 		=> '',
					'desc_tip' 		=> false,
				),
				'currency' => array(
					'title'       => __( 'Shop Currency', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'default'     => 'RUB',
					'options'     => array(
						'RUB' => __( 'Российский рубль', 'woocommerce' ),
						'EUR' => __( 'Евро', 'woocommerce' ),
						'USD' => __( 'Доллар США', 'woocommerce' ),
						'GBP' => __( 'Фунт стерлингов', 'woocommerce' ),
						'UAH' => __( 'Украинская гривна', 'woocommerce' ),
						'BYN' => __( 'Белорусский рубль', 'woocommerce' ),
						'KZT' => __( 'Казахский тенге', 'woocommerce' ),
						'AZN' => __( 'Азербайджанский манат', 'woocommerce' ),
						'CHF' => __( 'Швейцарский франк', 'woocommerce' ),
						'CZK' => __( 'Чешская крона', 'woocommerce' ),
						'CAD' => __( 'Канадский доллар', 'woocommerce' ),
						'PLN' => __( 'Польский злотый', 'woocommerce' ),
						'SEK' => __( 'Шведская крона', 'woocommerce' ),
						'TRY' => __( 'Турецкая лира', 'woocommerce' ),
						'CNY' => __( 'Китайский юань', 'woocommerce' ),
						'INR' => __( 'Индийская рупия', 'woocommerce' ),
						'BRL' => __( 'Бразильский реал', 'woocommerce' ),
						'ZAR' => __( 'Южноафриканский рэнд', 'woocommerce' ),
						'UZS' => __( 'Узбекский сум', 'woocommerce' ),
						'BGL' => __( 'Болгарский лев', 'woocommerce' ),
					),
                ),
                'skin' => array(
					'title'       => __( 'Дизайн виджета', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'default'     => 'classic',
					'options'     => array(
						'classic' => __( 'Классический', 'cloudpayments' ),
						'modern' => __( 'Модерн', 'cloudpayments' ),
						'mini' => __( 'Мини', 'cloudpayments' ),
                    ),
                ),
                'language' => array(
					'title'       => __( 'Язык виджета', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'default'     => 'ru-RU',
					'options'     => array(
						'ru-RU' => __( 'Русский', 'cloudpayments' ),
						'en-US' => __( 'Английский', 'cloudpayments' ),
						'lv' => __( 'Латышский', 'cloudpayments' ),
						'az' => __( 'Азербайджанский', 'cloudpayments' ),
						'kk' => __( 'Русский', 'cloudpayments' ),
						'kk-KZ' => __( 'Казахский', 'cloudpayments' ),
						'uk' => __( 'Украинский', 'cloudpayments' ),
						'pl' => __( 'Польский', 'cloudpayments' ),
                        'pt' => __( 'Португальский', 'cloudpayments' ),
                        'cs-CZ' => __( 'Чешский', 'cloudpayments' ),
                    ),
                ),
				'kassa_section' => array(
					'title'       => __( 'Онлайн-касса', 'cloudpayments' ),
					'type'        => 'title',
					'description' => '',
				),
				'kassa_enabled' => array(
					'title' 	=> __( 'Enable/Disable', 'cloudpayments' ),
					'type' 		=> 'checkbox',
					'label' 	=> __( 'Включить отправку данных для онлайн-кассы (по 54-ФЗ)', 'cloudpayments' ),
					'default' 	=> 'no'
				),
				'inn' => array(
					'title' 		=> __( 'ИНН', 'cloudpayments' ),
					'type' 			=> 'text',
					'description'	=> '',
					'default' 		=> '',
					'desc_tip' 		=> true,
                ),
				'kassa_taxtype' => array(
					'title'       => __( 'Ставка НДС', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите ставку НДС, которая применима к товарам в магазине.', 'cloudpayments' ),
					'default'     => '10',
					'desc_tip'    => true,
					'options'     => array(
						'null' => __( 'НДС не облагается', 'cloudpayments' ),
						'20' => __( 'НДС 20%', 'cloudpayments' ),
						'10' => __( 'НДС 10%', 'cloudpayments' ),
						'0' => __( 'НДС 0%', 'cloudpayments' ),
						'110' => __( 'расчетный НДС 10/110', 'cloudpayments' ),
						'120' => __( 'расчетный НДС 20/120', 'cloudpayments' ),
					),
				),
				'delivery_taxtype' => array(
					'title'       => __( 'Ставка НДС для доставки', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите ставку НДС, которая применима к доставке в магазине.', 'cloudpayments' ),
					'default'     => '10',
					'desc_tip'    => true,
					'options'     => array(
						'null' => __( 'НДС не облагается', 'cloudpayments' ),
						'20' => __( 'НДС 20%', 'cloudpayments' ),
						'10' => __( 'НДС 10%', 'cloudpayments' ),
						'0' => __( 'НДС 0%', 'cloudpayments' ),
						'110' => __( 'расчетный НДС 10/110', 'cloudpayments' ),
						'120' => __( 'расчетный НДС 20/120', 'cloudpayments' ),
					),
				),
				'kassa_taxsystem' => array(
					'title'       => __( 'Cистема налогообложения организации', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Указанная система налогообложения должна совпадать с одним из вариантов, зарегистрированных в ККТ.', 'cloudpayments' ),
					'default'     => '1',
					'desc_tip'    => true,
					'options'     => array(
						'0' => __( 'Общая система налогообложения', 'cloudpayments' ),
						'1' => __( 'Упрощенная система налогообложения (Доход)', 'cloudpayments' ),
						'2' => __( 'Упрощенная система налогообложения (Доход минус Расход)', 'cloudpayments' ),
						'3' => __( 'Единый налог на вмененный доход', 'cloudpayments' ),
						'4' => __( 'Единый сельскохозяйственный налог', 'cloudpayments' ),
						'5' => __( 'Патентная система налогообложения', 'cloudpayments' ),
					),
				),
				'kassa_method' => array(
					'title'       => __( 'Способ расчета', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите способ расчета', 'cloudpayments' ),
					'default'     => '1',
					'desc_tip'    => true,
					'options'     => array(
						'0' => __( 'Способ расчета не передается', 'cloudpayments' ),
						'1' => __( 'Предоплата 100%', 'cloudpayments' ),
						'2' => __( 'Предоплата', 'cloudpayments' ),
						'3' => __( 'Аванс', 'cloudpayments' ),
						'4' => __( 'Полный расчёт', 'cloudpayments' ),
						'5' => __( 'Частичный расчёт и кредит', 'cloudpayments' ),
						'6' => __( 'Передача в кредит', 'cloudpayments' ),
						'7' => __( 'Оплата кредита', 'cloudpayments' ),
					),
				),
				'kassa_object' => array(
					'title'       => __( 'Предмет расчета', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите предмет расчета', 'cloudpayments' ),
					'default'     => '1',
					'desc_tip'    => true,
					'options'     => array(
						'0' => __( 'Предмет расчета не передается', 'cloudpayments' ),
						'1' => __( 'Товар', 'cloudpayments' ),
						'2' => __( 'Подакцизный товар', 'cloudpayments' ),
						'3' => __( 'Работа', 'cloudpayments' ),
						'4' => __( 'Услуга', 'cloudpayments' ),
						'5' => __( 'Ставка азартной игры', 'cloudpayments' ),
						'6' => __( 'Выигрыш азартной игры', 'cloudpayments' ),
						'7' => __( 'Лотерейный билет', 'cloudpayments' ),
						'8' => __( 'Выигрыш лотереи', 'cloudpayments' ),
						'9' => __( 'Предоставление РИД', 'cloudpayments' ),
						'10' => __( 'Платеж', 'cloudpayments' ),
						'11' => __( 'Агентское вознаграждение', 'cloudpayments' ),
						'12' => __( 'Составной предмет расчета', 'cloudpayments' ),
						'13' => __( 'Иной предмет расчета', 'cloudpayments' ),
					),
				),
				'status_delivered' => array(
					'title'       => __( 'Статус которым пробивать 2ой чек при отгрузке товара или выполнении услуги', 'cloudpayments' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Согласно ФЗ-54 владельцы онлайн-касс должны формировать чеки для зачета и предоплаты. Отправка второго чека возможна только при следующих способах расчета: Предоплата, Предоплата 100%, Аванс', 'cloudpayments' ),
					'default'     => 'wc-pay_delivered',
					'desc_tip'    => true,
					'options'     => $array_status,
				),
				'kassa_skubarcode' => array(
								'title' 	=> __( 'Действие со штрих-кодом', 'cloudpayments' ),
								'type' 		=> 'checkbox',
								'label' 	=> __( 'Отправлять артикул (SKU) товара как штрих-код', 'cloudpayments' ),
								'default' 	=> 'yes'
				),
			);
		}

		// Process payment
		public function process_payment( $order_id ) {

			global $woocommerce;

			$order = new WC_Order( $order_id );

			return array(
				'result'    => 'success',
				'redirect'  => add_query_arg( 'key', $order->order_key, add_query_arg( 'order-pay', $order_id, $order->get_checkout_payment_url( true ) ) )
			);
		}

		// Output iframe
		public function payment_page( $order_id ) {
            $this->cpgwwc_addError("Проверка заказа");
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$title = array();
			$items_array = array();
			$items = $order->get_items();
			$shipping_data = array("label"=> __( 'Delivery', 'cloudpayments' ), "price"=>number_format((float)$order->get_total_shipping()+abs((float)$order->get_shipping_tax()), 2, '.', ''), "quantity"=>"1.00",	"amount"=>number_format((float)$order->get_total_shipping()+abs((float)$order->get_shipping_tax()), 2, '.', ''),
			"vat"=>($this->delivery_taxtype == "null") ? null : $this->delivery_taxtype, 'method'=> (int)$this->kassa_method, 'object'=>4, "ean"=>null);
			foreach ($items as $item) {
				if ($this->kassa_enabled == 'yes') {
				    $product = $order->get_product_from_item($item);
				    $items_array[] = array("label"=>$item['name'], "price"=>number_format((float)$product->get_price(), 2, '.', ''), "quantity"=>number_format((float)$item['quantity'], 2, '.', ''),
				    "amount"=>number_format((float)$item['total']+abs((float)$item['total_tax']), 2, '.', ''), "vat"=>($this->kassa_taxtype == "null") ? null : $this->kassa_taxtype,
				    'method'=> (int)$this->kassa_method, 'object'=> (int)$this->kassa_object,
				    "ean"=>($this->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null);
				}
				$title[] = $item['name'] . (isset($item['pa_ver']) ? ' ' . $item['pa_ver'] : '');
			}
			if ($this->kassa_enabled == 'yes' && $order->get_total_shipping() > 0) $items_array[] = $shipping_data;
			$kassa_array = array("cloudPayments"=>(array("customerReceipt"=>array("Items"=>$items_array, "taxationSystem"=>$this->kassa_taxsystem, 'calculationPlace'=>'www.'.$_SERVER['SERVER_NAME'],
			"email"=>$order->billing_email, "phone"=>$order->billing_phone))));
			$title = implode(', ', $title);

            $widget_f='charge';
            if ($this->enabledDMS!='no')
            {
                $widget_f='auth';
            }
			?>
			<script>
				var widget = new cp.CloudPayments({language: '<?=$this->language?>'});
		    	widget.<?=$widget_f?>({
		            publicId: '<?=$this->public_id?>',
		            description: __( 'Order payment', 'cloudpayments' ) . ' <?=$order_id?>',
		            amount: <?=$order->get_total()?>,
		            currency: '<?=$this->currency?>',
		            skin: '<?=$this->skin?>',
		            invoiceId: <?=$order_id?>,
		            accountId: '<?=$order->billing_email?>',
		            data:
		                <?php echo (($this->kassa_enabled == 'yes') ? json_encode($kassa_array) : "{}") ?>
		            },
			        function (options) {
						window.location.replace('<?=$this->get_return_url($order)?>');
			        },
			        function (reason, options) {
						window.location.replace('<?=$order->get_cancel_order_url()?>');
		        	}
		        );
			</script>

			<?php
		}
      	public function cpgwwc_processRequest($action,$request)
      	{
              $this->cpgwwc_addError("processRequest - action");
              $this->cpgwwc_addError(print_r($action,true));
              $this->cpgwwc_addError("processRequest - request");
              $this->cpgwwc_addError(print_r($request,true));
              $this->cpgwwc_addError("processRequest - params");
              $this->cpgwwc_addError(print_r($params,true));
              $this->cpgwwc_addError('processRequest - '.$action);
              $this->cpgwwc_addError(print_r($request,true));

              if ($action == 'check')
              {
                  return $this->cpgwwc_processCheckAction($request);
                  die();
              }
              else if ($action == 'fail')
              {
                  return $this->cpgwwc_processFailAction($request);
                  die();
              }
              else if ($action == 'pay')
              {
                  return $this->cpgwwc_processSuccessAction($request);
                  die();
              }
              else if ($action == 'refund')
              {
                  return $this->cpgwwc_processRefundAction($request);
                  die();
              }
              else if ($action == 'confirm')
              {
                  return $this->cpgwwc_processConfirmAction($request);
                  die();
              }
              else if ($action == 'cancel')
              {
                  return $this->cpgwwc_processRefundAction($request);
                  die();
              }
              else if ($action == 'void')
              {
                  return $this->cpgwwc_processRefundAction($request);
                  die();
              }
              else if ($action == 'receipt')
              {
                  return $this->cpgwwc_processReceiptAction($request);
                  die();
              }
              else
              {

                  $data['TECH_MESSAGE'] = 'Unknown action: '.$action;
                  $this->cpgwwc_addError('Unknown action: '.$action.'. Request='.print_r($request,true));
                  exit('{"code":0}');
              }
      	}

        private function cpgwwc_processReceiptAction($request)   //ok
        {

            //$request['InvoiceId']=$_POST['InvoiceId'];

            if ($request['Type'] == 'IncomeReturn') {
                $Type = 'возврата прихода';
            }
            elseif ($request['Type'] == 'Income') {
                $Type = 'прихода';
            }
            $url = $request['Url'];
            $note= 'Ссылка на чек '.$Type.': '.$url;
            $order=self::cpgwwc_get_order($request);
            $var = $order->add_order_note( $note, 1 );
            $order->save();
            $data['CODE'] = 0;
            echo json_encode($data);
            exit;

        }

        private function cpgwwc_processConfirmAction($request)   //ok
        {
            $order=self::cpgwwc_get_order($request);
            $data['CODE'] = 0;
            self::cpgwwc_OrderSetStatus($order,$this->status_pay);
            $this->cpgwwc_addError('PAY_COMPLETE');
            $this->cpgwwc_addError(print_r($request,true));
            $this->cpgwwc_addError(print_r($order,true));
            echo json_encode($data);
        }

       public function cpgwwc_Object_to_array($data)
       {
            if (is_array($data) || is_object($data))
            {
                $result = array();
                foreach ($data as $key => $value)
                {
                    $result[$key] = self::cpgwwc_Object_to_array($value);
                }
                return $result;
            }
            return $data;
       }
        private function cpgwwc_processRefundAction($request)
        {
            $order=self::cpgwwc_get_order($request);
            self::cpgwwc_OrderSetStatus($order,$this->status_chancel);
            $data['CODE'] = 0;
            echo json_encode($data);
        }

        private function cpgwwc_processSuccessAction($request)
        {
            $order=self::cpgwwc_get_order($request);
            $DMS_TYPE=$this->enabledDMS;
            $this->cpgwwc_addError(print_r($DMS_TYPE,1));
               $this->cpgwwc_addError("---------processSuccessAction--------");
            if ($DMS_TYPE=='yes'):
                  $data['CODE'] = 0;
                  self::cpgwwc_OrderSetStatus($order,$this->DMS_AU_status);
                  $this->cpgwwc_addError("-----".$request['TransactionId']);
                  $this->cpgwwc_addError('PAY_COMPLETE - DMS_AU_status');
            else:
                  $data['CODE'] = 0;
                  $order->payment_complete();
                  self::cpgwwc_OrderSetStatus($order,$this->status_pay);
                  $this->cpgwwc_addError('PAY_COMPLETE');
            endif;
           $this->cpgwwc_addError('----------data============');
           $this->cpgwwc_addError(print_r($data,true));
            WC()->cart->empty_cart();
            echo json_encode($data);
        }

        private function cpgwwc_processFailAction($request)
        {
            $order=self::cpgwwc_get_order($request);

            $data['CODE'] = 0;
            self::cpgwwc_OrderSetStatus($order,'wc-pending');
            echo json_encode($data);
        }

        public function cpgwwc_OrderSetStatus($order,$status)
        {
              $this->cpgwwc_addError("---------OrderSetStatus--------");
              $this->cpgwwc_addError(print_r($status,1));
              if ($order):
                $order->update_status($status);
                $this->cpgwwc_addError("---------OrderSetStatus999--------");
              endif;
        }

      	public function cpgwwc_processCheckAction($request)
      	{
              $this->cpgwwc_addError('processCheckAction');
              $order=self::cpgwwc_get_order($request);
              if (!$order):
                  json_encode(array("ERROR"=>'order empty'));
                  die();
              endif;
              $accesskey=trim($this->api_pass);

              if($this->cpgwwc_CheckHMac($accesskey))
              {
                  if ($this->cpgwwc_isCorrectSum($request,$order))
                  {
                      $data['CODE'] = 0;
                      $this->cpgwwc_addError( __( 'Correct Sum', 'cloudpayments' ) );
                  }
                  else
                  {
                      $data['CODE'] = 11;
                      $errorMessage = __( 'Incorrect payment sum', 'cloudpayments' );

                      $this->cpgwwc_addError($errorMessage);
                  }

                  $this->cpgwwc_addError( __( 'Order check', 'cloudpayments' ) );

                  $STATUS_CHANCEL= $this->chancel_status;

                  if($this->cpgwwc_isCorrectOrderID($order, $request))
                  {
                      $data['CODE'] = 0;
                  }
                  else
                  {
                      $data['CODE'] = 10;
                      $errorMessage = __( 'Incorrect order ID', 'cloudpayments' );
                      $this->cpgwwc_addError($errorMessage);
                  }

                  $orderID=$request['InvoiceId'];

                  if($order->has_status($this->status_pay)):
                      $data['CODE'] = 13;
                      $errorMessage = __( 'Order already paid', 'cloudpayments' );
                      $this->cpgwwc_addError($errorMessage);
                  else:
                      $data['CODE'] = 0;
                  endif;


                  if ($order->has_status($this->status_pay)|| $order->has_status($this->status_chancel))
                  {
                     $data['CODE'] = 13;
                  }
              }
              else
              {
                  $errorMessage='ERROR HMAC RECORDS';
                  $this->cpgwwc_addError($errorMessage);
                  $data['CODE']=5204;
              }

              $this->cpgwwc_addError(json_encode($data));

      		    echo json_encode($data);
      	}

        private function cpgwwc_isCorrectOrderID($order, $request)
        {
            $oid = $request['InvoiceId'];
            $paymentid = $order->get_id();
            $this->cpgwwc_addError('get_id->'.$paymentid);
            return round($paymentSum, 2) == round($sum, 2);
        }

      	private function cpgwwc_isCorrectSum($request,$order)
      	{
      		$sum = $request['Amount'];
      		$paymentSum = $order->get_total();
          $this->cpgwwc_addError('get_total->'.$paymentSum);

      		return round($paymentSum, 2) == round($sum, 2);
      	}


        private function cpgwwc_CheckHMac($APIPASS)
        {
            $headers = $this->cpgwwc_detallheaders();
            $this->cpgwwc_addError(print_r($headers,true));

            if (isset($headers['Content-HMAC']) || isset($headers['Content-Hmac']))
            {
                $this->cpgwwc_addError('HMAC_1');
                $this->cpgwwc_addError($APIPASS);
                $message = file_get_contents('php://input');
                $s = hash_hmac('sha256', $message, $APIPASS, true);
                $hmac = base64_encode($s);

                $this->cpgwwc_addError(print_r($hmac,true));
                if ($headers['Content-HMAC']==$hmac) return true;
                else if($headers['Content-Hmac']==$hmac) return true;
            }
            else return false;
        }

        private function cpgwwc_detallheaders()
        {
            if (!is_array($_SERVER)) {
                return array();
            }
            $headers = array();
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        }
        public function cpgwwc_addError($text)
        {
              $debug=false;
              if ($debug)
              {
                $file=plugin_dir_url( __FILE__ ).'log.txt';
                $current = file_get_contents($file);
                $current .= date("d-m-Y H:i:s").":".$text."\n";
                file_put_contents($file, $current);
              }
        }

      	public function cpgwwc_get_order($request)
        {
        	global $woocommerce;
        	$order = new WC_Order($request['InvoiceId']);
          return $order;
        }

		// Callback
        public function cpgwwc_handle_callback()
        {
          $this->cpgwwc_addError('handle_callback');
          self::cpgwwc_processRequest($_GET['action'],$_POST);
          exit;

        	$headers = cpgwwc_detallheaders();
        	if ((!isset($headers['Content-HMAC'])) and (!isset($headers['Content-Hmac'])))
          {
        		wp_mail(get_option('admin_email'), __( 'no headers set', 'cloudpayments' ), print_r($headers,1));
        		exit;
        	}
        	$message = file_get_contents('php://input');
          $posted = wp_unslash( $_POST );
    			$s = hash_hmac('sha256', $message, $this->api_pass, true);
    			$hmac = base64_encode($s);
    			if (!array_key_exists('Content-HMAC',$headers) && !array_key_exists('Content-Hmac',$headers) || (array_key_exists('Content-HMAC',$headers) && $headers['Content-HMAC'] != $hmac) || (array_key_exists('Content-Hmac',$headers) && $headers['Content-Hmac'] != $hmac))
          {
    			 wp_mail(get_option('admin_email'), __( 'CloudPayments payment signature is incorrect', 'cloudpayments' ), print_r($headers,1). '     payment: '. print_r($posted,1). '     HMAC: '. $hmac);
           exit("hmac error");
			    }
        	global $woocommerce;
        	$order = new WC_Order( $posted['InvoiceId'] );
        	if ($posted['Amount'] != $order->get_total())
          {
        		wp_mail(get_option('admin_email'), __( 'order amount is incorrect', 'cloudpayments' ), print_r($headers,1). '     payment: '. print_r($posted,1). '     order: '. print_r($order,1));
        		exit("sum error");
        	}
          update_post_meta($posted['InvoiceId'], 'cpgwwc_CloudPayments', json_encode($posted, JSON_UNESCAPED_UNICODE));
          if ($posted['Status'] == 'Completed')
          {

            if ($TYPE==2):
            else:
              $order->payment_complete();
              $order->add_order_note( __( 'Order successfully paid', 'cloudpayments')  );
              WC()->cart->empty_cart();
            endif;
          }
          else
          {
            $order->update_status( 'on-hold', __( 'Order pending payment', 'cloudpayments' ) );
            $order->add_order_note( __( 'Order pending payment', 'cloudpayments' ) );
            WC()->cart->empty_cart();
          }
          exit;
        }

        public function cpgwwc_update_order_status($order_id,$old_status,$new_status) //OK
        {
            if ($this->kassa_enabled == 'yes'):
                $this->cpgwwc_addError('update_order_statuskassa');
                $this->cpgwwc_addError('payment_methods');
                $this->cpgwwc_addError($this->status_pay."==wc-".$new_status);
                $request['InvoiceId']=$order_id;
                $order=self::cpgwwc_get_order($request);
                if ("wc-".$new_status == $this->status_delivered && ((int)$this->kassa_method == 1 || (int)$this->kassa_method == 2 || (int)$this->kassa_method == 3)):
                    self::cpgwwc_addError("Send kkt Income!");
                    $this->cpgwwc_addError('request');
                    $this->cpgwwc_addError(print_r($request,1));
                    self::cpgwwc_SendReceipt($order, 'Income',$old_status,$new_status);
                elseif ($this->status_chancel=="wc-".$new_status):
                    self::cpgwwc_addError("Send kkt IncomeReturn!");
                    self::cpgwwc_SendReceipt($order, 'IncomeReturn',$old_status,$new_status);
                endif;
            endif;
        }

        public function cpgwwc_SendReceipt($order,$type,$old_status,$new_status)
        {
            self::cpgwwc_addError('SendReceipt!!');
            $cart=$order->get_items();
            $total_amount = 0;

        	foreach ($cart as $item_id => $item_data):
                $product = $item_data->get_product();
                if ("wc-".$new_status == $this->status_delivered) {
                    $method = 4;
                }
                else {
                    $method = (int)$this->kassa_method;
                };
                $items[]=array(
                    'label'    => $product->get_name(),
                    'price'    => number_format($product->get_price(),2,".",''),
                    'quantity' => $item_data->get_quantity(),
                    'amount'   => number_format(floatval($item_data->get_total()),2,".",''),
                    'vat'      => $this->kassa_taxtype,
                    'method'   => $method,
                    'object'   => (int)$this->kassa_object,
                );

                $total_amount = $total_amount +  number_format(floatval($item_data->get_total()),2,".",'');

            endforeach;

            if ($order->get_total_shipping()):
                $items[]=array(
                    'label'=> __( 'Delivery', 'cloudpayments' ),
                    'price'=>$order->get_total_shipping(),
                    'quantity'=>1,
                    'amount'=>$order->get_total_shipping(),
                    'vat'=>$this->delivery_taxtype,
                    'method'   => $method,
                    'object'   => 4,
                );

                $total_amount = $total_amount + number_format(floatval($order->get_total_shipping()),2,".",'');

            endif;

            $data['cloudPayments']['customerReceipt']['Items']=$items;
            $data['cloudPayments']['customerReceipt']['taxationSystem']=$this->kassa_taxsystem;
            $data['cloudPayments']['customerReceipt']['calculationPlace']='www.'.$_SERVER['SERVER_NAME'];
            $data['cloudPayments']['customerReceipt']['email']=$order->get_billing_email();
            $data['cloudPayments']['customerReceipt']['phone']=$order->get_billing_phone();
            $data['cloudPayments']['customerReceipt']['amounts']['electronic']=$total_amount;

            if ("wc-".$new_status == $this->status_delivered) {
                $data['cloudPayments']['customerReceipt']['amounts']['electronic']=0;
                $data['cloudPayments']['customerReceipt']['amounts']['advancePayment']=$total_amount;
            }

  		    $aData = array(
  			    'Inn' => $this->inn,
      			'InvoiceId' => $order->get_id(), //номер заказа, необязательный
      			'AccountId' => $order->get_user_id(),
      			'Type' => $type,
      			'CustomerReceipt' => $data['cloudPayments']['customerReceipt']
  	    	);
            $API_URL='https://api.cloudpayments.ru/kkt/receipt';
            self::cpgwwc_send_request($API_URL,$aData);
            self::cpgwwc_addError("kkt/receipt");
        }

        public function cpgwwc_send_request($API_URL,$request)  ///OK
        {
            $request2=self::cpgwwc_cur_json_encode($request);
            $str=date("d-m-Y H:i:s").$request['Type'].$request['InvoiceId'].$request['AccountId'].$request['CustomerReceipt']['email'];
            $reque=md5($str);
            $auth = base64_encode($this->public_id. ":" . $this->api_pass);
            wp_remote_post( $API_URL, array(
	            'timeout'     => 30,
	            'redirection' => 5,
	            'httpversion' => '1.0',
	            'blocking'    => true,
	            'headers'     => array('Authorization' => 'Basic '.$auth, 'Content-Type' => 'application/json', 'X-Request-ID' => $reque),
	            'body'        => $request2,
	            'cookies'     => array()
            ) );
        }

        function cpgwwc_cur_json_encode($a=false)      /////ok
        {
            if (is_null($a) || is_resource($a)) {
                return 'null';
            }
            if ($a === false) {
                return 'false';
            }
            if ($a === true) {
                return 'true';
            }

            if (is_scalar($a)) {
                if (is_float($a)) {
                    $a = str_replace(',', '.', strval($a));
                }

                static $jsonReplaces = array(
                    array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'),
                    array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"')
                );

                return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
            }

            $isList = true;

            for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
                if (key($a) !== $i) {
                    $isList = false;
                    break;
                }
            }

            $result = array();

            if ($isList) {
                foreach ($a as $v) {
                    $result[] = self::cpgwwc_cur_json_encode($v);
                }

                return '[ ' . join(', ', $result) . ' ]';
            }
            else {
                foreach ($a as $k => $v) {
                    $result[] = self::cpgwwc_cur_json_encode($k) . ': ' . self::cpgwwc_cur_json_encode($v);
                }

                return '{ ' . join(', ', $result) . ' }';
            }
        }
	}
}
