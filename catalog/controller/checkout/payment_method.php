<?php
namespace Opencart\Catalog\Controller\Checkout;

class PaymentMethod extends \Opencart\System\Engine\Controller {

    /**
     * Index — renders the payment method + GST block
     */
    public function index(): string {
        $this->load->language('checkout/payment_method');

        // ── Payment data ──────────────────────────────────────────
        $data['payment_method'] = $this->session->data['payment_method']['name'] ?? '';
        $data['code']           = $this->session->data['payment_method']['code'] ?? '';
        $data['comment']        = $this->session->data['comment'] ?? '';
        $data['agree']          = $this->session->data['agree']   ?? '';

        // Agreement text
        $this->load->model('catalog/information');
        $information_info = $this->model_catalog_information->getInformation(
            (int)$this->config->get('config_checkout_id')
        );

        if ($information_info) {
            $data['text_agree'] = sprintf(
                $this->language->get('text_agree'),
                $this->url->link(
                    'information/information.info',
                    'language=' . $this->config->get('config_language') . '&information_id=' . $this->config->get('config_checkout_id')
                ),
                $information_info['title']
            );
        } else {
            $data['text_agree'] = '';
        }

        // ── GST data ──────────────────────────────────────────────
        $gst_number = '';

        if ($this->customer->isLogged()) {
            $this->load->model('account/customer');
            $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

            if (!empty($customer_info['custom_field'])) {
                $custom_fields = is_array($customer_info['custom_field'])
                    ? $customer_info['custom_field']
                    : json_decode($customer_info['custom_field'], true);

                $gst_number = $custom_fields[33] ?? '';
            }
        }

        $data['gst_number'] = $gst_number;
        $data['use_gst']    = isset($this->session->data['order_gst'])
            ? true
            : !empty($gst_number);

        $data['language'] = $this->config->get('config_language');

        return $this->load->view('checkout/payment_method', $data);
    }

    /**
     * Get Payment Methods
     */
    public function getMethods(): void {
        $this->load->language('checkout/payment_method');

        $json = [];

        if (
            !$this->cart->hasProducts() ||
            (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) ||
            !$this->cart->hasMinimum()
        ) {
            $json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
        }

        if (!$json) {
            if (!isset($this->session->data['customer'])) {
                $this->resetPaymentState();
                $json['error'] = $this->language->get('error_customer');
            }

            if ($this->config->get('config_checkout_payment_address')
                && empty($this->session->data['payment_address']['address_1'])
            ) {
                $this->resetPaymentState();
                $json['error'] = $this->language->get('error_payment_address');
            }

            if ($this->cart->hasShipping()) {
                if (empty($this->session->data['shipping_address']['address_id'])) {
                    $this->resetPaymentState();
                    $json['error'] = $this->language->get('error_shipping_address');
                }

                if (!isset($this->session->data['shipping_method'])) {
                    $this->resetPaymentState();
                    $json['error'] = $this->language->get('error_shipping_method');
                }
            }
        }

        if (!$json) {
            if (
                $this->config->get('config_checkout_payment_address') &&
                isset($this->session->data['payment_address'])
            ) {
                $payment_address = $this->session->data['payment_address'];
            } else {
                $payment_address = $this->session->data['shipping_address'] ?? [];
            }

            $this->load->model('checkout/payment_method');
            $payment_methods = $this->model_checkout_payment_method->getMethods($payment_address);

            if ($payment_methods) {
                $this->session->data['payment_methods'] = $payment_methods;
                $json['payment_methods'] = $payment_methods;
            } else {
                $this->resetPaymentState();
                $json['error'] = sprintf(
                    $this->language->get('error_no_payment'),
                    $this->url->link('information/contact', 'language=' . $this->config->get('config_language'))
                );
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * SINGLE ENDPOINT — saves payment method + GST atomically in one session write.
     *
     * POST params:
     *   payment_method  string   e.g. "cod.cod"
     *   gst_number      string   GST number (may be empty)
     *   use_gst         int      1 = apply to order, 0 = do not apply
     */
    public function saveCheckoutState(): void {
        $this->load->language('checkout/payment_method');

        $json = [];

        // ── Cart validation ───────────────────────────────────────
        if (
            !$this->cart->hasProducts() ||
            (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) ||
            !$this->cart->hasMinimum()
        ) {
            $json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
            $this->jsonResponse($json);
            return;
        }

        // ── Address / shipping validation ─────────────────────────
        if ($this->config->get('config_checkout_payment_address')
            && empty($this->session->data['payment_address']['address_1'])
        ) {
            $this->resetPaymentState();
            $json['error'] = $this->language->get('error_payment_address');
        }

        if (!$json && $this->cart->hasShipping()) {
            if (empty($this->session->data['shipping_address']['address_id'])) {
                $this->resetPaymentState();
                $json['error'] = $this->language->get('error_shipping_address');
            }

            if (!isset($this->session->data['shipping_method'])) {
                $this->resetPaymentState();
                $json['error'] = $this->language->get('error_shipping_method');
            }
        }

        if ($json) {
            $this->jsonResponse($json);
            return;
        }

        // ── Validate payment method code ──────────────────────────
        $payment_method_code = $this->request->post['payment_method'] ?? '';

        if (!$payment_method_code || !isset($this->session->data['payment_methods'])) {
            $json['error'] = $this->language->get('error_payment_method');
            $this->jsonResponse($json);
            return;
        }

        $payment = explode('.', $payment_method_code);

        if (
            !isset($payment[0], $payment[1]) ||
            !isset($this->session->data['payment_methods'][$payment[0]]['option'][$payment[1]])
        ) {
            $json['error'] = $this->language->get('error_payment_method');
            $this->jsonResponse($json);
            return;
        }

        // ── Resolve GST ───────────────────────────────────────────
        $gst_number    = '';
        $custom_fields = [];
        $use_gst       = !empty($this->request->post['use_gst']);

        if ($this->customer->isLogged()) {
            $customer_id = (int)$this->customer->getId();

            $this->load->model('account/customer');
            $customer_info = $this->model_account_customer->getCustomer($customer_id);
            $custom_fields = is_array($customer_info['custom_field'])
                ? $customer_info['custom_field']
                : json_decode($customer_info['custom_field'] ?? '[]', true);

            $submitted_gst = trim($this->request->post['gst_number'] ?? '');

            if ($submitted_gst !== '') {
                // New number — persist to DB
                $custom_fields[33] = $submitted_gst;
                $this->db->query(
                    "UPDATE `" . DB_PREFIX . "customer`
                     SET `custom_field` = '" . $this->db->escape(json_encode($custom_fields)) . "'
                     WHERE `customer_id` = '" . $customer_id . "'"
                );
                $gst_number = $submitted_gst;
            } else {
                // Use whatever is already in DB
                $gst_number = $custom_fields[33] ?? '';
            }
        }

        // ── SINGLE ATOMIC SESSION WRITE ───────────────────────────
        // Payment method and GST are committed together — the session
        // is never readable in a half-updated state between two requests.
        $this->session->data['payment_method'] =
            $this->session->data['payment_methods'][$payment[0]]['option'][$payment[1]];

        if ($use_gst && $gst_number !== '') {
            $this->session->data['order_gst'] = $gst_number;
        } else {
            unset($this->session->data['order_gst']);
        }

        // ═══════════════════════════════════════════════════════════════
        // 🔴 BUG FIX #1: Clear cached order ID when payment/GST changes
        // ═══════════════════════════════════════════════════════════════
        // When payment method or GST changes, we must invalidate the cached
        // order so that confirm.php will CREATE A FRESH ORDER with updated
        // data instead of retrieving the old order from the database.
        // Without this, confirm block would retrieve stale order with
        // previous payment method and GST values.
        unset($this->session->data['order_id']);
        unset($this->session->data['confirm']);
        // ═══════════════════════════════════════════════════════════════

        // ── Response ──────────────────────────────────────────────────
        $is_active = isset($this->session->data['order_gst']);

        $json['success']             = $this->language->get('text_success');
        $json['payment_name']        = $this->session->data['payment_method']['name'];
        $json['payment_code']        = $this->session->data['payment_method']['code'];
        $json['is_active']           = $is_active;
        $json['current_session_gst'] = $this->session->data['order_gst'] ?? '';
        $json['db_gst']              = $custom_fields[33] ?? $gst_number;

        $this->jsonResponse($json);
    }

    /**
     * Save Comment
     */
    public function comment(): void {
        $this->session->data['comment'] = (string)($this->request->post['comment'] ?? '');
        $this->jsonResponse(['success' => true]);
    }

    /**
     * Agree Terms
     */
    public function agree(): void {
        if (isset($this->request->post['agree'])) {
            $this->session->data['agree'] = 1;
        } else {
            unset($this->session->data['agree']);
        }
        $this->jsonResponse(['success' => true]);
    }

    /**
     * Reset Payment State
     */
    private function resetPaymentState(): void {
        unset($this->session->data['payment_method']);
        unset($this->session->data['payment_methods']);
        unset($this->session->data['confirm']);
    }

    /**
     * Helper — set JSON response
     */
    private function jsonResponse(array $json): void {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
