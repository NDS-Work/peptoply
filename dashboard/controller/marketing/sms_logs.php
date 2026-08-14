<?php
namespace Opencart\Admin\Controller\Marketing;

class SmsLogs extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('marketing/sms_logs');
        $this->document->setTitle("Fast2SMS Logs");
        $this->load->model('marketing/sms_logs');

        // Check for success message
        $data['success'] = $this->session->data['success'] ?? '';
        unset($this->session->data['success']);

        $data['logs'] = [];
        $results = $this->model_marketing_sms_logs->getSmsLogs(50);

        foreach ($results as $result) {
            $data['logs'][] = [
                'id'                 => $result['id'],
                'provider'           => $result['provider'],
                'request_id'         => $result['request_id'],
                'sender_id'          => $result['sender_id'],
                'mobile'             => $result['mobile'],
                'status'             => $result['status'],
                'status_description' => $result['status_description'],
                'sent_time'          => $result['sent_time'],
                'delivery_time'      => $result['delivery_time'],
                'delete'             => $this->url->link('marketing/sms_logs|delete', 'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'], true)
            ];
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('marketing/sms_logs_list', $data));
    }

    public function delete(): void {
        $this->load->model('marketing/sms_logs');
        if (isset($this->request->get['id'])) {
            $this->model_marketing_sms_logs->deleteSmsLog((int)$this->request->get['id']);
            $this->session->data['success'] = "Log entry removed.";
        }
        $this->response->redirect($this->url->link('marketing/sms_logs', 'user_token=' . $this->session->data['user_token'], true));
    }
}