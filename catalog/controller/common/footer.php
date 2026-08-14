<?php
namespace Opencart\Catalog\Controller\Common;

class Footer extends \Opencart\System\Engine\Controller {
    public function index(): string {
        $this->load->language('common/footer');

        $this->load->model('cms/article');
        $this->load->model('catalog/information');
        $this->load->model('catalog/product');

        $lang = $this->config->get('config_language');

        // Blog link
        $article_total = $this->model_cms_article->getTotalArticles();
        $data['blog_url'] = $article_total
            ? $this->url->link('cms/blog')
            : '';

        // Direct About URL (custom controller)
        $data['about_url'] = $this->url->link('information/about');

        // Get all info pages
        $information_pages = $this->model_catalog_information->getInformations();

        // Shipping & Cancellation from info pages
        $shipping_url = '';
        $cancellation_url = '';

        foreach ($information_pages as $info) {
            $title = strtolower($info['title']);
            if (strpos($title, 'shipping') !== false || strpos($title, 'delivery') !== false) {
                $shipping_url = $this->url->link('information/information',  '&information_id=' . $info['information_id']);
            }
            if (strpos($title, 'cancellation') !== false || strpos($title, 'refund') !== false) {
                $cancellation_url = $this->url->link('information/information',  '&information_id=' . $info['information_id']);
            }
        }

        // Build Quick Links
        $data['informations'] = [];
        if ($data['blog_url']) {
            $data['informations'][] = ['title' => 'Blog', 'href' => $data['blog_url']];
        }
        if ($data['about_url']) {
            $data['informations'][] = ['title' => 'About Us', 'href' => $data['about_url']];
        }
        if ($shipping_url) {
            $data['informations'][] = ['title' => 'Shipping & Delivery', 'href' => $shipping_url];
        }
        if ($cancellation_url) {
            $data['informations'][] = ['title' => 'Cancellation & Refund', 'href' => $cancellation_url];
        }

        // Footer products
        $products = $this->model_catalog_product->getProducts(['start' => 0, 'limit' => 12]);
        $data['footer_products'] = [];
        foreach ($products as $product) {
            $data['footer_products'][] = [
                'name' => $product['name'],
                'href' => $this->url->link('product/product', 'product_id=' . (int)$product['product_id'])
            ];
        }

        // Store details
        $data['store_phone']   = $this->config->get('config_telephone');
        $data['store_email']   = $this->config->get('config_email');
        $data['store_address'] = nl2br($this->config->get('config_address'));
        $data['config_name']   = $this->config->get('config_name');
        $data['home']          = '/';

        // Privacy Policy & Terms
        $data['privacy_policy'] = '';
        $data['terms'] = '';
        foreach ($information_pages as $info) {
            $title = strtolower($info['title']);
            if (strpos($title, 'privacy') !== false) {
                $data['privacy_policy'] = $this->url->link('information/information',  '&information_id=' . $info['information_id']);
            }
            if (strpos($title, 'terms') !== false) {
                $data['terms'] = $this->url->link('information/information',  '&information_id=' . $info['information_id']);
            }
        }

        // Defaults
        $data['base']      = $this->config->get('config_url');
        $data['bootstrap'] = 'catalog/view/javascript/bootstrap/js/bootstrap.bundle.min.js';
        $data['scripts']   = $this->document->getScripts('footer');
        $data['cookie']    = $this->load->controller('common/cookie');
        $data['powered']   = sprintf($this->language->get('text_powered'), $this->config->get('config_name'), date('Y'));

        return $this->load->view('common/footer', $data);
    }
}
