<?php
namespace Opencart\Catalog\Controller\Checkout;

class Checkout extends \Opencart\System\Engine\Controller {
	public function index(): void {

		// 🔒 Require login with mobile verify
		if (!$this->customer->isLogged()) {
			$redirect = 'checkout/checkout';
			$this->response->redirect($this->url->link('account/mobile_verify', 'redirect=' . $redirect, true));
		}

		// 🔒 Minimum order validation
		if ($this->cart->getTotal() < 10000) {
			$this->session->data['error'] = 'Minimum order amount is ₹10,000 to proceed with checkout.';
			$this->response->redirect($this->url->link('checkout/cart', 'language=' . $this->config->get('config_language')));
		}

		// 🔒 Validate cart products
		if (
			!$this->cart->hasProducts() ||
			(!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) ||
			!$this->cart->hasMinimum()
		) {
			$this->response->redirect($this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true));
		}

		$this->load->language('checkout/checkout');
		$this->document->setTitle($this->language->get('heading_title'));

		// 🥖 Breadcrumbs
		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_cart'),
			'href' => $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'))
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'))
		];

		// 🟢 Inject dummy shipping address if missing (for shipping methods only)
		if ($this->cart->hasShipping() && empty($this->session->data['shipping_address'])) {
			$this->session->data['shipping_address'] = [
				'firstname'  => $this->customer->getFirstName(),
				'lastname'   => $this->customer->getLastName(),
				'address_1'  => '[DUMMY]', // 🚨 marker to detect dummy later
				'city'       => '',
				'postcode'   => '',
				'country_id' => $this->config->get('config_country_id'),
				'zone_id'    => $this->config->get('config_zone_id')
			];
		}

		// 🟢 Preload shipping methods
		if ($this->cart->hasShipping()) {
			$this->load->model('checkout/shipping_method');
			$this->session->data['shipping_methods'] =
				$this->model_checkout_shipping_method->getMethods($this->session->data['shipping_address']);

			// Select first available shipping method if not chosen
			if (!isset($this->session->data['shipping_method']) && !empty($this->session->data['shipping_methods'])) {
				$first = reset($this->session->data['shipping_methods']);
				if (isset($first['quote']) && is_array($first['quote'])) {
					$this->session->data['shipping_method'] = reset($first['quote']);
				}
			}
		}

		// 🟢 Ensure payment address session
// 🟢 Ensure payment address session
if ($this->customer->isLogged() && $this->config->get('config_checkout_payment_address')) {
    // Only copy from shipping if a real shipping address exists
    if (empty($this->session->data['payment_address']) &&
        isset($this->session->data['shipping_address']) &&
        !empty($this->session->data['shipping_address']['address_1']) &&
        $this->session->data['shipping_address']['address_1'] !== '[DUMMY]'
    ) {
        $this->session->data['payment_address'] = $this->session->data['shipping_address'];
    }
}


		// 🟢 Load checkout blocks
		$data['register'] = (!$this->customer->isLogged()) ? $this->load->controller('checkout/register') : '';
		$data['payment_address'] = ($this->customer->isLogged() && $this->config->get('config_checkout_payment_address'))
			? $this->load->controller('checkout/payment_address')
			: '';
		$data['shipping_address'] = ($this->customer->isLogged() && $this->cart->hasShipping())
			? $this->load->controller('checkout/shipping_address')
			: '';
		$data['shipping_method'] = $this->cart->hasShipping()
			? $this->load->controller('checkout/shipping_method')
			: '';
		$data['payment_method'] = $this->load->controller('checkout/payment_method');
		$data['confirm'] = $this->load->controller('checkout/confirm');

		// 🟢 Layout controllers
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
		$data['referral'] = $this->load->controller('checkout/referral');

		$this->response->setOutput($this->load->view('checkout/checkout', $data));
	}
}
