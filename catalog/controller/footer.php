<?php
namespace Opencart\Catalog\Controller\Common;

/**
 * Class Footer
 * @package Opencart\Catalog\Controller\Common
 */
class Footer extends \Opencart\System\Engine\Controller {
    public function index(): string {
        $this->load->language('common/footer');

        // Article/blog (keep as you have)
        $this->load->model('cms/article');
        $article_total = $this->model_cms_article->getTotalArticles();
        $data['blog'] = $article_total
            ? $this->url->link('cms/blog', 'language=' . $this->config->get('config_language'))
            : '';

        // Information pages - for quick links (you already do this)
        $data['informations'] = [];
        $this->load->model('catalog/information');
        $results = $this->model_catalog_information->getInformations();
        foreach ($results as $result) {
            $data['informations'][] = [
                'title' => $result['title'],
                'href'  => $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=' . $result['information_id'])
            ];
        }

        // Dynamically load products for footer "Products" list
        $this->load->model('catalog/product');
        $products = $this->model_catalog_product->getProducts(['start' => 0, 'limit' => 12]); // Change limit as needed
        $data['footer_products'] = [];
        foreach ($products as $product) {
            $data['footer_products'][] = [
                'name' => $product['name'],
                'href' => $this->url->link('product/product', 'product_id=' . $product['product_id'])
            ];
        }

        // ========== Other default footer data ============
        $data['contact']      = $this->url->link('information/contact', 'language=' . $this->config->get('config_language'));
        $data['return']       = $this->url->link('account/returns.add', 'language=' . $this->config->get('config_language'));
        $data['gdpr']         = $this->config->get('config_gdpr_id')
            ? $this->url->link('information/gdpr', 'language=' . $this->config->get('config_language'))
            : '';
        $data['sitemap']      = $this->url->link('information/sitemap', 'language=' . $this->config->get('config_language'));
        $data['manufacturer'] = $this->url->link('product/manufacturer', 'language=' . $this->config->get('config_language'));
        $data['affiliate']    = $this->config->get('config_affiliate_status')
            ? $this->url->link('account/affiliate', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''))
            : '';
        $data['special']      = $this->url->link('product/special', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
        $data['account']      = $this->url->link('account/account', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
        $data['order']        = $this->url->link('account/order', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
        $data['wishlist']     = $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
        $data['newsletter']   = $this->url->link('account/newsletter', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
        $data['powered']      = sprintf($this->language->get('text_powered'), $this->config->get('config_name'), date('Y'));

        // Who's online as before...
        if ($this->config->get('config_customer_online')) {
            $this->load->model('tool/online');
            $url = (isset($this->request->server['HTTP_HOST']) && isset($this->request->server['REQUEST_URI']))
                ? (($this->request->server['HTTPS'] ?? false) ? 'https://' : 'http://') . $this->request->server['HTTP_HOST'] . $this->request->server['REQUEST_URI']
                : '';
            $referer = $this->request->server['HTTP_REFERER'] ?? '';
            $this->model_tool_online->addOnline(oc_get_ip(), $this->customer->getId(), $url, $referer);
        }

        // Scripts and cookie
        $data['base']      = $this->config->get('config_url');
        $data['bootstrap'] = 'catalog/view/javascript/bootstrap/js/bootstrap.bundle.min.js';
        $data['scripts']   = $this->document->getScripts('footer');
        $data['cookie']    = $this->load->controller('common/cookie');

        return $this->load->view('common/footer', $data);
    }
}
