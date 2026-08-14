<?php
namespace Opencart\Catalog\Controller\Webhook;

class Sms extends \Opencart\System\Engine\Controller {

    public function fast2sms(): void {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'webhook reached']);
    }
}
