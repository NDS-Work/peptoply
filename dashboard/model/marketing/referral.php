<?php
namespace Opencart\Admin\Model\Marketing;

class Referral extends \Opencart\System\Engine\Model {

    public function getReferrals(): array {

        $sql = "
            SELECT 
                r.referral_id,
                r.customer_id,
                r.referrer_id,
                r.referrer_name,
                r.referrer_mobile,
                r.order_id,
                r.reward_given,
                r.date_added,

                c.firstname AS referrer_firstname,
                c.lastname AS referrer_lastname,

                rc.customer_id AS referred_customer_id,
                rc.date_added AS referred_account_created,

                (
                    SELECT o.order_id 
                    FROM `" . DB_PREFIX . "order` o
                    WHERE o.customer_id = rc.customer_id
                    ORDER BY o.date_added ASC
                    LIMIT 1
                ) AS referred_first_order_id

            FROM `" . DB_PREFIX . "referral` r

            LEFT JOIN `" . DB_PREFIX . "customer` c 
                ON r.customer_id = c.customer_id

            LEFT JOIN `" . DB_PREFIX . "customer` rc 
                ON CONVERT(rc.mobile USING utf8mb4) = CONVERT(r.referrer_mobile USING utf8mb4)

            ORDER BY r.date_added DESC
        ";

        $query = $this->db->query($sql);

        if (!$query) {
            return [];
        }

        return $query->rows;
    }

    public function getReferral(int $referral_id): array {

        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "referral`
            WHERE referral_id = '" . (int)$referral_id . "'
        ");

        return $query ? $query->row : [];
    }

    public function deleteReferral(int $referral_id): void {

        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "referral`
            WHERE referral_id = '" . (int)$referral_id . "'
        ");
    }
}