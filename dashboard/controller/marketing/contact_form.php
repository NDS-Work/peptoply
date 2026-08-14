<?php
namespace Opencart\Admin\Controller\Marketing;

class ContactForm extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('marketing/contact_form');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('marketing/contact_form');

        $data['contacts'] = $this->model_marketing_contact_form->getContacts();

        $data['delete'] = $this->url->link('marketing/contact_form.delete', 'user_token=' . $this->session->data['user_token']);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('marketing/contact_form_list', $data));
    }

    public function delete(): void {
        $this->load->model('marketing/contact_form');

        if (isset($this->request->get['contact_id'])) {
            $this->model_marketing_contact_form->deleteContact((int)$this->request->get['contact_id']);
        }

        $this->response->redirect($this->url->link('marketing/contact_form', 'user_token=' . $this->session->data['user_token']));
    }
}
