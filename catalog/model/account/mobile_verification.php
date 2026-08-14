<?php
namespace Opencart\Catalog\Model\Account;

class MobileVerification extends \Opencart\System\Engine\Model {

    public function validateMobile(string $mobile): array {
        error_log("Validating mobile: " . $mobile);

        if (str_starts_with($mobile, '+91')) {
            $mobile_digits = substr($mobile, 3);
        } else {
            $mobile_digits = preg_replace('/[^0-9]/', '', $mobile);
        }

        if (strlen($mobile_digits) == 10 && preg_match('/^[6-9][0-9]{9}$/', $mobile_digits)) {
            return [
                'valid' => true,
                'formatted' => $mobile_digits,
                'international' => '+91' . $mobile_digits,
                'display' => '+91' . $mobile_digits
            ];
        }

        return [
            'valid' => false,
            'message' => 'Please enter a valid 10-digit mobile number'
        ];
    }

    public function generateOTP(string $mobile): array {
        error_log("=== OTP GENERATION START ===");

        // Validate mobile
        $validation = $this->validateMobile($mobile);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        $mobile_digits = $validation['formatted'];
        $international_mobile = $validation['international'];

        // Generate 6-digit OTP
        $otp_code = sprintf("%06d", mt_rand(1, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $current_time = date('Y-m-d H:i:s');

        // Get IP and User Agent
        $ip_address = $this->request->server['REMOTE_ADDR'] ?? '127.0.0.1';
        $user_agent = $this->request->server['HTTP_USER_AGENT'] ?? 'Unknown';

        try {
            // Insert or update customer
            $customer_query = $this->db->query("SELECT `customer_id` FROM `" . DB_PREFIX . "customer` WHERE `mobile` = '" . $this->db->escape($mobile_digits) . "'");

            if ($customer_query->num_rows) {
                $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET 
                    `otp_code` = '" . $this->db->escape($otp_code) . "',
                    `otp_expires_at` = '" . $this->db->escape($expires_at) . "',
                    `otp_attempts` = 0,
                    `last_otp_sent` = '" . $this->db->escape($current_time) . "'
                    WHERE `mobile` = '" . $this->db->escape($mobile_digits) . "'");
            } else {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "customer` SET 
                    `mobile` = '" . $this->db->escape($mobile_digits) . "',
                    `otp_code` = '" . $this->db->escape($otp_code) . "',
                    `otp_expires_at` = '" . $this->db->escape($expires_at) . "',
                    `otp_attempts` = 0,
                    `last_otp_sent` = '" . $this->db->escape($current_time) . "',
                    `mobile_verified` = 0,
                    `status` = 0,
                    `firstname` = 'temp',
                    `lastname` = 'user',
                    `email` = 'temp_" . time() . "@temp.com',
                    `telephone` = '" . $this->db->escape($mobile_digits) . "',
                    `customer_group_id` = " . (int)$this->config->get('config_customer_group_id') . ",
                    `approved` = 1,
                    `date_added` = NOW()");
            }

            // Log OTP generation
            $this->db->query("INSERT INTO `" . DB_PREFIX . "mobile_verification_log` SET 
                `mobile` = '" . $this->db->escape($mobile_digits) . "',
                `otp_code` = '" . $this->db->escape($otp_code) . "',
                `ip_address` = '" . $this->db->escape($ip_address) . "',
                `user_agent` = '" . $this->db->escape($user_agent) . "',
                `status` = 'sent'");

            // Send SMS via Fast2SMS DLT template
            $apiKey = $this->config->get('fast2sms_api_key') ?: (defined('FAST2SMS_API_KEY') ? FAST2SMS_API_KEY : '');
            if (!$apiKey) {
                return ['success' => false, 'message' => 'Fast2SMS API key not set'];
            }

            $sender_id = "PEPPLY";              // Your sender ID
            $route = "dlt";                     // DLT route
            $message_template_id = "209311";    // Your template ID
            $flash = 0;                         // Normal SMS
            $numbers = $mobile_digits;          // 10-digit number
            $schedule_time = "";                // Leave blank for immediate send
            $variables_values = $otp_code;      // OTP replaces first placeholder in template

            $final_url = "https://www.fast2sms.com/dev/bulkV2?authorization=$apiKey&route=$route&sender_id=$sender_id&message=$message_template_id&variables_values=$variables_values&flash=$flash&numbers=$numbers&schedule_time=$schedule_time";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $final_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                return [
                    'success' => true,
                    'message' => 'OTP generated (SMS failed: ' . $err . ')',
                    'mobile' => $international_mobile,
                    'debug_otp' => $otp_code,
                    'sms_sent' => false
                ];
            } else {
                $respData = json_decode($response, true);
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'mobile' => $international_mobile,
                    'debug_otp' => $otp_code,
                    'sms_sent' => true,
                    'sms_response' => $respData
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate OTP: ' . $e->getMessage()
            ];
        }
    }

    public function verifyOTP(string $mobile, string $entered_otp): array {
        try {
            $mobile_digits = preg_replace('/[^0-9]/', '', str_starts_with($mobile, '+91') ? substr($mobile, 3) : $mobile);

            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer` 
                WHERE `mobile` = '" . $this->db->escape($mobile_digits) . "' 
                AND `otp_code` = '" . $this->db->escape($entered_otp) . "'
                AND `otp_expires_at` > NOW()");

            if ($query->num_rows) {
                $customer = $query->row;

                $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET 
                    `otp_code` = NULL,
                    `otp_expires_at` = NULL,
                    `otp_attempts` = 0,
                    `mobile_verified` = 1
                    WHERE `customer_id` = '" . (int)$customer['customer_id'] . "'");

                $this->db->query("UPDATE `" . DB_PREFIX . "mobile_verification_log` SET 
                    `status` = 'verified',
                    `verified_at` = NOW()
                    WHERE `mobile` = '" . $this->db->escape($mobile_digits) . "' 
                    AND `otp_code` = '" . $this->db->escape($entered_otp) . "'
                    ORDER BY `created_at` DESC LIMIT 1");

                $is_complete_profile = (
                    !empty($customer['firstname']) && $customer['firstname'] !== 'temp' &&
                    !empty($customer['lastname']) && $customer['lastname'] !== 'user' &&
                    !empty($customer['email']) && !str_starts_with($customer['email'], 'temp_') &&
                    $customer['status'] == 1
                );

                return [
                    'success' => true,
                    'customer_id' => $customer['customer_id'],
                    'is_complete_profile' => $is_complete_profile
                ];
            } else {
                $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET 
                    `otp_attempts` = `otp_attempts` + 1
                    WHERE `mobile` = '" . $this->db->escape($mobile_digits) . "'");

                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }

    public function canSendOTP(string $mobile): array {
        try {
            $query = $this->db->query("SELECT `last_otp_sent` FROM `" . DB_PREFIX . "customer` 
                WHERE `mobile` = '" . $this->db->escape($mobile) . "'
                AND `last_otp_sent` > DATE_SUB(NOW(), INTERVAL 60 SECOND)");

            if ($query->num_rows) {
                return [
                    'can_send' => false,
                    'message' => 'Please wait 60 seconds before requesting another OTP'
                ];
            }

            return ['can_send' => true];
        } catch (\Exception $e) {
            error_log("Rate Limit Check Error: " . $e->getMessage());
            return ['can_send' => true];
        }
    }
}
?>
