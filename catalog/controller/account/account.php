<?php
namespace Opencart\Catalog\Controller\Account;

class Account extends \Opencart\System\Engine\Controller {

    public function index(): void {

        $this->load->language('account/account');

        // Login + token check
        if (
            !$this->customer->isLogged() ||
            !isset($this->request->get['customer_token']) ||
            !isset($this->session->data['customer_token']) ||
            ($this->request->get['customer_token'] != $this->session->data['customer_token'])
        ) {
            $this->session->data['redirect'] = $this->url->link(
                'account/account',
                'language=' . $this->config->get('config_language')
            );

            $this->response->redirect(
                $this->url->link(
                    'account/mobile_verify',
                    'language=' . $this->config->get('config_language'),
                    true
                )
            );
        }

        $this->document->setTitle($this->language->get('heading_title'));

        // Load customer model
        $this->load->model('account/customer');

        $customer_id   = $this->customer->getId();
        $customer_info = $this->model_account_customer->getCustomer($customer_id);

        // =============================
        // GET REWARD FROM CUSTOM FIELD (ID = 34)
        // =============================
$reward_points = 0;

$customer_info = $this->model_account_customer->getCustomer($customer_id);

if (!empty($customer_info['custom_field'][34])) {
    $reward_points = (int)$customer_info['custom_field'][34];
}

if (!empty($customer_info['custom_field'][30])) {
    $occupation = (int)$customer_info['custom_field'][30];
}

$data['reward_points'] = $reward_points;
$data['occupation'] = $occupation;
        // =============================


        // Format mobile
        $mobile_display = '';
        $mobile_verified = 0;

        if ($customer_info && !empty($customer_info['mobile'])) {
            $mobile_display = '+91' . $customer_info['mobile'];
            $mobile_verified = $customer_info['mobile_verified'] ?? 0;
        }

        $data['mobile_number']  = $mobile_display;
        $data['mobile_verified'] = $mobile_verified;
        $data['customer_name']  = ($customer_info['firstname'] ?? '') . ' ' . ($customer_info['lastname'] ?? '');
        $data['customer_email'] = $customer_info['email'] ?? '';

        // =============================
        // WHATSAPP REDEEM LINK
        // =============================
        $message = "Hello, I would like to redeem my reward points.\n\n"
            . "Name: " . $data['customer_name'] . "\n"
            . "Mobile: " . $mobile_display . "\n"
            . "Reward Points: " . $reward_points;

        $data['redeem_whatsapp'] = "https://wa.me/919717986812?text=" . urlencode($message);
        // =============================


        // Breadcrumbs
        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link(
                'common/home',
                'language=' . $this->config->get('config_language')
            )
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link(
                'account/account',
                'language=' . $this->config->get('config_language') .
                '&customer_token=' . $this->session->data['customer_token']
            )
        ];

        $data['success'] = $this->session->data['success'] ?? '';
        unset($this->session->data['success']);

        // Links
        $data['edit'] = $this->url->link(
            'account/edit',
            'language=' . $this->config->get('config_language') .
            '&customer_token=' . $this->session->data['customer_token']
        );

        $data['mobile_verify'] = $this->url->link(
            'account/mobile_verify',
            'language=' . $this->config->get('config_language') .
            '&customer_token=' . $this->session->data['customer_token']
        );

        $data['address'] = $this->url->link(
            'account/address',
            'language=' . $this->config->get('config_language') .
            '&customer_token=' . $this->session->data['customer_token']
        );

        $data['order'] = $this->url->link(
            'account/order',
            'language=' . $this->config->get('config_language') .
            '&customer_token=' . $this->session->data['customer_token']
        );

        $data['products'] = $this->url->link(
            'product/category',
            'path=63&language=' . $this->config->get('config_language')
        );

        $data['logout'] = $this->url->link(
            'account/logout',
            'language=' . $this->config->get('config_language'),
            true
        );

        $data['text_logout'] = $this->language->get('Logout');

        // Layout
        $data['column_left']   = $this->load->controller('common/column_left');
        $data['column_right']  = $this->load->controller('common/column_right');
        $data['content_top']   = $this->load->controller('common/content_top');
        $data['content_bottom']= $this->load->controller('common/content_bottom');
        $data['footer']        = $this->load->controller('common/footer');
        $data['header']        = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('account/account', $data));
    }
}