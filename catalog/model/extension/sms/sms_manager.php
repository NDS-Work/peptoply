<?php
namespace Opencart\Catalog\Model\Extension\Sms;
class SmsManager extends \Opencart\System\Engine\Model {
    
    public function sendOTP(string $mobile, string $otp): array {
        $message = "Your verification code is: {$otp}. Valid for 10 minutes. Do not share this code.";
        
        // Determine which SMS service to use based on config
        $sms_provider = $this->config->get('mobile_verification_sms_provider') ?: 'console';
        
        switch ($sms_provider) {
            case 'smsto':
                $this->load->model('extension/sms/smsto_sms');
                $result = $this->model_extension_sms_smsto_sms->sendSMS($mobile, $message);
                break;
                
            case 'console':
            default:
                $this->load->model('extension/sms/console_sms');
                $result = $this->model_extension_sms_console_sms->sendSMS($mobile, $message);
                break;
        }
        
        return $result;
    }
}
?>
