<?php
/*
Plugin Name: WC Vendors - Integration with Aelia Currency Switcher
Plugin URI: https://aelia.co/
Description: Adds support for the Aelia Currency Switcher in WC Vendors.
Version: 1.0.0.170313
Author: Aelia
Author URI: https://aelia.co
License: GPLv3
*/

/**
 * Hides the products assigned to the "Vault of Sold Out Goods" from the
 * catalogue.
 *
 * @link https://aelia.freshdesk.com/helpdesk/tickets/4735
 */
class WC_Aelia_WCVendors_CS_Integration {
	/**
	 * The text domain used by the plugin.
	 *
	 * @var string
	 */
	protected static $text_domain = 'wc-aelia-wcvendors-cs-integration';

	protected static $_base_currency;
	protected static $_currency_names;

	const PV_SHOP_CURRENCY_FIELD = 'pv_shop_currency';

  /**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_hooks();
	}

	protected static function shop_base_currency() {
    if(empty(self::$_base_currency)) {
      self::$_base_currency = get_option('woocommerce_currency');
    }
    return self::$_base_currency;
  }

  /**
   * Returns the list of the currencies enabled in the Aelia Currency Switcher.
   * If the Currency Switcher is not installed, or active, then the list will
   * only contain the shop base currency.
   *
   * @return array An array of currency codes.
   */
  public static function enabled_currencies() {
    return apply_filters('wc_aelia_cs_enabled_currencies', array(self::shop_base_currency()));
  }


	/**
	 * Returns the description of a Currency.
	 *
	 * @param string currency The currency code.
	 * @return string The Currency description.
	 * @return string The Currency description.
	 */
	public static function get_currency_name($currency) {
		if(empty(self::$_currency_names)) {
			self::$_currency_names = get_woocommerce_currencies();
		}
		return isset(self::$_currency_names[$currency]) ? self::$_currency_names[$currency] : sprintf(__('Currency name not found for %s',
																																																		 self::$text_domain),
																																																	$currency);
	}

	/**
   * Converts an amount from one currency to another. Uses the functions provided
   * by the WooCommerce Currency Switcher, developed by Aelia.
   *
   * @param float amount The amount to convert.
   * @param string to_currency The destination currency.
   * @param string from_currency The source currency.
   * @return float The amount converted to the target destination currency.
   */
  public static function convert($amount, $to_currency, $from_currency = null) {
    if(empty($from_currency)) {
      $from_currency = self::shop_base_currency();
    }

    return apply_filters('wc_aelia_cs_convert', $amount, $from_currency, $to_currency);
  }

	/**
	 * Returns a vendor's currency.
	 *
	 * @param int vendor_id
	 * @param string default The default currency to return if the vendor has none
	 * associated.
	 * @return string
	 */
	protected static function get_pv_shop_currency($vendor_id, $default = null) {
		$currency = get_user_meta($vendor_id, self::PV_SHOP_CURRENCY_FIELD, true);
		if(empty($currency)) {
			$currency = $default;
		}
		return $currency;
	}

	/**
	 * Returns the Vendor ID for a product.
	 *
	 * @param int product_id
	 * @return int
	 */
	protected static function get_product_vendor_id($product_id) {
		return \WCV_Vendors::get_vendor_from_product($product_id);
	}

	/**
	 * Return the currency to be used to calculate commissions for a product.
	 *
	 * @param int product_id
	 * @return string
	 */
	protected static function get_product_commission_currency($product_id) {
		return self::get_pv_shop_currency(self::get_product_vendor_id($product_id),
																			self::shop_base_currency());
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit(plugins_url('/', __FILE__));
	}

  /**
   * Sets all actions and filters used by the plugin.
   */
	protected function set_hooks() {
		add_action('plugins_loaded', array($this, 'plugins_loaded'), 10);

		add_action('wcvendors_settings_after_paypal', array($this, 'wcvendors_settings_after_paypal'), 10);
		add_action('wcvendors_shop_settings_saved', array($this, 'save_vendor_currency_data'), 10, 1);
		add_action('wcvendors_shop_settings_admin_saved', array($this, 'save_vendor_currency_data'), 10, 1);

		add_filter('wcv_commission_rate', array($this, 'wcv_commission_rate'), 10, 5);

		// Admin
		add_action('admin_init', array($this, 'admin_init'), 10, 1);
	}

	/**
	 * Performs actions when all plugins have been loaded.
	 */
	public function plugins_loaded() {
		// Load the plugin's text domain, to allow localisation
		load_plugin_textdomain(self::$text_domain, false, dirname(plugin_basename(__FILE__)) . '/languages/');
	}

	/**
	 * Renders the currency selector on vendor's profile page.
	 */
	public function wcvendors_settings_after_paypal() {
		$pv_shop_currency = self::get_pv_shop_currency(get_current_user_id());
		?>
		<tr id="pv_shop_currency_container">
			<th>
				<h4><?php
					_e('Currency for commissions', self::$text_domain);
				?></h4>
			</th>
			<td>
				<?php if(empty($pv_shop_currency)): ?>
					<?php $shop_currency = self::shop_base_currency(); ?>
					<select id="pv_shop_currency" name="pv_shop_currency"><?php
						foreach(self::enabled_currencies() as $currency) {
							$selected = selected($currency, $shop_currency, false);
							?>
							<option value="<?= $currency ?>" <?= $selected ?>><?= self::get_currency_name($currency) ?></option>
							<?php
						}
					?></select>
					<p class="description">
						<?php _e('Select the currency in which you will receive your commissions.', self::$text_domain); ?><br/>
						<strong><?php
							_e('This selection cannot be changed later.', self::$text_domain); ?>
						</strong>
					</p>
				<?php else: ?>
					<span><?php
						echo sprintf('%s (%s)', $pv_shop_currency, self::get_currency_name($pv_shop_currency));
					?></span>
					<p class="description"><?php
						_e('This currency cannot be changed, because it would invalidate the purchase data collected so far.', self::$text_domain);
						echo '<br>';
						_e('If you wish to accept commissions in another currency, please open a second vendor account.', self::$text_domain); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saves vendor's currency preferences.
	 *
	 * @param int user_id Vendor's user ID.
	 */
	public function save_vendor_currency_data($user_id) {
		$pv_shop_currency = !empty($_POST[self::PV_SHOP_CURRENCY_FIELD]) ? $_POST[self::PV_SHOP_CURRENCY_FIELD] : self::shop_base_currency();

		update_user_meta($user_id, self::PV_SHOP_CURRENCY_FIELD, $_POST[self::PV_SHOP_CURRENCY_FIELD]);
	}

	/**
	 * Calculates vendor's commission for a specific product, in vendor's currency.
	 *
	 * NOTE
	 * The commission is recalculated from scratch because WC Vendors applies a
	 * rounding to it, before passing it to the filter.	 *
	 *
	 * @param float commission
	 * @param int product_id
	 * @param float product_price
	 * @param WC_Order order
	 * @param int qty
	 * @return float
	 */
	public function wcv_commission_rate($commission, $product_id, $product_price, $order, $qty) {
		$commission_currency = self::get_product_commission_currency($product_id);
		$commission_rate_percent = WCV_Commission::get_commission_rate($product_id);

		$order_currency = $order->get_order_currency();
		$product_price = self::convert($product_price, $commission_currency, $order_currency);

		$commission = round($product_price * ($commission_rate_percent / 100), 2);

		return apply_filters('wc_aelia_wcvendors_cs_integration_calculated_commission', $commission, $product_id, $product_price, $order, $qty, $commission_currency);
	}

	/**
	 * Performs actions in when an admin page loads.
	 *
	 */
	public function admin_init() {
		if(empty($_GET['page'])) {
			return;
		}

		// On the Vendor Orders page, the currency should be the one from vendor's
		// profile
		if($_GET['page'] === 'wcv-vendor-orders') {
			add_filter('woocommerce_currency', array($this, 'set_active_currency_to_pv_shop_currency'), 10, 1);
		}
	}

	/**
	 * Sets the active currency to the one from current vendor's profile.
	 *
	 * @param string currency
	 * @return string
	 */
	public function set_active_currency_to_pv_shop_currency($currency) {
		$vendor_id = get_current_user_id();
		if(!empty($vendor_id)) {
			$currency = self::get_pv_shop_currency($vendor_id, self::shop_base_currency());
		}

		return $currency;
	}
}


$GLOBALS['wc-aelia-wcvendors-cs-integration'] = new WC_Aelia_WCVendors_CS_Integration();
