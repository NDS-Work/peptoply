<?php
namespace Opencart\Catalog\Controller\Common;

class Home extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $description = $this->config->get('config_description');
        $language_id = $this->config->get('config_language_id');

        if (isset($description[$language_id])) {
            $this->document->setTitle($description[$language_id]['meta_title']);
            $this->document->setDescription($description[$language_id]['meta_description']);
            $this->document->setKeywords($description[$language_id]['meta_keyword']);
        }

        // Load common controllers
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        // Load models for categories and products
        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        // Fetch categories (top-level)
        $data['categories'] = $this->model_catalog_category->getCategories(0);

        // Get filter category id from GET param
        $category_id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;
        $data['active_category_id'] = $category_id;

        // Prepare filter data
        $filter_data = [
            'filter_category_id' => $category_id,
            'start' => 0,
            'limit' => 12,
        ];

        // Fetch products based on filter
        $results = $this->model_catalog_product->getProducts($filter_data);

        // Prepare products array for Twig
        $data['products'] = [];

        foreach ($results as $product) {
            if ($product['image']) {
                $image = $this->model_tool_image->resize($product['image'], 300, 300);
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', 300, 300);
            }

            $price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);

            $data['products'][] = [
                'product_id' => $product['product_id'],
                'thumb'      => $image,
                'name'       => $product['name'],
                'price'      => $price,
                'href'       => $this->url->link('product/product', 'product_id=' . $product['product_id']),
            ];
        }

// Direct links
$data['all_products_url'] = $this->url->link('product/category', 'path=63');
$data['about_url'] = $this->url->link('information/about');


        // Load custom sections
        $data['about_us_section'] = $this->load->view('custom/about_us', $data);
        $data['hero_banner_section'] = $this->load->view('custom/hero_banner', $data);
        $data['product_range_section'] = $this->load->view('custom/products', $data);
        $data['why_choose_us_section'] = $this->load->view('custom/why_choose_us', $data);
        $data['testimonials_section'] = $this->load->view('custom/testimonials', $data);
        $data['contact_section'] = $this->load->view('custom/contact', $data);
		$data['termi_tuff_section'] = $this->load->view('custom/termi_tuff', $data);

        // Render the main home twig
        $this->response->setOutput($this->load->view('common/home', $data));
    }
}
