<?php
namespace Opencart\Admin\Controller\Marketing;

class Referral extends \Opencart\System\Engine\Controller {

    public function index(): void {

        $this->document->setTitle('Referrals');
        $this->load->model('marketing/referral');

        $data['referrals'] = $this->model_marketing_referral->getReferrals();
        $data['user_token'] = $this->session->data['user_token'];
        $data['delete'] = $this->url->link('marketing/referral.delete', 'user_token=' . $this->session->data['user_token']);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput(
            $this->load->view('marketing/referral_list', $data)
        );
    }

    public function delete(): void {

        $this->load->model('marketing/referral');

        if (isset($this->request->get['referral_id'])) {
            $this->model_marketing_referral->deleteReferral(
                (int)$this->request->get['referral_id']
            );
        }

        $this->response->redirect(
            $this->url->link('marketing/referral', 
            'user_token=' . $this->session->data['user_token'], true)
        );
    }

    public function reward(): void {

        $this->load->model('marketing/referral');

        if (isset($this->request->get['referral_id'])) {
            $referral = $this->model_marketing_referral->getReferral(
                (int)$this->request->get['referral_id']
            );

            if ($referral) {
                $this->response->redirect(
                    $this->url->link('customer/customer.form', 
                    'user_token=' . $this->session->data['user_token'] . 
                    '&customer_id=' . $referral['customer_id'], true)
                );
            }
        }

        $this->response->redirect(
            $this->url->link('marketing/referral', 
            'user_token=' . $this->session->data['user_token'], true)
        );
    }
}