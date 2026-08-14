<?php
namespace Opencart\Catalog\Model\Marketing;

class SmsLogs extends \Opencart\System\Engine\Model {
    public function addSmsLog(array $data): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "sms_logs` SET 
            `provider` = '" . $this->db->escape($data['provider']) . "', 
            `request_id` = '" . $this->db->escape($data['request_id']) . "', 
            `route` = '" . $this->db->escape($data['route']) . "', 
            `sender_id` = '" . $this->db->escape($data['sender_id']) . "', 
            `mobile` = '" . $this->db->escape($data['mobile']) . "', 
            `status` = '" . $this->db->escape($data['status']) . "', 
            `status_description` = '" . $this->db->escape($data['status_description']) . "', 
            `post_attempt` = '" . (int)$data['post_attempt'] . "', 
            `amount_debited` = '" . (float)$data['amount_debited'] . "', 
            `sent_time` = '" . $this->db->escape($data['sent_time']) . "', 
            `delivery_time` = '" . $this->db->escape($data['delivery_time']) . "', 
            `date_added` = NOW()");
    }
}