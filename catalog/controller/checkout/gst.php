<?php
namespace Opencart\Catalog\Controller\Checkout;

class Gst extends \Opencart\System\Engine\Controller {
    public function index(): string {
        if (!$this->customer->isLogged()) return '';

        $this->load->model('account/customer');
        $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

        // Extract GST number from custom field
        $gst_number = '';
        if (!empty($customer_info['custom_field'])) {
            $custom_fields = is_array($customer_info['custom_field'])
                ? $customer_info['custom_field']
                : json_decode($customer_info['custom_field'], true);

            $gst_number = $custom_fields[33] ?? '';
        }

        // Default checked if GST number exists or if already used this session
        $data['gst_number'] = $gst_number;
        $data['use_gst'] = isset($this->session->data['order_gst'])
            ? true
            : !empty($gst_number);
        $data['language'] = $this->config->get('config_language');

        return $this->load->view('checkout/gst', $data);
    }

public function save(): void {
    $json = [];

    if (!$this->customer->isLogged()) {
        $json['error'] = 'You must be logged in.';
    } else {
        $customer_id = (int)$this->customer->getId();
        $gst_number = trim($this->request->post['gst_number'] ?? '');
        $use_gst = !empty($this->request->post['use_gst']);

        // 1. FETCH CURRENT DB DATA
        $this->load->model('account/customer');
        $customer_info = $this->model_account_customer->getCustomer($customer_id);
        $custom_fields = is_array($customer_info['custom_field']) 
            ? $customer_info['custom_field'] 
            : json_decode($customer_info['custom_field'] ?? '[]', true);

        // 2. ALWAYS UPDATE DATABASE (If they provided a number)
        if ($gst_number !== '') {
            $custom_fields[33] = $gst_number;
            $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `custom_field` = '" . $this->db->escape(json_encode($custom_fields)) . "' WHERE `customer_id` = '" . $customer_id . "'");
        } else {
            // Fallback: If they submitted an empty field, fetch what was already in the DB
            $gst_number = $custom_fields[33] ?? ''; 
        }

        // 3. SESSION LOGIC (Are they applying it to this order?)
        if (!$use_gst || $gst_number === '') {
            unset($this->session->data['order_gst']);
            $json['success'] = 'GST not applied to this order.';
        } else {
            $this->session->data['order_gst'] = $gst_number;
            $json['success'] = 'GST applied to order successfully.';
        }

        // 4. RETURN THE EXACT STATE TO THE JAVASCRIPT
        $json['current_session_gst'] = $this->session->data['order_gst'] ?? '';
        $json['is_active'] = isset($this->session->data['order_gst']);
        $json['db_gst'] = $custom_fields[33] ?? ''; // <-- Your new variable
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
}
}
