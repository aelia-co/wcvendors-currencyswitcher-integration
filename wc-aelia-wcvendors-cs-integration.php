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
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit(plugins_url('/', __FILE__));
	}

	protected function load_user_data() {
		$this->current_user_id = get_current_user_id();

		$this->pv_shop_currency = get_user_meta($this->current_user_id, self::PV_SHOP_CURRENCY_FIELD, true);
	}

  /**
   * Sets all actions and filters used by the plugin.
   */
	protected function set_hooks() {
		add_action('plugins_loaded', array($this, 'plugins_loaded'), 10);

		add_action('wcvendors_settings_after_paypal', array($this, 'wcvendors_settings_after_paypal'), 10);
		add_action('wcvendors_shop_settings_saved', array($this, 'save_vendor_currency_data'), 10, 1);
		add_action('wcvendors_shop_settings_admin_saved', array($this, 'save_vendor_currency_data'), 10, 1);
	}

	/**
	 * Performs actions when all plugins have been loaded.
	 */
	public function plugins_loaded() {
		// Load the plugin's text domain, to allow localisation
		load_plugin_textdomain(self::$text_domain, false, dirname(plugin_basename(__FILE__)) . '/languages/');
	}

	public function wcvendors_settings_after_paypal() {
		$this->load_user_data();
		?>
		<tr id="pv_shop_currency_container">
			<th>
				<h4><?php
					_e('Currency', self::$text_domain);
				?></h4>
			</th>
			<td>
				<?php if(empty($this->pv_shop_currency)): ?>
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
						<?php _e('Select the currency in which you will sell your products.', self::$text_domain); ?><br/>
						<strong><?php
							_e('This selection cannot be changed later.', self::$text_domain); ?>
						</strong>
					</p>
				<?php else: ?>
					<span><?php
						echo sprintf('%s (%s)', $this->pv_shop_currency, self::get_currency_name($this->pv_shop_currency));
					?></span>
					<strong><?php
						_e('This currency cannot be changed, because it would invalidate the shop data collected so far.', self::$text_domain); ?>
					</strong>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	public function save_vendor_currency_data($user_id) {
		$pv_shop_currency = !empty($_POST[self::PV_SHOP_CURRENCY_FIELD]) ? $_POST[self::PV_SHOP_CURRENCY_FIELD] : self::shop_base_currency();

		update_user_meta($user_id, self::PV_SHOP_CURRENCY_FIELD, $_POST[self::PV_SHOP_CURRENCY_FIELD]);
	}
}

$GLOBALS['wc-aelia-wcvendors-cs-integration'] = new WC_Aelia_WCVendors_CS_Integration();
