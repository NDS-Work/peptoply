<?php
namespace Opencart\Catalog\Controller\Common;

class SideMenu extends \Opencart\System\Engine\Controller {
    public function index(): string {
        $language = $this->config->get('config_language');
        $customer_token = $this->session->data['customer_token'] ?? '';

        $data['logged'] = $this->customer->isLogged();

        $data['links'] = [
            'products' => $this->url->link('product/category', 'path=63'), // Update path accordingly
            'about'    => $this->url->link('information/about'), // Replace YOUR_ABOUT_ID
            'blog'     => $this->url->link('cms/blog'),// Adjust if blog extension used
            'contact'  => $this->url->link('information/contact'),
            'login'    => $this->url->link('account/login'),
            'register' => $this->url->link('account/register'),
            'account'  => $this->url->link('account/account', 'language=' . $language . ($customer_token ? '&customer_token=' . $customer_token : '')),
            'logout'   => $this->url->link('account/logout'),
        ];

        $data['store_phone']   = $this->config->get('config_telephone');

        return $this->load->view('custom/side_menu', $data);
    }
}
