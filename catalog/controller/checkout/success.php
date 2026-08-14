<?php
namespace Opencart\Catalog\Controller\Checkout;

class Success extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('checkout/success');

        if (isset($this->session->data['order_id'])) {
            $order_id = $this->session->data['order_id'];

            // Load order info
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($order_id);

            $data['order_id'] = $order_id;
            $data['order_total'] = $order_info['total'];
            $data['order_date'] = date('d-m-Y', strtotime($order_info['date_added']));

            // Clear session/cart data
            $this->cart->clear();
            unset($this->session->data['order_id']);
            unset($this->session->data['payment_method']);
            unset($this->session->data['payment_methods']);
            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
            unset($this->session->data['comment']);
            unset($this->session->data['agree']);
            unset($this->session->data['coupon']);
            unset($this->session->data['reward']);
        } else {
            $data['order_id'] = null;
            $data['order_total'] = null;
            $data['order_date'] = null;
        }

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_basket'),
            'href' => $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'))
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'))
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_success'),
            'href' => $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'))
        ];

        // Message
        if ($this->customer->isLogged()) {
            $data['text_message'] = sprintf(
                $this->language->get('text_customer'),
                $this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']),
                $this->url->link('account/order', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']),
                $this->url->link('account/download', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']),
                $this->url->link('information/contact', 'language=' . $this->config->get('config_language'))
            );
        } else {
            $data['text_message'] = sprintf(
                $this->language->get('text_guest'),
                $this->url->link('information/contact', 'language=' . $this->config->get('config_language'))
            );
        }

        $data['continue'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
        $data['url_account_orders'] = $this->url->link('account/order', 'language=' . $this->config->get('config_language') . '&customer_token=' . ($this->session->data['customer_token'] ?? ''));

        // Load common controllers
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('checkout/success_redirect', $data));
    }
}
