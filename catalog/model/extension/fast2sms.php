<?php
namespace Opencart\Catalog\Model\Extension;

class ModelExtensionFast2sms extends \Opencart\System\Engine\Model {

    public function sendOrderPlacedSMS(array $data): void {

        $template_id = '208121'; // Your Fast2SMS DLT template ID
        $mobile_digits = preg_replace('/\D/', '', $data['mobile']); // remove +, spaces, dashes etc.


        $payload = [
            'route'            => 'dlt',
            'sender_id'        => 'PPLYWO',
            'message'          => $template_id,
            'variables_values' => $data['order_id'],
            'numbers'          => $mobile_digits,
            'flash'            => 0,
            'schedule_time'    => ''
        ];

        $this->callFast2SMS($payload);
    }

    private function callFast2SMS(array $payload): void {

        $api_key = $this->config->get('fast2sms_api_key') ?: (defined('FAST2SMS_API_KEY') ? FAST2SMS_API_KEY : '');

        if (!$api_key) {
            $this->log->write('Fast2SMS API key not set');
            return;
        }

        $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_HTTPHEADER     => [
                'authorization: ' . $api_key,
                'cache-control: no-cache'
            ],
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $this->log->write('Fast2SMS Error: ' . $err);
        } else {
            $this->log->write('Fast2SMS Response: ' . $response);
        }
    }
}
