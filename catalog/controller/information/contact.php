<?php
namespace Opencart\Catalog\Controller\Information;

class Contact extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('information/contact');
        $this->document->setTitle($this->language->get('heading_title'));

        // === SEO Meta Tags ===
        $this->document->setTitle('Contact Us for Quality Termite Proof Plywood Solutions | Pepto ply');

        $this->document->setDescription(
            'Contact us for termite proof plywood, BWP & MR products. Get pricing and expert assistance for your requirements.'
        );

        $this->document->setKeywords(
            'plywood supplier, termite proof plywood delhi, plywood manufacturer contact, PeptoPly contact'
        );

        /* ---------------- Breadcrumbs ---------------- */
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('information/contact', 'language=' . $this->config->get('config_language'))
            ]
        ];

        /* ---------------- Header Banner ---------------- */
        $data['header_banner'] = [
            'title' => 'Contact Us',
            'description' => '<p>Termite is a major pain point for plywood customers in India...</p>'
        ];

        /* ---------------- Company Info ---------------- */
        $data['company_contacts'] = [
            [
                'icon'  => 'fa fa-phone',
                'title' => 'Phone',
                'value' => $this->config->get('config_telephone'),
                'href'  => 'tel:' . preg_replace('/\s+/', '', $this->config->get('config_telephone'))
            ],
            [
                'icon'  => 'fa fa-envelope',
                'title' => 'Email',
                'value' => $this->config->get('config_email'),
                'href'  => 'mailto:' . $this->config->get('config_email')
            ],
            [
                'icon'  => 'fa fa-map-marker-alt',
                'title' => 'Registered office',
                'value' => nl2br($this->config->get('config_address')),
                'href'  => ''
            ],
                        [
                'icon'  => 'fa fa-industry',
                'title' => 'Factory',
                'value' => 'Village Naharpur, Yamunanagar - 135001 (Haryana).',
                'href'  => ''
            ],
        ];

        /* ---------------- Google Map ---------------- */
        $geocode = $this->config->get('config_geocode');
        if ($geocode) {
            [$lat, $lng] = array_map('trim', explode(',', $geocode));
            $data['map_iframe'] =
                '<iframe width="100%" height="400" style="border:0" loading="lazy" allowfullscreen
                src="https://maps.google.com/maps?q=' . $lat . ',' . $lng . '&z=15&output=embed"></iframe>';
        } else {
            $data['map_iframe'] = '';
        }

        /* ---------------- Errors & Form Persistence ---------------- */
        $data['error'] = $this->session->data['error'] ?? '';
        unset($this->session->data['error']);

        $data['form'] = $this->session->data['form'] ?? [
            'name'    => '',
            'email'   => '',
            'phone'   => '',
            'message' => ''
        ];
        unset($this->session->data['form']);

        /* ---------------- FORM SUBMIT ---------------- */
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $name    = trim($this->request->post['name'] ?? '');
            $email   = trim($this->request->post['email'] ?? '');
            $phone   = trim($this->request->post['phone'] ?? '');
            $message = trim($this->request->post['message'] ?? '');
            $recaptcha = $this->request->post['g-recaptcha-response'] ?? '';

            /* ---- Validation ---- */
            if (!oc_validate_length($name, 3, 32)) {
                $this->session->data['error'] = 'Name must be between 3 and 32 characters.';
            } elseif (!oc_validate_email($email)) {
                $this->session->data['error'] = 'Please enter a valid email address.';
            } elseif (!oc_validate_length($message, 10, 3000)) {
                $this->session->data['error'] = 'Message must be at least 10 characters.';
            } elseif (!$recaptcha) {
                $this->session->data['error'] = 'Captcha verification required.';
            } else {
                // Verify Google reCAPTCHA v2
                $verify_data = http_build_query([
                    'secret'   => RECAPTCHA_SECRET_KEY,
                    'response' => $recaptcha,
                    'remoteip' => $this->request->server['REMOTE_ADDR']
                ]);

                $context = stream_context_create([
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                        'content' => $verify_data,
                        'timeout' => 10
                    ]
                ]);

                $verify = json_decode(
                    file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context),
                    true
                );

                if (empty($verify['success'])) {
                    $this->session->data['error'] = 'Captcha validation failed.';
                }
            }

            if (!empty($this->session->data['error'])) {
                $this->session->data['form'] = compact('name', 'email', 'phone', 'message');
                $this->response->redirect($this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
            }

            /* ---- Save to DB ---- */
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "contact_messages`
                SET name = '" . $this->db->escape($name) . "',
                    email = '" . $this->db->escape($email) . "',
                    phone = '" . $this->db->escape($phone) . "',
                    message = '" . $this->db->escape($message) . "',
                    date_added = NOW()
            ");

            /* ---- Send Email ---- */
if ($this->config->get('config_mail_engine')) {
    try {
        // Recipients: store + custom
        $recipients = [
            $this->config->get('config_email'), // default store email
            'bansal.raghav87@gmail.com','jai@ndimensionstudio.com'                // your custom recipient
        ];

        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);

            // Validate email
            if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                error_log("Skipping invalid email: $recipient");
                continue;
            }

            $mail = new \Opencart\System\Library\Mail(
                $this->config->get('config_mail_engine'),
                [
                    'parameter'       => $this->config->get('config_mail_parameter'),
                    'smtp_hostname'   => $this->config->get('config_mail_smtp_hostname'),
                    'smtp_username'   => $this->config->get('config_mail_smtp_username'),
                    'smtp_password'   => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
                    'smtp_port'       => $this->config->get('config_mail_smtp_port'),
                    'smtp_timeout'    => $this->config->get('config_mail_smtp_timeout'),
                    'smtp_encryption' => $this->config->get('config_mail_smtp_encryption'),
                ]
            );

            $mail->setTo($recipient);
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender($this->config->get('config_name'));
            $mail->setReplyTo($email); // form submit email
            $mail->setSubject('New Contact Form Submission');
            $mail->setText("Name: $name\nEmail: $email\nPhone: $phone\n\nMessage:\n$message");
            $mail->send();

            error_log("DEBUG: Email sent to $recipient");
        }
    } catch (\Exception $e) {
        $this->log->write('Contact Mail Error: ' . $e->getMessage());
    }
}

            $this->response->redirect(
                $this->url->link('information/contact.success', 'language=' . $this->config->get('config_language'))
            );
        }

        /* ---------------- View Data ---------------- */
        $data['recaptcha_site_key'] = RECAPTCHA_SITE_KEY;
        $data['action'] = $this->url->link('information/contact', 'language=' . $this->config->get('config_language'));

        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('information/contact', $data));
    }

    public function success(): void {
        $this->load->language('information/contact');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('information/contact', 'language=' . $this->config->get('config_language'))
            ]
        ];

        $data['text_message'] = 'Thank you for contacting us. We will get back to you soon.';
        $data['continue'] = $this->url->link('common/home');

        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('common/success', $data));
    }
}
