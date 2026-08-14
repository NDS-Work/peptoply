<?php
namespace Opencart\Catalog\Controller\Common;

class HeaderCustom extends \Opencart\System\Engine\Controller {
    public function index(): string {
        // Analytics
        $data['analytics'] = [];

        if (!$this->config->get('config_cookie_id') || (isset($this->request->cookie['policy']) && $this->request->cookie['policy'])) {
            // Extension
            $this->load->model('setting/extension');

            $analytics = $this->model_setting_extension->getExtensionsByType('analytics');

            foreach ($analytics as $analytic) {
                if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
                    $data['analytics'][] = $this->load->controller('extension/' . $analytic['extension'] . '/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
                }
            }
        }

        // Language and document info
        $data['lang'] = $this->language->get('code');
        $data['direction'] = $this->language->get('direction');

        $data['title'] = $this->document->getTitle();
        $data['base'] = $this->config->get('config_url');
        $data['description'] = $this->document->getDescription();
        $data['keywords'] = $this->document->getKeywords();

        // Prepare fully qualified URLs for CSS/JS assets
        $base = $data['base'];

        $data['bootstrap'] = $base . 'catalog/view/stylesheet/bootstrap.css';
        $data['icons'] = $base . 'catalog/view/stylesheet/fonts/fontawesome/css/all.min.css';
        $data['stylesheet'] = $base . 'catalog/view/stylesheet/stylesheet.css';
        $data['custom_css'] = $base . 'catalog/view/stylesheet/custom.css';

        $data['jquery'] = $base . 'catalog/view/javascript/jquery/jquery-3.7.1.min.js';

        $data['links'] = $this->document->getLinks();
        $data['styles'] = $this->document->getStyles();
        $data['scripts'] = $this->document->getScripts('header');

        $data['name'] = $this->config->get('config_name');

        // Fav icon
        if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
            $data['icon'] = $base . 'image/' . $this->config->get('config_icon');
        } else {
            $data['icon'] = '';
        }

        // Logo
        if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
            $data['logo'] = $base . 'image/' . $this->config->get('config_logo');
        } else {
            $data['logo'] = '';
        }

        // Load language for header texts
        $this->load->language('common/header');

        // Wishlist info
        if ($this->customer->isLogged()) {
            $this->load->model('account/wishlist');
            $data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist($this->customer->getId()));
        } else {
            $data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0));
        }

        // Core URLs
        $data['home'] = '/';
        $data['wishlist'] = $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
        $data['logged'] = $this->customer->isLogged();

        if (!$this->customer->isLogged()) {
            $data['register'] = $this->url->link('account/register');
            $data['login'] = $this->url->link('account/login');
        } else {
            $data['account'] = $this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            $data['order'] = $this->url->link('account/order', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            $data['transaction'] = $this->url->link('account/transaction', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            $data['download'] = $this->url->link('account/download', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            $data['logout'] = $this->url->link('account/logout');
        }

        $data['shopping_cart'] = $this->url->link('checkout/cart');
        $data['checkout'] = $this->url->link('checkout/checkout');
        $data['contact'] = $this->url->link('information/contact');
        $data['telephone'] = $this->config->get('config_telephone');

        // Load other common controllers
        $data['language'] = $this->load->controller('common/language');
        $data['currency'] = $this->load->controller('common/currency');
        $data['search'] = $this->load->controller('common/search');
        $data['cart'] = $this->load->controller('common/cart');
        $data['menu'] = $this->load->controller('common/menu');

        // Your custom menu URLs - adjust routes and params as needed
        $data['url_link_products'] = $this->url->link('product/category', 'path=20');   // Change 'path=20' to your actual products category ID
        $data['url_link_services'] = $this->url->link('information/information', 'information_id=5'); // Change to your Services info page ID
        $data['url_link_blog'] = $this->url->link('blog/blog');  // Change if you have blog extension or a page

        return $this->load->view('common/header_custom', $data);
    }
}
