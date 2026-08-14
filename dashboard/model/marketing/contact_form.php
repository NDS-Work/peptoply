<?php
namespace Opencart\Admin\Model\Marketing;

class ContactForm extends \Opencart\System\Engine\Model {
    public function getContacts(int $limit = 0): array {
        $sql = "SELECT * FROM `" . DB_PREFIX . "contact_messages` ORDER BY date_added DESC";
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function deleteContact(int $contact_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "contact_messages` WHERE contact_id = '" . (int)$contact_id . "'");
    }
}
