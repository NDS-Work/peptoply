<?php
namespace Opencart\Catalog\Controller\Account;
class MobileVerify extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Store redirect if present
        if (!empty($this->request->get['redirect'])) {
            $this->session->data['redirect'] = $this->request->get['redirect'];
        } elseif (!empty($this->request->post['redirect'])) {
            $this->session->data['redirect'] = $this->request->post['redirect'];
        }

        // Force SMS configuration
        if (defined('SMSTO_API_KEY')) {
            $this->config->set('smsto_api_key', SMSTO_API_KEY);
        }
        if (defined('SMSTO_SENDER_ID')) {
            $this->config->set('smsto_sender_id', SMSTO_SENDER_ID);
        }
        if (defined('SMS_PROVIDER')) {
            $this->config->set('mobile_verification_sms_provider', SMS_PROVIDER);
        }

        // Redirect if already logged in
        if ($this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . ($this->session->data['customer_token'] ?? ''), true));
        }

        $this->load->language('account/mobile_verify');
        $this->document->setTitle($this->language->get('heading_title'));

        // Breadcrumbs
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/mobile_verify', 'language=' . $this->config->get('config_language'))
            ]
        ];

        $data['base'] = $this->config->get('config_url');
        $data['logo'] = is_file(DIR_IMAGE . $this->config->get('config_logo')) ? $data['base'] . 'image/' . $this->config->get('config_logo') : '';

        // Session messages
        $data['error_warning'] = $this->session->data['error'] ?? '';
        unset($this->session->data['error']);
        $data['success'] = $this->session->data['success'] ?? '';
        unset($this->session->data['success']);

        // AJAX endpoints
        $data['send_otp'] = $this->url->link('account/mobile_verify.sendOTP', 'language=' . $this->config->get('config_language'));
        $data['verify_otp'] = $this->url->link('account/mobile_verify.verifyOTP', 'language=' . $this->config->get('config_language'));
        $data['complete_registration'] = $this->url->link('account/mobile_verify.completeRegistration', 'language=' . $this->config->get('config_language'));

        // Customer Groups
        $data['customer_groups'] = [];
        if (is_array($this->config->get('config_customer_group_display'))) {
            $this->load->model('account/customer_group');
            $customer_groups = $this->model_account_customer_group->getCustomerGroups();
            foreach ($customer_groups as $group) {
                if (in_array($group['customer_group_id'], (array)$this->config->get('config_customer_group_display'))) {
                    $data['customer_groups'][] = $group;
                }
            }
        }
        $data['customer_group_id'] = (int)$this->config->get('config_customer_group_id');

        // Custom Fields
        $data['custom_fields'] = [];
        $this->load->model('account/custom_field');
        foreach ($this->model_account_custom_field->getCustomFields() as $cf) {
            if ($cf['location'] === 'account') $data['custom_fields'][] = $cf;
        }

        // Layout
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('account/mobile_verify', $data));
    }

    public function sendOTP(): void {
        $json = [];
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->load->model('account/mobile_verification');
            $mobile = trim($this->request->post['mobile'] ?? '');
            if (empty($mobile)) {
                $json['error'] = 'Mobile number is required';
            } else {
                $validation = $this->model_account_mobile_verification->validateMobile($mobile);
                if (!$validation['valid']) {
                    $json['error'] = $validation['message'];
                } else {
                    $rate_check = $this->model_account_mobile_verification->canSendOTP($validation['formatted']);
                    if (!$rate_check['can_send']) {
                        $json['error'] = $rate_check['message'];
                    } else {
                        $otp_result = $this->model_account_mobile_verification->generateOTP($mobile);
                        if ($otp_result['success']) {
                            $json['success'] = 'Verification code sent to ' . $mobile;
                            $json['mobile'] = $mobile;
                            $this->session->data['verify_mobile'] = $mobile;
                        } else {
                            $json['error'] = $otp_result['message'] ?? 'Failed to send OTP';
                        }
                    }
                }
            }
        } else {
            $json['error'] = 'Invalid request method';
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function verifyOTP(): void {
        $json = [];
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->load->model('account/mobile_verification');
            $this->load->model('account/customer');

            $mobile = trim($this->request->post['mobile'] ?? '');
            $otp = trim($this->request->post['otp'] ?? '');
            $verification_result = $this->model_account_mobile_verification->verifyOTP($mobile, $otp);

            if ($verification_result['success']) {
                if ($verification_result['is_complete_profile']) {
                    $customer_info = $this->model_account_customer->getCustomer($verification_result['customer_id']);
                    if ($customer_info && $customer_info['status'] && $this->loginCustomer($customer_info)) {
                        $json['success'] = 'Login successful!';
                        $json['action'] = 'login';
                        $json['redirect'] = $this->getRedirectAfterAuth('product/category&path=63');
                    } else {
                        $json['error'] = 'Account is disabled or login failed';
                    }
                } else {
                    $json['success'] = 'Mobile verified! Please complete your registration.';
                    $json['action'] = 'register';
                    $json['customer_id'] = $verification_result['customer_id'];
                    $this->session->data['verified_customer_id'] = $verification_result['customer_id'];
                }
            } else {
                $json['error'] = $verification_result['message'] ?? 'Invalid or expired OTP';
            }
        } else {
            $json['error'] = 'Invalid request method';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function completeRegistration(): void {
        $this->load->language('account/mobile_verify');
        $json = [];
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->load->model('account/customer');
            $customer_id = $this->session->data['verified_customer_id'] ?? null;

            if (!$customer_id) {
                $json['error'] = $this->language->get('error_session_expired');
            } else {
                $customer_group_id = (int)($this->request->post['customer_group_id'] ?? $this->config->get('config_customer_group_id'));
                $firstname = trim($this->request->post['firstname'] ?? '');
                $lastname = trim($this->request->post['lastname'] ?? '');
                $email = trim($this->request->post['email'] ?? '');
                $telephone = trim($this->request->post['telephone'] ?? '');
                $custom_field = $this->request->post['custom_field'] ?? [];

                // Basic validation
                if (!oc_validate_length($firstname, 1, 32)) {
                    $json['error'] = $this->language->get('error_firstname');
                } elseif (!oc_validate_length($lastname, 1, 32)) {
                    $json['error'] = $this->language->get('error_lastname');
                } elseif (!oc_validate_email($email)) {
                    $json['error'] = $this->language->get('error_email');
                } elseif ($this->model_account_customer->getTotalCustomersByEmail($email)) {
                    $json['error'] = $this->language->get('error_email_exists');
                } else {
                    // Custom field validation
                    $this->load->model('account/custom_field');
                    foreach ($this->model_account_custom_field->getCustomFields($customer_group_id) as $cf_info) {
                        if ((int)$cf_info['custom_field_id'] === 33) continue;
                        if ($cf_info['location'] === 'account' && $cf_info['required'] && empty($custom_field[$cf_info['custom_field_id']])) {
                            $json['error'] = sprintf($this->language->get('error_custom_field_required'), $cf_info['name']);
                            break;
                        }
                    }

                    if (!$json) {
                        // Update customer
                        $mobile_query = $this->db->query("SELECT `mobile` FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . (int)$customer_id . "'");
                        if ($mobile_query->num_rows) {
                            $mobile = $mobile_query->row['mobile'];
                            $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET 
                                `customer_group_id` = '" . (int)$customer_group_id . "',
                                `firstname` = '" . $this->db->escape($firstname) . "',
                                `lastname` = '" . $this->db->escape($lastname) . "',
                                `email` = '" . $this->db->escape($email) . "',
                                `telephone` = '" . $this->db->escape($telephone ?: $mobile) . "',
                                `custom_field` = '" . $this->db->escape(json_encode($custom_field)) . "',
                                `password` = '',
                                `status` = 1,
                                `approved` = 1
                                WHERE `customer_id` = '" . (int)$customer_id . "'");

                            $customer_info = $this->model_account_customer->getCustomer($customer_id);
                            if ($customer_info && $this->loginCustomer($customer_info)) {
                                unset($this->session->data['verified_customer_id'], $this->session->data['verify_mobile']);
                                $json['success'] = $this->language->get('text_registration_success');
                                $json['redirect'] = $this->getRedirectAfterAuth('product/category&path=63');
                            } else {
                                $json['error'] = $this->language->get('error_registration_login_failed');
                            }
                        } else {
                            $json['error'] = $this->language->get('error_customer_not_found');
                        }
                    }
                }
            }
        } else {
            $json['error'] = $this->language->get('error_invalid_request');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function getRedirectAfterAuth($default_route = 'account/account') {
        $allowed = ['checkout/checkout', 'checkout/cart'];
        $customer_token = isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : '';
        $redirect = $this->session->data['redirect'] ?? '';

        if ($redirect) {
            $redirect = str_replace('index.php?route=', '', $redirect);
            if (in_array($redirect, $allowed)) {
                unset($this->session->data['redirect']);
                return $this->url->link($redirect, 'language=' . $this->config->get('config_language') . $customer_token, true);
            }
        }

        unset($this->session->data['redirect']);
        $parts = explode('&', $default_route, 2);
        $route = $parts[0];
        $query = $parts[1] ?? '';

        return $this->url->link($route, 'language=' . $this->config->get('config_language') . ($query ? '&' . $query : '') . $customer_token, true);
    }

    private function loginCustomer($customer_info): bool {
        try {
            if ($this->customer->login($customer_info['email'], '', true)) {
                // Extend session lifetime to 2 hours
                ini_set('session.gc_maxlifetime', 7200);
                setcookie(session_name(), session_id(), time() + 7200, '/');

                $this->session->data['last_active'] = time();

                $custom_field = is_string($customer_info['custom_field']) ? json_decode($customer_info['custom_field'], true) ?: [] : ($customer_info['custom_field'] ?? []);
                $this->session->data['customer'] = [
                    'customer_id'       => $customer_info['customer_id'],
                    'customer_group_id' => $customer_info['customer_group_id'],
                    'firstname'         => $customer_info['firstname'],
                    'lastname'          => $customer_info['lastname'],
                    'email'             => $customer_info['email'],
                    'telephone'         => $customer_info['telephone'],
                    'custom_field'      => $custom_field
                ];
                unset(
                    $this->session->data['order_id'],
                    $this->session->data['shipping_method'],
                    $this->session->data['shipping_methods'],
                    $this->session->data['payment_method'],
                    $this->session->data['payment_methods']
                );
                $this->load->model('account/customer');
                $this->model_account_customer->addLogin($this->customer->getId(), oc_get_ip());
                $this->session->data['customer_token'] = oc_token(26);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
}
?>