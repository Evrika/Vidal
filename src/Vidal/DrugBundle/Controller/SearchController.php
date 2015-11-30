<?php

namespace Vidal\DrugBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

class SearchController extends Controller
{
	const PRODUCTS_PER_PAGE = 40;

	/**
	 * @Route("/search", name="search", options={"expose":true})
	 *
	 * @Template("VidalDrugBundle:Search:search.html.twig")
	 */
	public function searchAction(Request $request)
	{
		$em     = $this->getDoctrine()->getManager('drug');
		$q      = $request->query->get('q', ''); # поисковый запрос
		$q      = trim($q);
		$t      = $request->query->get('t', 'all'); # тип запроса из селект-бокса
		$p      = $request->query->get('p', 1); # номер страницы
		$bad    = $request->query->has('bad');
		$params = array(
			'q'     => $q,
			't'     => $t,
			'p'     => $p,
			'title' => 'Поиск',
		);

		# поисковый запрос не может быть меньше 2
		if (mb_strlen($q, 'UTF-8') < 2) {
			return $this->render('VidalDrugBundle:Search:search_too_short.html.twig', $params);
		}

		if ($t == 'all' || $t == 'product') {
			list($productsRaw, $anyOfWord) = $em->getRepository('VidalDrugBundle:Product')->findByQuery($q, $bad);

			# если включаем бады, то их надо в отдельную группу
			if ($bad && $p == 1) {
				$products = array();
				$bads     = array();
				$mis      = array();

				foreach ($productsRaw as $product) {
					switch ($product['ProductTypeCode']) {
						case 'BAD':
							$bads[] = $product;
							break;
						case 'MI':
							$mis[] = $product;
							break;
						default:
							$products[] = $product;
					}
				}

				if (count($bads)) {
					$badIds                  = $this->getProductIds($bads);
					$params['bads']          = $bads;
					$params['bad_companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($badIds);
					$params['bad_pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($badIds);
					$params['bad_infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($bads);
				}

				if (count($mis)) {
					$miIds                  = $this->getProductIds($mis);
					$params['mis']          = $mis;
					$params['mi_companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($miIds);
					$params['mi_pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($miIds);
					$params['mi_infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($mis);
				}
			}
			else {
				$products = $productsRaw;
			}

			$paginator                    = $this->get('knp_paginator');
			$pagination                   = $paginator->paginate($products, $p, self::PRODUCTS_PER_PAGE);
			$params['productsPagination'] = $pagination;
			$params['anyOfWord']          = $anyOfWord;

			if ($pagination->getTotalItemCount()) {
				$productIds          = $this->getProductIds($pagination);
				$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
				$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
				$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($pagination);
			}
		}

		if ($p == 1) {
			# поиск по активному веществу
			if ($t == 'all' || $t == 'molecule') {
				$params['molecules'] = $em->getRepository('VidalDrugBundle:Molecule')->findByQuery($q);
			}

			# поиск по АТХ коду
			if ($t == 'all' || $t == 'atc') {
				$qUpper             = mb_strtoupper($q, 'utf-8');
				$params['atcCodes'] = $em->getRepository('VidalDrugBundle:ATC')->findByQuery($qUpper);
			}

			# поиск по компании
			if ($t == 'all' || $t == 'company') {
				$params['search_companies'] = $em->getRepository('VidalDrugBundle:Company')->findByQuery($q);
				$params['search_infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByQuery($q);
			}

			# поиск по заболеванию (это статьи и синонимы)
			if ($t == 'all' || $t == 'disease') {
				$articles = $em->getRepository('VidalDrugBundle:Article')->findByQuery($q);
				# если есть БАДы, то исключаем дублирующие их статьи
				if (isset($params['bads']) && !empty($articles)) {
					$articles = $this->excludeBads($articles, $params['bads']);
				}
				$params['articles'] = $articles;
			}
		}

		return $params;
	}

	/** @Route("/searche") */
	public function redirectSearche()
	{
		return $this->redirect($this->generateUrl('searche'), 301);
	}

	/**
	 * @Route("/drugs", name="drugs")
	 * @Route("/drugs/search", name="searche", options={"expose"=true})
	 *
	 * @Template("VidalDrugBundle:Search:searche.html.twig")
	 */
	public function searcheAction(Request $request)
	{
		$em  = $this->getDoctrine()->getManager('drug');
		$q   = $request->query->get('q', ''); # поисковый запрос
		$q   = trim($q);
		$t   = $request->query->get('t', 'all'); # тип запроса из селект-бокса
		$p   = $request->query->get('p', 1); # номер страницы
		$bad = $request->query->has('bad'); # включать ли бады
		$o   = $request->query->get('o', null); # опция на поиск по группе из списка

		$params = array(
			'q'          => $q,
			't'          => $t,
			'o'          => $o,
			'p'          => $p,
			'title'      => 'Расширенный поиск',
			'menu_drugs' => 'searche',
		);

		# поисковый запрос не может быть меньше 2
		if (empty($q)) {
			return $params;
		}
		elseif (mb_strlen($q, 'UTF-8') < 2) {
			return $this->render('VidalDrugBundle:Search:searche_too_short.html.twig', $params);
		}

		if ($t == 'all' || $t == 'product') {
			list($productsRaw, $anyOfWord) = $em->getRepository('VidalDrugBundle:Product')->findByQuery($q, $bad);
			# если включаем бады, то их надо в отдельную группу
			if ($bad && $p == 1) {
				$products = array();
				$bads     = array();
				$mis      = array();

				foreach ($productsRaw as $product) {
					switch ($product['ProductTypeCode']) {
						case 'BAD':
							$bads[] = $product;
							break;
						case 'MI':
							$mis[] = $product;
							break;
						default:
							$products[] = $product;
					}
				}

				if (count($bads)) {
					$badIds                  = $this->getProductIds($bads);
					$params['bads']          = $bads;
					$params['bad_companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($badIds);
					$params['bad_pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($badIds);
					$params['bad_infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($bads);
				}

				if (count($mis)) {
					$miIds                  = $this->getProductIds($mis);
					$params['mis']          = $mis;
					$params['mi_companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($miIds);
					$params['mi_pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($miIds);
					$params['mi_infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($mis);
				}
			}
			else {
				$products = $productsRaw;
			}

			if(count($products) < 1) {
				return $this->redirect($this->generateUrl('index'), 301);
			}

			$paginator                    = $this->get('knp_paginator');
			$pagination                   = $paginator->paginate($products, $p, self::PRODUCTS_PER_PAGE);
			if($this->get('kernel')->isDebug()) {
				print_r($pagination);
				die();
			}
			$params['productsPagination'] = $pagination;
			$params['anyOfWord']          = $anyOfWord;

			if ($pagination->getTotalItemCount()) {
				$productIds          = $this->getProductIds($pagination);
				$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
				$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
				$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($pagination);
			}
		}

		# на следующих страницах отображаются только препараты
		if ($p == 1) {
			# поиск по активному веществу
			if ($t == 'all' || $t == 'molecule') {
				$params['molecules'] = $em->getRepository('VidalDrugBundle:Molecule')->findByQuery($q);
			}

			# поиск по показаниям (МКБ-10) - Nozology
			if ($t == 'all' || $t == 'nosology') {
				$params['nozologies'] = $em->getRepository('VidalDrugBundle:Nozology')->findByQuery($q);
			}

			# поиск по АТХ коду
			if ($t == 'all' || $t == 'atc') {
				$qUpper             = mb_strtoupper($q, 'utf-8');
				$params['atcCodes'] = $em->getRepository('VidalDrugBundle:ATC')->findByQuery($qUpper);
				$params['atcTree']  = true;
			}

			# поиск по компании
			if ($t == 'all' || $t == 'company') {
				$params['search_companies'] = $em->getRepository('VidalDrugBundle:Company')->findByQuery($q);
				$params['search_infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByQuery($q);
			}

			# поиск по заболеванию (это статьи и синонимы)
			if ($t == 'all' || $t == 'disease') {
				$articles = $em->getRepository('VidalDrugBundle:Article')->findByQuery($q);
				# если есть БАДы, то исключаем дублирующие их статьи
				if (isset($params['bads']) && !empty($articles)) {
					$articles = $this->excludeBads($articles, $params['bads']);
				}
				$params['articles'] = $articles;
			}

			# поиск по клиннико-фармакологической группе
			if ($t == 'all' || $t == 'clphgroup') {
				$params['clphgroups'] = $em->getRepository('VidalDrugBundle:Document')->findClPhGroupsByQuery($q);
			}

			# поиск по фармако-терапевтической группе
			if ($t == 'all' || $t == 'phthgroup') {
				$params['phthgroups'] = $em->getRepository('VidalDrugBundle:Product')->findPhThGroupsByQuery($q);
			}
		}

		return $params;
	}

	/**
	 * @Route("/searche/indic")
	 */
	public function indicRedirectAction()
	{
		return $this->redirect($this->generateUrl('drugs'), 301);
	}

	/**
	 * @Route("/search/disease")
	 */
	public function diseaseRedirectAction()
	{
		return $this->redirect($this->generateUrl('drugs'), 301);
	}

	/**
	 * @Template("VidalDrugBundle:Drug:drugs_indic.html.twig")
	 */
	public function searcheIndicAction(Request $request)
	{
		$em          = $this->getDoctrine()->getManager('drug');
		$contraCodes = $nozologyCodes = null;
		$params      = array(
			'title' => 'Поиск по показаниям/противопоказаниям',
		);

		if ($request->query->has('nozology')) {
			$nozologyCodes = explode('-', $request->query->get('nozology'));

			if (empty($nozologyCodes)) {
				$params['noNozology'] = true;

				return $params;
			}

			$params['nozologies'] = $em->getRepository('VidalDrugBundle:Nozology')->findByCodes($nozologyCodes);

			if ($request->query->has('contra')) {
				$contraCodes                 = explode('-', $request->query->get('contra'));
				$params['contraindications'] = $em->getRepository('VidalDrugBundle:Contraindication')->findByCodes($contraCodes);
			}

			$documentIds = $em->getRepository('VidalDrugBundle:Document')
				->findIdsByNozologyContraCodes($nozologyCodes, $contraCodes);

			if (!empty($documentIds)) {
				$products = $em
					->getRepository('VidalDrugBundle:Product')
					->findByDocumentIDs($documentIds);

				if (!empty($products)) {
					$pagination = $this->get('knp_paginator')->paginate(
						$products,
						$request->query->get('p', 1),
						self::PRODUCTS_PER_PAGE
					);

					$productIds = $this->getProductIds($products);

					$params['productsPagination'] = $pagination;
					$params['companies']          = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
					$params['pictures']           = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
				}
			}
		}

		return $params;
	}

	public function searcheLetterAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager('drug');
		$t  = $request->query->get('t', 'p'); // тип препараты-бады-вместе
		$p  = $request->query->get('p', 1); // номер страницы
		$l  = $request->query->get('l', null); // буква
		$n  = $request->query->has('n'); // только безрецептурные препараты

		$letters = explode(' ', 'А Б В Г Д Е Ж З И Й К Л М Н О П Р С Т У Ф Х Ц Ч Ш Э Ю Я');

		$params = array(
			't'       => $t,
			'p'       => $p,
			'l'       => $l,
			'n'       => $n,
			'menu'    => 'drugs',
			'title'   => 'Поиск по алфавиту',
			'letters' => $letters,
		);

		# БАДы только безрецептурные
		if ($t == 'b') {
			$n = false;
		}

		if ($l != null) {
			$paginator  = $this->get('knp_paginator');
			$pagination = $paginator->paginate(
				$em->getRepository('VidalDrugBundle:Product')->getQueryByLetter($l, $t, $n),
				$p,
				self::PRODUCTS_PER_PAGE
			);

			$products             = $pagination->getItems();
			$params['pagination'] = $pagination;

			if (!empty($products)) {
				$productIds = array();

				foreach ($products as $product) {
					$productIds[] = $product->getProductID();
				}

				$params['products']    = $products;
				$params['indications'] = $em->getRepository('VidalDrugBundle:Document')->findIndicationsByProductIds($productIds);
				$params['companies']   = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
				$params['pictures']    = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
			}
		}

		return $params;
	}

	/**
	 * @Route("/search/google", name="searche_google")
	 * @Template("VidalDrugBundle:Search:searche_google.html.twig")
	 */
	public function googleAction()
	{
		$params = array(
			'title'      => 'Поиск Google по открытым разделам сайта',
			'menu_drugs' => 'google',
		);

		return $params;
	}

	/**
	 * @Route("/search-options/{type}", name="search_options", options={"expose"=true})
	 */
	public function searchOptions($type)
	{
		$em = $this->getDoctrine()->getManager('drug');

		switch ($type) {
			case 'molecule':
				return new JsonResponse($em->getRepository('VidalDrugBundle:Molecule')->getOptions());
			case 'atc':
				return new JsonResponse($em->getRepository('VidalDrugBundle:ATC')->getOptions());
			case 'nosology':
				return new JsonResponse($em->getRepository('VidalDrugBundle:Nozology')->getOptions());
			case 'clphgroup':
				return new JsonResponse($em->getRepository('VidalDrugBundle:ClPhGroups')->getOptions());
			case 'phthgroup':
				return new JsonResponse($em->getRepository('VidalDrugBundle:PhThGroups')->getOptions());
			default:
				return new JsonResponse(array());
		}
	}

	/** Получить массив идентификаторов продуктов */
	private function getProductIds($products)
	{
		$productIds = array();

		foreach ($products as $product) {
			$productIds[] = $product['ProductID'];
		}

		return $productIds;
	}

	private function excludeBads($articlesRaw, $bads)
	{
		$badNames = array();
		$articles = array();

		foreach ($bads as $bad) {
			$badNames[] = $this->stripLower($bad['RusName']);
		}

		foreach ($articlesRaw as $article) {
			$title = $this->stripLower($article['title']);

			if (!in_array($title, $badNames)) {
				$articles[] = $article;
			}
		}

		return $articles;
	}

	private function stripLower($string)
	{
		$string = mb_strtolower(strip_tags(html_entity_decode($string, ENT_QUOTES, 'UTF-8')), 'utf-8');
		return str_replace(explode(' ', '® ™'), '', $string);
	}
}
