<?php
namespace Opencart\Catalog\Controller\Startup;

class SeoUrl extends \Opencart\System\Engine\Controller {

	private array $data = [];

	public function index() {
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);
			$this->load->model('design/seo_url');

			if (isset($this->request->get['_route_'])) {
				$parts = explode('/', $this->request->get['_route_']);

				if (oc_strlen(end($parts)) == 0) {
					array_pop($parts);
				}

				foreach ($parts as $key => $value) {
					$seo_url_info = $this->model_design_seo_url->getSeoUrlByKeyword($value);

					if ($seo_url_info) {
						$this->request->get[$seo_url_info['key']] =
							html_entity_decode($seo_url_info['value'], ENT_QUOTES, 'UTF-8');

						// 🔥 FIX: FORCE ROUTES
						if ($seo_url_info['key'] === 'article_id') {
							$this->request->get['route'] = 'cms/blog.info';
						}

						if ($seo_url_info['key'] === 'route' && $seo_url_info['value'] === 'cms/blog') {
							$this->request->get['route'] = 'cms/blog';
						}

						unset($parts[$key]);
					}
				}

				if (!isset($this->request->get['route'])) {
					$this->request->get['route'] = $this->config->get('action_default');
				}

				if ($parts) {
					$this->request->get['route'] = $this->config->get('action_error');
				}
			}
		}

		return null;
	}

	public function rewrite(string $link): string {
		$url_info = parse_url(str_replace('&amp;', '&', $link));
		parse_str($url_info['query'] ?? '', $query);

		unset($query['language']);

		$language_id = $this->config->get('config_language_id');
		$paths = [];

		foreach ($query as $key => $value) {
			$index = $key . '=' . $value;

			if (!isset($this->data[$language_id][$index])) {
				$this->data[$language_id][$index] =
					$this->model_design_seo_url->getSeoUrlByKeyValue($key, (string)$value);
			}

			if ($this->data[$language_id][$index]) {
				$paths[] = $this->data[$language_id][$index];
				unset($query[$key]);
			}
		}

		usort($paths, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

		$url  = $url_info['scheme'] . '://' . $url_info['host'];
		$url .= isset($url_info['port']) ? ':' . $url_info['port'] : '';
		$url .= str_replace('/index.php', '', $url_info['path']);

		foreach ($paths as $result) {
			$url .= '/' . $result['keyword'];
		}

		if ($query) {
			$url .= '?' . http_build_query($query);
		}

		return $url;
	}
}
