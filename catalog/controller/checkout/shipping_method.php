<?php
namespace Opencart\Catalog\Controller\Checkout;

class ShippingMethod extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('checkout/shipping_method');

		// 🔄 Always recalc shipping methods based on current address
		if ($this->cart->hasShipping()) {
			$this->load->model('checkout/shipping_method');

			$this->session->data['shipping_methods'] =
				$this->model_checkout_shipping_method->getMethods($this->session->data['shipping_address'] ?? []);

			// ✅ Auto-pick if not already selected
			if (!isset($this->session->data['shipping_method']) && !empty($this->session->data['shipping_methods'])) {
				$first = reset($this->session->data['shipping_methods']);
				if (isset($first['quote']) && is_array($first['quote'])) {
					$this->session->data['shipping_method'] = reset($first['quote']);
				}
			}
		}

		if (isset($this->session->data['shipping_method'])) {
			$data['shipping_method'] = $this->session->data['shipping_method']['name'];
			$data['code']            = $this->session->data['shipping_method']['code'];
			$data['shipping_cost'] = $this->session->data['shipping_method']['text'];
		} else {
			$data['shipping_method'] = '';
			$data['code']            = '';
			$data['shipping_cost'] = '';
		}

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('checkout/shipping_method', $data);
	}

	/**
	 * Quote
	 *
	 * @return void
	 */
	public function quote(): void {
		$this->load->language('checkout/shipping_method');

		$json = [];

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Validate if customer data is set
			if (!isset($this->session->data['customer'])) {
				$json['error'] = $this->language->get('error_customer');
			}

			// Validate if payment address is set if required
			if ($this->config->get('config_checkout_payment_address') && !isset($this->session->data['payment_address'])) {
				$json['error'] = $this->language->get('error_payment_address');
			}
		}

		if (!$json) {
			// 🚀 Inject dummy shipping address if missing
			if (empty($this->session->data['shipping_address'])) {
				$this->session->data['shipping_address'] = [
					'firstname'  => '',
					'lastname'   => '',
					'address_1'  => '',
					'city'       => '',
					'postcode'   => '',
					'country_id' => $this->config->get('config_country_id'),
					'zone_id'    => $this->config->get('config_zone_id')
				];
			}

			// Shipping method
			$this->load->model('checkout/shipping_method');

			$shipping_methods = $this->model_checkout_shipping_method->getMethods($this->session->data['shipping_address']);

			if ($shipping_methods) {
				$this->session->data['shipping_methods'] = $shipping_methods;
				$json['shipping_methods'] = $shipping_methods;

				// ✅ Auto-pick first if not already selected
				if (!isset($this->session->data['shipping_method'])) {
					$first = reset($shipping_methods);
					if (isset($first['quote']) && is_array($first['quote'])) {
						$this->session->data['shipping_method'] = reset($first['quote']);
					}
				}
			} else {
				$json['error'] = sprintf($this->language->get('error_no_shipping'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('checkout/shipping_method');

		$json = [];

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Validate if customer is logged in
			if (!isset($this->session->data['customer'])) {
				$json['error'] = $this->language->get('error_customer');
			}

			// Validate if payment address is set if required
			if ($this->config->get('config_checkout_payment_address') && !isset($this->session->data['payment_address'])) {
				$json['error'] = $this->language->get('error_payment_address');
			}
		}

		if (!$json) {
			// 🚀 Inject dummy shipping address if missing
			if (empty($this->session->data['shipping_address'])) {
				$this->session->data['shipping_address'] = [
					'firstname'  => '',
					'lastname'   => '',
					'address_1'  => '',
					'city'       => '',
					'postcode'   => '',
					'country_id' => $this->config->get('config_country_id'),
					'zone_id'    => $this->config->get('config_zone_id')
				];
			}

			// ✅ Always auto-select the first method, ignore POST
			if (!empty($this->session->data['shipping_methods'])) {
				$first = reset($this->session->data['shipping_methods']);
				if (isset($first['quote']) && is_array($first['quote'])) {
					$this->session->data['shipping_method'] = reset($first['quote']);
					$json['success'] = $this->language->get('text_success');
				} else {
					$json['error'] = $this->language->get('error_shipping_method');
				}
			} else {
				$json['error'] = $this->language->get('error_shipping_method');
			}

			// Clear payment methods
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
