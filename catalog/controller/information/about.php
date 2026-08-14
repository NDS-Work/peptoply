<?php
namespace Opencart\Catalog\Controller\Information;

class About extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('information/about');

        // === SEO Meta Tags ===
        $this->document->setTitle('Premium Termite-Resistant Plywood Manufacturer | PeptoPly');

        $this->document->setDescription(
            'PeptoPly is a trusted brand offering termite-proof and waterproof plywood, focused on quality, durability, and long-lasting interiors.'
        );

        $this->document->setKeywords(
            'plywood manufacturer delhi, termite proof plywood company, waterproof plywood brand, block board manufacturer'
        );

        // Banner section
        $data['banner_image'] = 'image/catalog/peptoply/about-banner.png';

        // Title section data
        $data['title_section'] = [
            'title' => 'About PEPTO PLY',
            'description' => '
                <p>Termite is a major pain point for home owners in India. The market is flooded with sub-standard Plywoods that claim to be termite proof but in reality are not chemically treated to serve the purpose. In case of a termite attack, the total loss can be many times more than the cost of plywood. Duplicacy is also a major problem due to the presence of unscrupulous dealers. </p>
                <p>At Pepto Ply, our mission is to ensure that customers get authentic termite proof plywood. We deliver directly to eliminate any chance of duplicacy and accept Cash on Delivery to ensure a hassle-free shopping experience, Moreover, we aim to deliver all orders within 2 working days.</p>
            ',
            'image' => 'image/catalog/peptoply/about.png'
        ];

        // Testimonials
        $data['testimonials_section'] = $this->load->view('custom/testimonials', $data);

        // Footer banner
        $data['footer_banner'] = 'image/catalog/peptoply/about-footer-image.jpg';

        // Header & Footer
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        // View
        $this->response->setOutput($this->load->view('information/about', $data));
    }
}
