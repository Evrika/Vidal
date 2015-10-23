<?php

namespace Vidal\MainBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Lsw\SecureControllerBundle\Annotation\Secure;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ArticleController extends Controller
{
	const ARTICLES_PER_PAGE = 14;
	const PHARM_PER_PAGE = 5;

	/**
	 * Конкретная статья рубрики энциклопедии
	 * @Route("/encyclopedia/{rubrique}/{link}", name="article")
	 * @Template("VidalMainBundle:Article:article.html.twig")
	 */
	public function articleAction(Request $request, $rubrique, $link)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$rubrique = $em->getRepository('VidalDrugBundle:ArticleRubrique')->findOneByRubrique($rubrique);
		$article  = $em->getRepository('VidalDrugBundle:Article')->findOneByLink($link);
		$testMode = $request->query->has('test');

		if (!$testMode && (!$rubrique || !$rubrique->getEnabled() || !$article || $article->getEnabled() === false)) {
			throw $this->createNotFoundException();
		}

		$title = $this->strip($article->getTitle());

		$params = array(
			'title'       => $title . ' | ' . $rubrique,
			'ogTitle'     => $title,
			'menu'        => 'articles',
			'rubrique'    => $rubrique,
			'article'     => $article,
			'description' => $this->strip($article->getAnnounce()),
			'hideMobile'  => true,
		);

		$articleId = $article->getId();
		$isDoctor  = $this->get('security.context')->isGranted('ROLE_DOCTOR');
		$products  = $em->getRepository('VidalDrugBundle:Product')->findByArticle($articleId, $isDoctor);

		if (!empty($products)) {
			# если нашлись препараты по статье - надо разбить на 2 группы: безрецептурные и рецептурные
			if ($isDoctor) {
				$productsPre = array();
				$productsNon = array();

				foreach ($products as $product) {
					$product['NonPrescriptionDrug'] ? $productsNon[] = $product : $productsPre[] = $product;
				}

				if (!empty($productsNon)) {
					$productIds          = $this->getProductIds($productsNon);
					$params['products']  = $productsNon;
					$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
					$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds, date('Y'));
					$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($productsNon);
				}

				if (!empty($productsPre)) {
					$productIds              = $this->getProductIds($productsPre);
					$params['pre_products']  = $productsPre;
					$params['pre_companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
					$params['pre_pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds, date('Y'));
					$params['pre_infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($productsPre);
				}

				$params['molecules'] = $em->getRepository('VidalDrugBundle:Molecule')->findByArticle($articleId);
			}
			else {
				$productIds          = $this->getProductIds($products);
				$params['products']  = $products;
				$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
				$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds, date('Y'));
				$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($products);
			}
		}
		if($link == "doctor_chistoteloff") {
			$params['products'] = array();
			$params['pre_products'] = array();
		}
		if($link == "doktor-chistoteloff") {
			$params['products'] = array();
			$params['pre_products'] = array();
		}
		return $params;
	}

	/**
	 * Конкретная рубрика
	 * @Route("/encyclopedia/{rubrique}", name="rubrique")
	 *
	 * @Template("VidalMainBundle:Article:rubrique.html.twig")
	 */
	public function rubriqueAction(Request $request, $rubrique)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$testMode = $request->query->has('test');
		$rubrique = $em->getRepository('VidalDrugBundle:ArticleRubrique')->findEnabledByRubrique($rubrique);

		if (!$rubrique) {
			throw $this->createNotFoundException();
		}

		$articles = $em->getRepository('VidalDrugBundle:Article')->ofRubrique($rubrique, $testMode);

		return array(
			'title'        => $rubrique . ' | Энциклопедия',
			'menu'         => 'articles',
			'rubrique'     => $rubrique,
			'articles'     => $articles,
			'hideRubrique' => true,
			'hideMobile'   => true,
		);
	}

	/**
	 * @Route("/patsientam/entsiklopediya/")
	 * @Route("/patsientam/entsiklopediya")
	 */
	public function r11()
	{
		return $this->redirect($this->generateUrl('articles'), 301);
	}

	/** @Route("/poisk_preparatov/") */
	public function r12()
	{
		return $this->redirect($this->generateUrl('searche'), 301);
	}

	/** @Route("/patsientam/entsiklopediya/{url}", requirements={"url"=".+"}) */
	public function r13($url)
	{
		$em = $this->getDoctrine()->getManager('drug');

		if ($pos = strpos($url, '.')) {
			$url = substr($url, 0, $pos);
		}

		if ($pos = strpos($url, '_')) {
			$id      = substr($url, $pos + 1);
			$article = $em->getRepository('VidalDrugBundle:Article')->findOneById($id);
		}
		else {
			$parts = explode('/', $url);
			if (count($parts) > 1) {
				$lastIndex = count($parts) - 1;
				$link      = $parts[$lastIndex];
				$article   = $em->getRepository('VidalDrugBundle:Article')->findOneByLink($link);
			}
			else {
				$article = null;
			}
		}

		if (!$article) {
			return $this->redirect($this->generateUrl('index'), 301);
		}

		return $this->redirect($this->generateUrl('article', array(
			'rubrique' => $article->getRubrique()->getRubrique(),
			'link'     => $article->getLink(),
		), 301));
	}

	/**
	 * @Route("/vracham/Informatsiya-dlya-spetsialistov")
	 * @Route("/vracham/Informatsiya-dlya-spetsialistov/")
	 */
	public function r4()
	{
		return $this->redirect($this->generateUrl('vracham'), 301);
	}

	/**
	 * Рубрики статей видаля
	 * @Route("/encyclopedia", name="articles")
	 *
	 * @Template()
	 */
	public function articlesAction()
	{
		$em = $this->getDoctrine()->getManager('drug');

		return array(
			'title'      => 'Энциклопедия',
			'menu'       => 'articles',
			'rubriques'  => $em->getRepository('VidalDrugBundle:ArticleRubrique')->findEnabled(),
			'hideMobile' => true,
		);
	}

	/**
	 * @Route("/vracham/pharma-company/{id}", name="pharma_company")
	 * @Template("VidalMainBundle:Article:pharmaCompany.html.twig")
	 */
	public function pharmaCompanyAction(Request $request, $id)
	{
		if ($response = $this->checkRole()) {
			return $response;
		}

		$em      = $this->getDoctrine()->getManager('drug');
		$company = $em->getRepository('VidalDrugBundle:PharmCompany')->findOneById($id);

		if (!$company) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'title'      => $company . ' | Новости Фармацевтических компаний',
			'company'    => $company,
			'menu_left'  => 'vracham',
			'hideMobile' => true,
		);

		$params['pagination'] = $this->get('knp_paginator')->paginate(
			$em->getRepository('VidalDrugBundle:PharmArticle')->getQueryOfCompany($id),
			$request->query->get('p', 1),
			self::PHARM_PER_PAGE
		);

		return $params;
	}

	/**
	 * @Route("/vracham/pharma-news", name="pharma_news")
	 * @Template("VidalMainBundle:Article:pharmaNews.html.twig")
	 */
	public function pharmaNewsAction(Request $request)
	{
		if ($response = $this->checkRole()) {
			return $response;
		}

		$em     = $this->getDoctrine()->getManager('drug');
		$params = array(
			'title'      => 'Новости Фармацевтических компаний',
			'menu_left'  => 'vracham',
			'hideMobile' => true,
		);

		$params['pagination'] = $this->get('knp_paginator')->paginate(
			$em->getRepository('VidalDrugBundle:PharmArticle')->getQuery(),
			$request->query->get('p', 1),
			7
		);

		return $params;
	}

	/**
	 * @Route("/vracham", name="vracham")
	 * @Template("VidalMainBundle:Article:vracham.html.twig")
	 */
	public function vrachamAction()
	{
		if ($response = $this->checkRole()) {
			return $response;
		}

		$em = $this->getDoctrine()->getManager('drug');

		return array(
			'title'      => 'Информация для специалистов',
			'menu'       => 'vracham',
			'rubriques'  => $em->getRepository('VidalDrugBundle:ArtRubrique')->findActive(),
			'arts'       => $em->getRepository('VidalDrugBundle:Art')->findForAnons(),
			'hideMobile' => true,
		);
	}

	/**
	 * @Route("/vracham/podrobno-o-preparate", name="portfolio")
	 *
	 * @Template("VidalMainBundle:Article:portfolio.html.twig")
	 */
	public function portfolioAction()
	{
		if ($response = $this->checkRole()) {
			return $response;
		}

		$em     = $this->getDoctrine()->getManager('drug');
		$params = array(
			'title'      => 'Портфели препаратов',
			'portfolios' => $em->getRepository('VidalDrugBundle:PharmPortfolio')->findActive(),
			'hideMobile' => true,
		);

		return $params;
	}

	/**
	 * @Route("/vracham/podrobno-o-preparate/{url}", name="portfolio_item")
	 * @Template("VidalMainBundle:Article:portfolioItem.html.twig")
	 */
	public function portfolioItemAction($url)
	{
		if ($response = $this->checkRole()) {
			return $response;
		}

		$em        = $this->getDoctrine()->getManager('drug');
		$portfolio = $em->getRepository('VidalDrugBundle:PharmPortfolio')->findOneByUrl($url);

		if (!$portfolio) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'title'      => $this->strip($portfolio->getTitle()) . ' | Портфель препарата',
			'portfolio'  => $portfolio,
			'products'   => $em->getRepository('VidalDrugBundle:Product')->findByPortfolio($portfolio),
			'hideMobile' => true,
		);

		return $params;
	}

	/**
	 * @Route("/vracham/expert", name="vracham_expert")
	 * @Template
	 */
	public function vrachamExpertAction()
	{
		if ($response = $this->checkRole()) {
			return $response;
		}

		return array(
			'title'      => 'Видаль-Эксперт',
			'menu'       => 'vracham',
			'hideMobile' => true,
		);
	}

	/**
	 * @Route("/vracham/expert/Vidal-CD", name="vracham_expert_cd")
	 * @Template("VidalMainBundle:Article:vrachamExpertCd.html.twig")
	 */
	public function vrachamExpertCdAction()
	{
		if ($response = $this->checkRole()) {
			return $response;
		}

		return array(
			'menu'       => 'vracham',
			'title'      => 'Электронный справочник Видаль',
			'hideMobile' => true,
		);
	}

	/**
	 * @Route("/vracham/Informatsiya-dlya-spetsialistov/{url}", requirements={"url"=".+"})
	 */
	public function r5($url)
	{
		//return $this->redirect($this->generateUrl('art', array('url' => $url)), 301);

		$parts        = explode('/', $url);
		$rubriqueName = $parts[0];
		$em           = $this->getDoctrine()->getManager('drug');
		$rubrique     = $em->getRepository('VidalDrugBundle:ArtRubrique')->findOneByUrl($rubriqueName);

		if (!$rubrique) {
			throw $this->createNotFoundException();
		}

		return $this->redirect($this->generateUrl('art', array('url' => $rubriqueName)), 301);
	}

	/** @Route("/vracham/expert/vzaimodeistvie{url}", requirements={"url"=".+"}) */
	public function interactionRedirect()
	{
		return $this->redirect($this->generateUrl('interaction'), 301);
	}

	/**
	 * @Route("/vracham/{url}", name="art", requirements={"url"=".+"})
	 * @Template("VidalMainBundle:Article:art.html.twig")
	 */
	public function artAction(Request $request, $url)
	{
		$parts    = explode('/', $url);
		$em       = $this->getDoctrine()->getManager('drug');
		$rubrique = $em->getRepository('VidalDrugBundle:ArtRubrique')->findOneByUrl($parts[0]);
		$params   = array(
			'menu'       => 'vracham',
			'rubrique'   => $rubrique,
			'hideMobile' => true,
		);

		# находим, если указана статья по старому
		$pos = strpos($url, '.');
		if ($pos !== false) {
			$index = count($parts) - 1;
			$link  = $parts[$index];
			$pos   = strpos($link, '.');
			$link  = substr($link, 0, $pos);
			$pos   = strpos($link, '_');
			if ($pos !== false) {
				$id                = substr($link, $pos + 1);
				$params['article'] = $em->getRepository('VidalDrugBundle:Art')->findOneById($id);
			}
			else {
				$params['article'] = $em->getRepository('VidalDrugBundle:Art')->findOneByLink($link);
			}
			array_pop($parts);
		}

		$pos = strpos($url, '~');
		if ($pos !== false) {
			$id                = substr($url, $pos + 1);
			$params['article'] = $em->getRepository('VidalDrugBundle:Art')->findOneById($id);
			array_pop($parts);
		}

		$count = count($parts);

		if ($count == 1) {
			$params['types']      = $em->getRepository('VidalDrugBundle:ArtType')->findByRubrique($rubrique);
			$query                = $em->getRepository('VidalDrugBundle:Art')->getQueryByRubrique($rubrique);
			$params['pagination'] = $this->get('knp_paginator')->paginate(
				$query,
				$request->query->get('p', 1),
				self::ARTICLES_PER_PAGE
			);
		}
		elseif ($count == 2) {
			$type = $em->getRepository('VidalDrugBundle:ArtType')->rubriqueUrl($rubrique, $parts[1]);
			if (!$type || !$type->getEnabled()) {
				throw $this->createNotFoundException();
			}
			$params['type']       = $type;
			$params['categories'] = $em->getRepository('VidalDrugBundle:ArtCategory')->findByType($type);
			$query                = $em->getRepository('VidalDrugBundle:Art')->getQueryByType($type);
			$params['pagination'] = $this->get('knp_paginator')->paginate(
				$query,
				$request->query->get('p', 1),
				self::ARTICLES_PER_PAGE
			);
		}
		elseif ($count == 3) {
			$type               = $em->getRepository('VidalDrugBundle:ArtType')->rubriqueUrl($rubrique, $parts[1]);
			$params['type']     = $type;
			$category           = $em->getRepository('VidalDrugBundle:ArtCategory')->typeUrl($type, $parts[2]);
			$params['category'] = $category;
			if (!$type || !$type->getEnabled() || !$category || !$category->getEnabled()) {
				throw $this->createNotFoundException();
			}
			$query                = $em->getRepository('VidalDrugBundle:Art')->getQueryByCategory($params['category']);
			$params['pagination'] = $this->get('knp_paginator')->paginate(
				$query,
				$request->query->get('p', 1),
				self::ARTICLES_PER_PAGE
			);
		}

		# формируем заголовок страницы
		$titles = array();
		if (isset($params['article'])) {
			$title                 = $this->strip($params['article']->getTitle());
			$titles[]              = $title;
			$params['ogTitle']     = $title;
			$params['description'] = $this->strip($params['article']->getAnnounce());
		}
		if (isset($params['category'])) {
			$titles[] = $params['category'];
		}
		if (isset($params['type'])) {
			$titles[] = $params['type'];
		}
		$titles[]        = $params['rubrique'];
		$params['title'] = implode(' | ', $titles);

		# og-мета для разделов
		if (!isset($params['article'])) {
			$titles            = array_reverse($titles);
			$params['ogTitle'] = implode('. ', $titles);
			$descriptions      = array();
			if (isset($query)) {
				$arts = $query->getResult();
				foreach ($arts as $art) {
					$descriptions[] = $this->strip($art->getTitle());
				}
				$params['description'] = implode('. ', $descriptions);
			}
		}

		# отображение отдельной статьи своим шаблоном
		if (isset($params['article'])) {
			if (!$params['article']->getEnabled() || !$params['article']->getRubrique()->getEnabled()) {
				throw $this->createNotFoundException();
			}
			if ($params['article']->getType() && !$params['article']->getType()->getEnabled()) {
				throw $this->createNotFoundException();
			}
			if ($params['article']->getCategory() && !$params['article']->getCategory()->getEnabled()) {
				throw $this->createNotFoundException();
			}

			if (!$this->getUser()) {
				$params['title']          = 'Закрытый раздел';
				$params['menu']           = 'vracham';
				$params['moduleId']       = 7;
				$params['loginAuthError'] = false;

				return $this->render('VidalMainBundle:Auth:login.html.twig', $params);
			}

			return $this->render('VidalMainBundle:Article:art_item.html.twig', $params);
		}

		if (!$this->getUser()) {
			$params['title']          = 'Закрытый раздел';
			$params['menu']           = 'vracham';
			$params['moduleId']       = 7;
			$params['loginAuthError'] = false;

			return $this->render('VidalMainBundle:Auth:login.html.twig', $params);
		}

		if ($response = $this->checkRole()) {
			return $response;
		}

		$params['keywords']    = "";
		

		return $params;
	}

	public function checkRole()
	{
		$security = $this->get('security.context');

		if (!$security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
			return $this->redirect($this->generateUrl('login'),301);
		}
		elseif (!$security->isGranted('ROLE_DOCTOR')) {
			return $this->redirect($this->generateUrl('confirm'),301);
		}

		return null;
	}

	private function strip($string)
	{
		$string = strip_tags(html_entity_decode($string, ENT_QUOTES, 'UTF-8'));
		$string = preg_replace('/&nbsp;|®|™/', '', $string);

		return $string;
	}

	private function getProductIds($products)
	{
		$productIds = array();

		foreach ($products as $product) {
			$productIds[] = $product['ProductID'];
		}

		return $productIds;
	}
}
