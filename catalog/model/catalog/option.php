<?php
namespace Opencart\Catalog\Model\Catalog;

class Option extends \Opencart\System\Engine\Model {
    public function getOptionValues(int $option_id): array {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "option_value_description` ovd LEFT JOIN `" . DB_PREFIX . "option_value` ov ON ov.option_value_id = ovd.option_value_id WHERE ov.option_id = '" . (int)$option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ov.sort_order ASC");
        return $query->rows;
    }
}
