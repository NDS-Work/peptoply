<?php
namespace Opencart\Catalog\Controller\Common;

class HeaderControls extends \Opencart\System\Engine\Controller {
    public function index(): string {
        // Language
        $this->load->language('custom/header_controls');

        // User logged state
        $data['logged'] = $this->customer->isLogged();

        // URLs
        $language = $this->config->get('config_language');
        $customer_token = isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : '';

        $data['login_url'] = $this->url->link('account/login');
        $data['register_url'] = $this->url->link('account/register', 'language=' . $language);
        $data['account_url'] = $this->url->link('account/account', 'language=' . $language . $customer_token);
        $data['cart_url'] = $this->url->link('checkout/cart');

        // ✅ Logout URL (only shown if logged in)
        $data['logout_url'] = $this->url->link('account/logout');

        // Cart total items
        $data['cart_total'] = $this->getCartTotalCount();

        return $this->load->view('custom/header_controls', $data);
    }

    // Helper to calculate cart items
    private function getCartTotalCount(): int {
        $total_items = 0;
        foreach ($this->cart->getProducts() as $product) {
            $total_items += $product['quantity'];
        }
        return $total_items;
    }

    // New AJAX endpoint to return updated cart count
    public function cartCount(): void {
        $json = [
            'cart_total' => $this->getCartTotalCount()
        ];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
