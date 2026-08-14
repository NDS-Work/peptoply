<?php
namespace Opencart\Catalog\Controller\Common;

class Menu extends \Opencart\System\Engine\Controller {
    public function index(): string {
        $this->load->language('common/menu');
        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('catalog/information');  // load information model

        $data['categories'] = [];
        $data['informations'] = [];  // initialize informations array

        // Fetch categories and children as usual
        $categories = $this->model_catalog_category->getCategories(0);
        foreach ($categories as $category) {
            $children_data = [];
            $children = $this->model_catalog_category->getCategories($category['category_id']);
            foreach ($children as $child) {
                $filter_data = [
                    'filter_category_id'  => $child['category_id'],
                    'filter_sub_category' => true
                ];
                $children_data[] = [
                    'name' => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
                    'href' => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $category['category_id'] . '_' . $child['category_id'])
                ];
            }
            $data['categories'][] = [
                'children' => $children_data,
                'href'     => $this->url->link('product/category', 'language=' . $this->config->get('config_language') . '&path=' . $category['category_id']),
                'name'     => $category['name']
            ];
        }

        // Fetch information pages
        $results = $this->model_catalog_information->getInformations();
        foreach ($results as $result) {
            $data['informations'][] = [
                'title' => $result['title'],
                'href'  => $this->url->link('information/information', 'information_id=' . $result['information_id'] . '&language=' . $this->config->get('config_language'))
            ];
        }

        return $this->load->view('common/menu', $data);
    }
}


