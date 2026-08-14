<?php
namespace Opencart\Catalog\Controller\Extension\Webhook;

class Sms extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->response->addHeader('Content-Type: application/json');

        /* * 🛡️ Security Note: 
         * Since Fast2SMS doesn't allow ?key=, we rely on the 
         * "Secret Keyword" in the SEO URL for security.
         */

        $input = file_get_contents('php://input');
        $payload = json_decode($input, true);

        if (!$payload || !isset($payload['sms_reports'])) {
            $this->response->setOutput(json_encode(['error' => 'Invalid payload']));
            return;
        }

        $this->load->model('marketing/sms_logs');

        foreach ($payload['sms_reports'] as $report) {
            $request_id = $report['request_id'] ?? '';
            $route = $report['route'] ?? '';

            foreach ($report['delivery_status'] ?? [] as $sms) {
                $this->model_marketing_sms_logs->addSmsLog([
                    'provider'           => 'Fast2SMS',
                    'request_id'         => $request_id,
                    'route'              => $route,
                    'sender_id'          => $sms['sender_id'] ?? '',
                    'mobile'             => $sms['mobile'] ?? '',
                    'status'             => $sms['status'] ?? '',
                    'status_description' => $sms['status_description'] ?? '',
                    'post_attempt'       => (int)($sms['post_attempt'] ?? 0),
                    'amount_debited'     => (float)($sms['amount_debited'] ?? 0),
                    'sent_time'          => date('Y-m-d H:i:s', $sms['sent_timestamp'] ?? time()),
                    'delivery_time'      => date('Y-m-d H:i:s', $sms['delivery_timestamp'] ?? time())
                ]);
            }
        }

        $this->response->setOutput(json_encode(['status' => 'success']));
    }
}