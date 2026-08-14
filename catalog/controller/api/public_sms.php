<?php
namespace Opencart\Catalog\Controller\Api;

class PublicSms extends \Opencart\System\Engine\Controller {
    public function dltWebhook(): void {
        header('Content-Type: application/json');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['sms_reports'])) {
            echo json_encode(['status'=>'error','message'=>'Invalid payload']);
            return;
        }

        $this->load->model('marketing/sms_logs');

        foreach ($data['sms_reports'] as $report) {
            $request_id = $report['request_id'] ?? '';
            $route = $report['route'] ?? 'dlt';

            foreach ($report['delivery_status'] as $sms) {
                $this->model_marketing_sms_logs->addSmsLog([
                    'provider' => 'Fast2SMS',
                    'request_id' => $request_id,
                    'route' => $route,
                    'sender_id' => $sms['sender_id'] ?? '',
                    'mobile' => $sms['mobile'] ?? '',
                    'status' => $sms['status'] ?? '',
                    'status_description' => $sms['status_description'] ?? '',
                    'post_attempt' => $sms['post_attempt'] ?? 0,
                    'sms_language' => $sms['sms_language'] ?? '',
                    'character_count' => $sms['character_count'] ?? 0,
                    'sms_count' => $sms['sms_count'] ?? 0,
                    'amount_debited' => $sms['amount_debited'] ?? 0,
                    'sent_time' => date('Y-m-d H:i:s', $sms['sent_timestamp'] ?? time()),
                    'delivery_time' => date('Y-m-d H:i:s', $sms['delivery_timestamp'] ?? time()),
                    'date_added' => date('Y-m-d H:i:s')
                ]);
            }
        }

        echo json_encode(['status'=>'success']);
    }
}
