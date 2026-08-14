<?php
namespace Opencart\Catalog\Controller\Checkout;

class Referral extends \Opencart\System\Engine\Controller {
    public function index(): string {
        if (!$this->customer->isLogged()) {
            return ''; // Only for logged-in customers
        }

        $this->load->model('account/order');
        $this->load->model('account/customer');

        $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
        $order_total   = $this->model_account_order->getTotalOrders();

        // Show referral option only on first order
        if ($order_total > 0) {
            return '';
        }

        $data['name']   = $customer_info['firstname'] . ' ' . $customer_info['lastname'];
        $data['mobile'] = $customer_info['telephone'];

        return $this->load->view('checkout/referral', $data);
    }

    public function save(): void {
        $json = [];

        if (!$this->customer->isLogged()) {
            $json['error'] = 'Not logged in';
        } else {
            $referrer_name   = $this->request->post['referrer_name'] ?? '';
            $referrer_mobile = $this->request->post['referrer_mobile'] ?? '';

             // Field-specific validation
        if (empty($referrer_name)) {
            $json['error']['referrer_name'] = 'Please enter the referrer name';
        }

        if (empty($referrer_mobile)) {
            $json['error']['referrer_mobile'] = 'Please enter the referrer mobile';
        }

            if (empty($referrer_name) || empty($referrer_mobile)) {
                $json['error'] = 'Please fill all fields!';
            } else {
                // Save referral details in session until order is confirmed
                $this->session->data['referral'] = [
                    'referrer_name'   => $referrer_name,
                    'referrer_mobile' => $referrer_mobile
                ];

                $json['success'] = 'Referral info saved! It will be stored after order completion.';
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
