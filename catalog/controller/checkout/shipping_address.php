<?php
namespace Opencart\Catalog\Controller\Checkout;

/**
 * Class ShippingAddress
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class ShippingAddress extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('checkout/shipping_address');

		$data['error_upload_size'] = sprintf($this->language->get('error_upload_size'), $this->config->get('config_file_max_size'));
		$data['config_file_max_size'] = ((int)$this->config->get('config_file_max_size') * 1024 * 1024);
		$data['payment_address_required'] = $this->config->get('config_checkout_payment_address');

		$this->ensureSameAsBillingDefault();

		$this->session->data['upload_token'] = oc_token(32);
		$data['upload'] = $this->url->link('tool/upload', 'language=' . $this->config->get('config_language') . '&upload_token=' . $this->session->data['upload_token']);

		// Address
		$this->load->model('account/address');
		$data['addresses'] = $this->model_account_address->getAddresses($this->customer->getId());

		$data['address_id'] = $this->session->data['shipping_address']['address_id'] ?? 0;

		if (isset($this->session->data['shipping_address'])) {
			$data['postcode'] = $this->session->data['shipping_address']['postcode'] ?? '';
			$data['country_id'] = $this->session->data['shipping_address']['country_id'] ?? (int)$this->config->get('config_country_id');
			$data['zone_id'] = $this->session->data['shipping_address']['zone_id'] ?? '';
		} else {
			$data['postcode'] = '';
			$data['country_id'] = (int)$this->config->get('config_country_id');
			$data['zone_id'] = '';
		}

		// Force India
		$data['country_id'] = 99;

		$data['firstname'] = $this->customer->getFirstName();
		$data['lastname']  = $this->customer->getLastName();

		// Zone
		$this->load->model('localisation/zone');
		$data['zones'] = $this->model_localisation_zone->getZonesByCountryId($data['country_id']);

		// Custom Fields
		$data['custom_fields'] = [];

		$this->load->model('account/custom_field');
		$custom_fields = $this->model_account_custom_field->getCustomFields($this->customer->getGroupId());

		foreach ($custom_fields as $custom_field) {
			if ($custom_field['location'] == 'address') {
				$data['custom_fields'][] = $custom_field;
			}
		}

		$data['language'] = $this->config->get('config_language');

		// If we already have a real shipping address and the flag is enabled,
		// keep billing in sync on initial render too.
		if ($this->isSameAsBillingEnabled()) {
			$this->syncPaymentToShipping();
		}

		// Keep checkout stable on initial load as well.
		if ($this->cart->hasShipping() && $this->hasRealShippingAddress() && !isset($this->session->data['shipping_method'])) {
			$this->autoSelectFirstShippingMethod();
			$this->recomputePaymentMethods();
		}

		return $this->load->view('checkout/shipping_address', $data);
	}

	private function ensureSameAsBillingDefault(): void {
		if (!isset($this->session->data['same_as_billing'])) {
			$this->session->data['same_as_billing'] = 1;
		}
	}

	private function isSameAsBillingEnabled(): bool {
		return !empty($this->session->data['same_as_billing']);
	}

	private function hasRealShippingAddress(): bool {
		if (empty($this->session->data['shipping_address']) || !is_array($this->session->data['shipping_address'])) {
			return false;
		}

		// Avoid copying the dummy marker used elsewhere in this codebase
		if (!empty($this->session->data['shipping_address']['address_1']) && $this->session->data['shipping_address']['address_1'] === '[DUMMY]') {
			return false;
		}

		return true;
	}

	private function syncPaymentToShipping(): void {
		if (!$this->hasRealShippingAddress()) {
			return;
		}

		$this->session->data['payment_address'] = $this->session->data['shipping_address'];
	}

	private function autoSelectFirstShippingMethod(): void {
		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);

		if (empty($this->session->data['shipping_address'])) {
			return;
		}

		$this->load->model('checkout/shipping_method');
		$methods = $this->model_checkout_shipping_method->getMethods($this->session->data['shipping_address']);

		if (!$methods) {
			return;
		}

		$this->session->data['shipping_methods'] = $methods;

		$first_method = reset($methods);
		if (!$first_method || empty($first_method['quote']) || !is_array($first_method['quote'])) {
			return;
		}

		$first_option = reset($first_method['quote']);
		if ($first_option) {
			$this->session->data['shipping_method'] = $first_option;
		}
	}

	private function recomputePaymentMethods(): void {
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);

		$this->load->model('checkout/payment_method');

		if ($this->config->get('config_checkout_payment_address')) {
			$payment_address = $this->session->data['payment_address'] ?? [];
		} else {
			$payment_address = $this->session->data['shipping_address'] ?? [];
		}

		$methods = $this->model_checkout_payment_method->getMethods($payment_address);

		if (!$methods) {
			return;
		}

		$this->session->data['payment_methods'] = $methods;

	}

	private function applyShippingSideEffects(): void {
		// If same-as-billing is ON, overwrite billing immediately (required behavior)
		if ($this->isSameAsBillingEnabled()) {
			$this->syncPaymentToShipping();
		}

		// Shipping changes can affect both shipping + payment availability
		$this->autoSelectFirstShippingMethod();
		$this->recomputePaymentMethods();
	}

	/**
	 * Save new shipping address
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('checkout/shipping_address');

		$this->ensureSameAsBillingDefault();

		$json = [];

		$required = [
			'firstname'    => '',
			'lastname'     => '',
			'company'      => '',
			'address_1'    => '',
			'address_2'    => '',
			'city'         => '',
			'postcode'     => '',
			'country_id'   => 0,
			'zone_id'      => 0,
			'custom_field' => []
		];

		$post_info = $this->request->post + $required;
		$post_info['country_id'] = 99;

		// Validate cart
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate login
		if (!$this->customer->isLogged() || !isset($this->session->data['customer'])) {
			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate shipping required
		if (!$this->cart->hasShipping()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Validate fields
			if (!oc_validate_length($post_info['firstname'], 1, 32)) {
				$json['error']['firstname'] = $this->language->get('error_firstname');
			}
			if (!oc_validate_length($post_info['lastname'], 1, 32)) {
				$json['error']['lastname'] = $this->language->get('error_lastname');
			}
			if (!oc_validate_length($post_info['address_1'], 3, 128)) {
				$json['error']['address_1'] = $this->language->get('error_address_1');
			}
			if (!oc_validate_length($post_info['city'], 2, 128)) {
				$json['error']['city'] = $this->language->get('error_city');
			}

			// Country validation
			$this->load->model('localisation/country');
			$country_info = $this->model_localisation_country->getCountry((int)$post_info['country_id']);

			if ($country_info && $country_info['postcode_required'] && !oc_validate_length($post_info['postcode'], 2, 10)) {
				$json['error']['postcode'] = $this->language->get('error_postcode');
			}

			if (!$country_info) {
				$json['error']['country'] = $this->language->get('error_country');
			}

			// Zone validation
			$this->load->model('localisation/zone');
			$zone_total = $this->model_localisation_zone->getTotalZonesByCountryId((int)$post_info['country_id']);
			if ($zone_total && !$post_info['zone_id']) {
				$json['error']['zone'] = $this->language->get('error_zone');
			}

			// Custom fields
			$this->load->model('account/custom_field');
			$custom_fields = $this->model_account_custom_field->getCustomFields($this->customer->getGroupId());
			foreach ($custom_fields as $custom_field) {
				if ($custom_field['location'] == 'address') {
					if ($custom_field['required'] && empty($post_info['custom_field'][$custom_field['custom_field_id']])) {
						$json['error']['custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
					} elseif (($custom_field['type'] == 'text') && !empty($custom_field['validation']) && !oc_validate_regex($post_info['custom_field'][$custom_field['custom_field_id']], $custom_field['validation'])) {
						$json['error']['custom_field_' . $custom_field['custom_field_id']] = sprintf($this->language->get('error_regex'), $custom_field['name']);
					}
				}
			}
		}

		if (!$json) {
			$this->load->model('account/address');

			$address_id = $this->model_account_address->addAddress($this->customer->getId(), $post_info);
			$this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->customer->getId(), $address_id);

			$this->applyShippingSideEffects();

			$json['success'] = $this->language->get('text_success');
			$json['address_id'] = $address_id;
			$json['addresses'] = $this->model_account_address->getAddresses($this->customer->getId());
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Select existing shipping address
	 *
	 * @return void
	 */
	public function address(): void {
		$this->load->language('checkout/shipping_address');

		$this->ensureSameAsBillingDefault();

		$json = [];

		$address_id = isset($this->request->get['address_id']) ? (int)$this->request->get['address_id'] : 0;

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate if customer is logged in or customer session data is not set
		if (!$this->customer->isLogged() || !isset($this->session->data['customer'])) {
			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate if shipping is not required
		if (!$this->cart->hasShipping()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			$this->load->model('account/address');
			$address_info = $this->model_account_address->getAddress($this->customer->getId(), $address_id);

			if (!$address_info) {
				$json['error'] = $this->language->get('error_address');

				unset($this->session->data['shipping_address']);
				unset($this->session->data['shipping_method']);
				unset($this->session->data['shipping_methods']);

				unset($this->session->data['payment_address']);
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);
			}
		}

		if (!$json) {
			$this->session->data['shipping_address'] = $address_info;
			$this->applyShippingSideEffects();
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Toggle “Use Same Address for Billing”
	 * POST: same_as_billing = 0|1
	 *
	 * @return void
	 */
	public function sameAsBilling(): void {
		$this->load->language('checkout/shipping_address');

		$json = [];

		// Validate cart
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		// Validate login
		if (!$this->customer->isLogged() || !isset($this->session->data['customer'])) {
			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			$value = isset($this->request->post['same_as_billing']) ? (int)$this->request->post['same_as_billing'] : 0;
			$this->session->data['same_as_billing'] = $value ? 1 : 0;

			// If enabling, immediately overwrite billing with shipping (required behavior)
			if ($this->session->data['same_as_billing'] === 1) {
				$this->syncPaymentToShipping();
			}

			// Ensure a shipping method exists for payment availability
			if ($this->cart->hasShipping() && $this->hasRealShippingAddress() && !isset($this->session->data['shipping_method'])) {
				$this->autoSelectFirstShippingMethod();
			}

			// Payment methods depend on billing/shipping method; recompute now.
			$this->recomputePaymentMethods();

			$json['success'] = 'OK';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
