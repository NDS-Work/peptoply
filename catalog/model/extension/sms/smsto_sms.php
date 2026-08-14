<?php
namespace Opencart\Catalog\Model\Extension\Sms;
class SmstoSms extends \Opencart\System\Engine\Model {
    
    public function sendSMS(string $mobile, string $message): array {
        $api_key = $this->config->get('smsto_api_key') ?: 
                   (defined('SMSTO_API_KEY') ? SMSTO_API_KEY : '');
        
        $sender_id = $this->config->get('smsto_sender_id') ?: 
                     (defined('SMSTO_SENDER_ID') ? SMSTO_SENDER_ID : 'OpenCart');
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => 'SMS.to API key not configured'
            ];
        }
        
        // Format mobile number for India
        $to_number = $this->formatMobileNumber($mobile);
        
        $url = 'https://api.sms.to/sms/send';
        
        $data = [
            'message' => $message,
            'to' => $to_number,
            'sender_id' => $sender_id
        ];
        
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return [
                'success' => false,
                'message' => 'Network error: ' . $curl_error
            ];
        }
        
        if ($http_code == 200 || $http_code == 201) {
            $result = json_decode($response, true);
            
            if (isset($result['success']) && $result['success']) {
                return [
                    'success' => true,
                    'message_id' => $result['message_id'] ?? 'unknown',
                    'cost' => $result['cost'] ?? 0,
                    'balance' => $result['balance'] ?? 'unknown'
                ];
            } else {
                $error_message = $result['message'] ?? $result['error'] ?? 'Unknown error';
                return [
                    'success' => false,
                    'message' => 'SMS.to API Error: ' . $error_message
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => "SMS sending failed with HTTP code: {$http_code}"
            ];
        }
    }
    
    private function formatMobileNumber(string $mobile): string {
        // Remove any non-numeric characters
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        
        // For Indian numbers - add +91 if it's 10 digits
        if (strlen($mobile) == 10 && preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
            return '+91' . $mobile;
        }
        
        // If already has country code, ensure it starts with +
        if (strlen($mobile) == 12 && str_starts_with($mobile, '91')) {
            return '+' . $mobile;
        }
        
        // If already starts with +91, return as is
        if (str_starts_with($mobile, '+91')) {
            return $mobile;
        }
        
        // Default fallback - assume it's Indian and add +91
        return '+91' . $mobile;
    }
}
?>
