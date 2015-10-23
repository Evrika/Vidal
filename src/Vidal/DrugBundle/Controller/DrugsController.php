<?php

namespace Vidal\DrugBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lsw\SecureControllerBundle\Annotation\Secure;

class DrugsController extends Controller
{
	const PHARM_PER_PAGE = 150;
	const KFG_PER_PAGE = 150;
	const PRODUCTS_PER_PAGE = 50;

	private $nozologies;

	/**
	 * @Route("/drugs/atc-tree", name="atc_tree")
	 * @Template("VidalDrugBundle:Drugs:atc_tree.html.twig")
	 */
	public function atcTreeAction(Request $request)
	{
		$em      = $this->getDoctrine()->getManager('drug');
		$choices = $em->getRepository('VidalDrugBundle:ATC')->getChoices();
		$atcCode = $request->query->get('c', null);

		$params = array(
			'menu_drugs' => 'atc',
			'title'      => 'АТХ',
			'ATCCode'    => $atcCode,
			'choices'    => $choices,
		);

		return $params;
	}

	/** @Route("/poisk_preparatov/lat_{url}", requirements={"url"=".+"}) */
	public function redirectLat($url)
	{
		if ($pos = strrpos($url, '.')) {
			$url = substr($url, 0, $pos);
		}

		return $this->redirect($this->generateUrl('atc_item', array('ATCCode' => $url)), 301);
	}

	/**
	 * Препараты по коду АТХ
	 *
	 * @Route("/drugs/atc/{ATCCode}/{search}", name="atc_item", options={"expose":true})
	 * @Template("VidalDrugBundle:Drugs:atc_item.html.twig")
	 */
	public function atcItemAction($ATCCode, $search = 0)
	{
		$em  = $this->getDoctrine()->getManager('drug');
		$atc = $em->getRepository('VidalDrugBundle:ATC')->findOneByATCCode($ATCCode);

		if (!$atc) {
			throw $this->createNotFoundException();
		}

		# все продукты по ATC-коду и отсеиваем дубли
		$products = $em->getRepository('VidalDrugBundle:Product')->findByATCCode($ATCCode);
		$params   = array(
			'atc'      => $atc,
			'products' => $products,
			'title'    => $this->strip($atc->getRusName()) . ' - ' . $atc->getATCCode() . ' | АТХ',
		);

		if (!empty($products)) {
			$productIds          = $this->getProductIds($products);
			$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
			$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
			$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($products);
		}

		return $search ? $this->render('VidalDrugBundle:Drugs:search_atc_item.html.twig', $params) : $params;
	}

	/**
	 * @Route("/drugs/atc", name="atc")
	 * @Template("VidalDrugBundle:Drugs:atc.html.twig")
	 */
	public function atcAction(Request $request)
	{
		$em      = $this->getDoctrine()->getManager('drug');
		$q       = $request->query->get('q', null);
		$l       = $request->query->get('l', null);
		$atcCode = $request->query->get('ATCCode', '');

		$params = array(
			'menu_drugs' => 'atc',
			'title'      => 'АТХ',
			'keywords'	 =>	'',
			'l'          => $l,
			'q'          => $q,
			'atcCode'    => $atcCode,
		);

		# если указан АТС-код, то показываем страницу по букве
		if (!empty($atcCode)) {
			$l = $params['l'] = substr($atcCode, 0, 1);
		}

		if ($l) {
			$codesByLetter           = $em->getRepository('VidalDrugBundle:ATC')->findByLetter($l);
			$params['codeByLetter']  = array_shift($codesByLetter);
			$params['codesByLetter'] = $codesByLetter;
		}
		elseif ($q) {
			$params['atcCodes'] = mb_strlen($q, 'utf-8') < 2
				? null
				: $em->getRepository('VidalDrugBundle:ATC')->findByQuery($q);
		}
		else {
			$params['showTree'] = true;
		}

		return $params;
	}

	/**
	 * [AJAX] Подгрузка дерева ATC
	 *
	 * @Route("/drugs/atc-ajax", name="atc_ajax", options={"expose":true})
	 */
	public function atcAjaxAction(Request $request)
	{
		if ($request->request->has('root')) {
			$file = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Generated' . DIRECTORY_SEPARATOR . 'atc.json';
			$json = json_decode(file_get_contents($file), true);
			$root = $request->request->get('root');
			$data = $json[$root]['children'];

			return new JsonResponse($data);
		}

		return new JsonResponse();
	}

	/**
	 * Функция генерации дерева с кодами ATC
	 *
	 * @Route("/drugs/atc-generator", name="atc_generator")
	 * @Template("VidalDrugBundle:Drugs:atc_generator.html.twig")
	 * @Secure(roles="ROLE_ADMIN")
	 */
	public function atcGeneratorAction()
	{
		$em    = $this->getDoctrine()->getManager('drug');
		$repo  = $em->getRepository('VidalDrugBundle:ATC');
		$codes = $repo->findForTree();

		return array('codes' => $codes);
	}

	/**
	 * @Route("/poisk_preparatov/lkf_{code}.htm", requirements={"code"=".+"})
	 * @Route("/poisk_preparatov/kf_{code}.htm", requirements={"code"=".+"})
	 */
	public function kfuRedirect($code)
	{
		$em  = $this->getDoctrine()->getManager('drug');
		$kfu = $em->getRepository('VidalDrugBundle:ClinicoPhPointers')->findOneByCode($code);

		if (!$kfu) {
			return $this->redirect($this->generateUrl('index'), 301);
		}

		return $this->redirect($this->generateUrl('kfu_item', array(
			'code' => $code,
		)), 301);
	}

	/**
	 * Препараты по КФУ
	 *
	 * @Route("/drugs/clinic-pointer/{code}", name="kfu_item", options={"expose":true})
	 * @Template("VidalDrugBundle:Drugs:kfu_item.html.twig")
	 */
	public function kfuItemAction($code)
	{
		$em   = $this->getDoctrine()->getManager('drug');
		$repo = $em->getRepository('VidalDrugBundle:ClinicoPhPointers');
		$kfu  = $repo->findOneByCode($code);

		if (!$kfu) {
			throw $this->createNotFoundException();
		}

		$ClPhPointerID   = $kfu->getClPhPointerID();
		$moleculeIdsUsed = array();

		$params = array(
			'menu_drugs' => 'kfu',
			'kfu'        => $kfu,
			'title'      => $this->strip($kfu->getName()) . ' - ' . $kfu->getCode() . ' | Клинико-фармакологические указатели',
		);

		$products = $em->getRepository('VidalDrugBundle:Product')->findByKfu($ClPhPointerID);

		if (!empty($products)) {
			$productIds          = $this->getProductIds($products);
			$params['products']  = $products;
			$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
			$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
			$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($products);

			####################################################################################################
			# группируем препараты по активному веществу
			$groups         = array();
			$unusedProducts = array();
			$moleculeRepo   = $em->getRepository('VidalDrugBundle:Molecule');
			$molecules      = $moleculeRepo->findByProductIds($productIds);

			foreach ($products as $product) {
				$moleculeIds = $moleculeRepo->idsByProduct($product['ProductID']);

				if (empty($moleculeIds) || count($moleculeIds) > 3 || in_array(1144, $moleculeIds) || in_array(2203, $moleculeIds)) {
					$unusedProducts[] = $product;
					continue;
				}

				$group = implode('-', $moleculeIds);

				if (isset($groups[$group])) {
					$groups[$group]['products'][] = $product;
				}
				else {
					$groups[$group]['products']    = array($product);
					$groups[$group]['moleculeIds'] = $moleculeIds;
				}
			}

			$params['molecules']      = $molecules;
			$params['groups']         = $groups;
			$params['unusedProducts'] = $unusedProducts;

			foreach ($molecules as $molecule) {
				$moleculeIdsUsed[] = $molecule['MoleculeID'];
			}
		}

		$params['moleculesRest'] = $em->getRepository('VidalDrugBundle:Molecule')->findByKfu($ClPhPointerID, $moleculeIdsUsed);

		return $params;
	}

	/**
	 * @Route("/drugs/clinic-pointers", name="kfu")
	 * @Template("VidalDrugBundle:Drugs:kfu.html.twig")
	 */
	public function kfuAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager('drug');
		$q  = $request->query->get('q', null);
		$l  = $request->query->get('l', null);

		$params = array(
			'menu_drugs' => 'kfu',
			'title'      => 'Клинико-фармакологические указатели',
			'l'          => $l,
			'q'          => $q,
		);

		if ($l) {
			$codesByLetter           = $em->getRepository('VidalDrugBundle:ClinicoPhPointers')->findByLetter($l);
			$params['codeByLetter']  = array_shift($codesByLetter);
			$params['codesByLetter'] = $codesByLetter;
		}
		elseif ($q) {
			$params['codes'] = mb_strlen($q, 'utf-8') < 2
				? null
				: $em->getRepository('VidalDrugBundle:ClinicoPhPointers')->findByQuery($q);
		}
		else {
			$params['showTree'] = true;
		}

		if ($request->query->has('show')) {
			$em      = $this->getDoctrine()->getManager('drug');
			$show    = $request->query->get('show', null);
			$showKfu = $em->getRepository('VidalDrugBundle:ClinicoPhPointers')->findOneById($show);
			if ($showKfu) {
				$showBaseKfu = $em->getRepository('VidalDrugBundle:ClinicoPhPointers')->findBase($showKfu);
				if ($showBaseKfu) {
					$params['showKfu']     = $showKfu;
					$params['showBaseKfu'] = $showBaseKfu;
				}
			}
		}

		return $params;
	}

	/**
	 * [AJAX] Подгрузка дерева КФУ
	 *
	 * @Route("/drugs/kfu-ajax", name="kfu_ajax", options={"expose":true})
	 */
	public function kfuAjaxAction(Request $request)
	{
		if ($request->request->has('root')) {
			$file = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Generated' . DIRECTORY_SEPARATOR . 'kfu.json';
			$json = json_decode(file_get_contents($file), true);

			$root = $request->request->get('root');
			$data = $json[$root]['children'];

			return new JsonResponse($data);
		}

		return new JsonResponse();
	}

	/**
	 * Функция генерации дерева с кодами КФУ
	 *
	 * @Route("/drugs/kfu-generator", name="kfu_generator")
	 * @Template("VidalDrugBundle:Drugs:kfu_generator.html.twig")
	 * @Secure(roles="ROLE_ADMIN")
	 */
	public function kfuGeneratorAction()
	{
		$em    = $this->getDoctrine()->getManager('drug');
		$codes = $em->getRepository('VidalDrugBundle:ClinicoPhPointers')->findForTree();

		return array('codes' => $codes);
	}

	/**
	 * Список компаний
	 *
	 * @Route("/drugs/pharm-groups", name="pharm")
	 * @Template("VidalDrugBundle:Drugs:pharm.html.twig")
	 */
	public function pharmAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager('drug');
		$q  = $request->query->get('q', null);
		$l  = $request->query->get('l', null);
		$p  = $request->query->get('p', 1);

		if ($l) {
			$query = $em->getRepository('VidalDrugBundle:PhThGroups')->getQueryByLetter($l);
		}
		elseif ($q) {
			$query = $em->getRepository('VidalDrugBundle:PhThGroups')->findByQueryString($q);
		}
		else {
			$query = $em->getRepository('VidalDrugBundle:PhThGroups')->getQuery();
		}

		$params = array(
			'menu_drugs' => 'pharm',
			'title'      => 'Фармако-терапевтические группы',
			'q'          => $q,
			'l'          => $l,
			'pagination' => $this->get('knp_paginator')->paginate($query, $p, self::PHARM_PER_PAGE),
		);

		return $params;
	}

	/**
	 * Список препаратов по фармако-терапевтической группе
	 *
	 * @Route("/drugs/pharm-group/{id}/{search}", name="pharm_item", defaults={"id":"\d+"})
	 * @Template("VidalDrugBundle:Drugs:pharm_item.html.twig")
	 */
	public function pharmItemAction($id, $search = 0)
	{
		$em        = $this->getDoctrine()->getManager('drug');
		$phthgroup = $em->getRepository('VidalDrugBundle:PhThGroups')->findById($id);

		if ($phthgroup === null) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'phthgroup' => $phthgroup,
			'title'     => $phthgroup['Name'] . ' | Фармако-терапевтические группы',
		);

		$products = $em->getRepository('VidalDrugBundle:Product')->findByPhThGroup($id);

		if (!empty($products)) {
			$productIds          = $this->getProductIds($products);
			$params['products']  = $products;
			$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
			$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
			$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($products);
		}

		return $search ? $this->render('VidalDrugBundle:Drugs:search_pharm_item.html.twig', $params) : $params;
	}

	/** @Route("/poisk_preparatov/lno_{url}", requirements={"url"=".+"}) */
	public function redirectNosology($url)
	{
		if ($pos = strrpos($url, '.')) {
			$url = substr($url, 0, $pos);
		}

		return $this->redirect($this->generateUrl('nosology_item', array('Code' => $url)), 301);
	}

	/**
	 * Список препаратов и активных веществ по показанию (Nozology)
	 *
	 * @Route("/drugs/nosology/{Code}/{search}", name="nosology_item", options={"expose":true})
	 * @Template("VidalDrugBundle:Drugs:nosology_item.html.twig")
	 */
	public function nosologyItemAction(Request $request, $Code, $search = 0)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$nozology = $em->getRepository('VidalDrugBundle:Nozology')->findOneByCode($Code);

		if ($nozology === null) {
			throw $this->createNotFoundException();
		}

		# надо найти нозологический код у этой назологии и родительской
		$nozologyCodes = array($nozology->getNozologyCode());
		if ($parent = $nozology->getParent()) {
			if ($parent->getLevel()) {
				$nozologyCodes[] = $parent->getNozologyCode();
				if ($grandparent = $parent->getParent()) {
					if ($grandparent->getLevel()) {
						$nozologyCodes[] = $grandparent->getNozologyCode();
					}
				}
			}
		}

		$params = array(
			'nozology'     => $nozology,
			'title'        => $nozology->getName() . ' | ' . 'Нозологический указатель',
			'articles'     => $em->getRepository('VidalDrugBundle:Article')->findByNozology($nozologyCodes),
			'arts'         => $em->getRepository('VidalDrugBundle:Art')->findByNozology($nozologyCodes),
			'publications' => $em->getRepository('VidalDrugBundle:Publication')->findByNozology($nozologyCodes),
		);

		$params['molecules'] = $em->getRepository('VidalDrugBundle:Molecule')->findByNozologyCode($Code);
		$documents           = $em->getRepository('VidalDrugBundle:Document')->findByNozologyCode($Code);

		if (!empty($documents)) {
			$products1 = $em->getRepository('VidalDrugBundle:Product')->findByDocuments25($documents);
			$products2 = $em->getRepository('VidalDrugBundle:Product')->findByDocuments4($documents);
			$products  = array();

			# надо слить продукты, исключая повторения и отсортировать по названию
			foreach ($products1 as $id => $product) {
				$products[] = $product;
			}
			foreach ($products2 as $id => $product) {
				if (!isset($products1[$id])) {
					$products[] = $product;
				}
			}

			if (!empty($products)) {
				usort($products, function ($a, $b) {
					return strcmp($a['RusName'], $b['RusName']);
				});

				$productIds          = $this->getProductIds($products);
				$params['products']  = $products;
				$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
				$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
				$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($products);
			}
		}

		return $search ? $this->render('VidalDrugBundle:Drugs:search_nosology_item.html.twig', $params) : $params;
	}

	/**
	 * @Route("/drugs/nosology", name="nosology")
	 * @Template("VidalDrugBundle:Drugs:nosology.html.twig")
	 */
	public function nosologyAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager('drug');
		$q  = $request->query->get('q', null);
		$l  = $request->query->get('l', null);

		$params = array(
			'menu_drugs' => 'nosology',
			'title'      => 'Нозологический указатель',
			'l'          => $l,
			'q'          => $q,
		);

		if ($l) {
			$file                   = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Generated' . DIRECTORY_SEPARATOR . 'nosology.json';
			$json                   = json_decode(file_get_contents($file), true);
			$params['codeByLetter'] = $json[$l];
			$this->orderNozologyCodes($json[$l]['children']);
			$params['codesByLetter'] = $this->nozologies;
		}
		elseif ($q) {
			$params['codes'] = mb_strlen($q, 'utf-8') < 2
				? null
				: $em->getRepository('VidalDrugBundle:Nozology')->findByQuery($q);
		}
		else {
			$params['showTree'] = true;
		}

		return $params;
	}

	private function orderNozologyCodes($codes)
	{
		foreach ($codes as $code) {
			$this->nozologies[] = array(
				'code'          => $code['code'],
				'Level'         => $code['Level'],
				'text'          => $code['text'],
				'countProducts' => $code['countProducts'],
			);

			if (isset($code['children'])) {
				$this->orderNozologyCodes($code['children']);
			}
		}
	}

	/**
	 * @Route("/drugs/nosology-tree", name="nosology_tree")
	 * @Template("VidalDrugBundle:Drugs:nosology_tree.html.twig")
	 */
	public function nosologyTreeAction(Request $request)
	{
		$em           = $this->getDoctrine()->getManager('drug');
		$choices      = $em->getRepository('VidalDrugBundle:Nozology')->getChoices();
		$nosologyCode = $request->query->get('c', null);

		$params = array(
			'menu_drugs'   => 'nosology',
			'title'        => 'Нозологический указатель',
			'nosologyCode' => $nosologyCode,
			'choices'      => $choices,
		);

		return $params;
	}

	/**
	 * [AJAX] Подгрузка дерева Нозологических указателей
	 * @Route("/drugs/nosology-ajax", name="nosology_ajax", options={"expose":true})
	 */
	public function nosologyAjaxAction(Request $request)
	{
		if ($request->request->has('root')) {
			$file = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Generated' . DIRECTORY_SEPARATOR . 'nosology.json';
			$json = json_decode(file_get_contents($file), true);
			$root = $request->request->get('root');
			$data = $json[$root]['children'];

			return new JsonResponse($data);
		}

		return new JsonResponse();
	}

	/**
	 * Функция генерации дерева нозологических указателей
	 *
	 * @Route("/drugs/nosology-generator", name="nosology_generator")
	 * @Template("VidalDrugBundle:Drugs:nosology_generator.html.twig")
	 * @Secure(roles="ROLE_ADMIN")
	 */
	public function nosologyGeneratorAction()
	{
		$em         = $this->getDoctrine()->getManager('drug');
		$nozologies = $em->getRepository('VidalDrugBundle:Nozology')->findForTree();

		return array('codes' => $nozologies);
	}

	/**
	 * @Route("/drugs/clinic-groups", name="clinic_groups")
	 * @Template("VidalDrugBundle:Drugs:clinic_groups.html.twig")
	 */
	public function clinicGroupsAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager('drug');
		$q  = $request->query->get('q', null);
		$l  = $request->query->get('l', null);
		$p  = $request->query->get('p', 1);

		if ($l) {
			$query = $em->getRepository('VidalDrugBundle:ClPhGroups')->findByLetter($l);
		}
		elseif ($q) {
			$query = $em->getRepository('VidalDrugBundle:ClPhGroups')->findByQuery($q);
		}
		else {
			$query = $em->getRepository('VidalDrugBundle:ClPhGroups')->getQuery();
		}

		$params = array(
			'menu_drugs' => 'clinic_groups',
			'title'      => 'Клинико-фармакологические группы',
			'q'          => $q,
			'l'          => $l,
			'pagination' => $this->get('knp_paginator')->paginate($query, $p, self::KFG_PER_PAGE),
		);

		return $params;
	}

	/**
	 * @Route("/drugs/clinic-group/{id}/{search}", name="clinic_group")
	 * @Template("VidalDrugBundle:Drugs:clinic_group.html.twig")
	 */
	public function clinicGroupAction($id, $search = 0)
	{
		$em        = $this->getDoctrine()->getManager('drug');
		$clphGroup = $em->getRepository('VidalDrugBundle:ClPhGroups')->findOneById($id);

		if (!$clphGroup) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'title'     => $this->strip($clphGroup->getName()) . ' | Клинико-фармакологические группы',
			'clphGroup' => $clphGroup,
		);

		$products = $em->getRepository('VidalDrugBundle:Product')->findByClPhGroupID($id);

		if (!empty($products)) {
			$productIds          = $this->getProductIds($products);
			$params['products']  = $products;
			$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
			$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
			$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($products);
		}

		return $search ? $this->render('VidalDrugBundle:Drugs:search_clinic_group.html.twig', $params) : $params;
	}

	/**
	 * @Route("/drugs/companies", name="companies")
	 * @Template("VidalDrugBundle:Drugs:companies.html.twig")
	 */
	public function companiesAction(Request $request)
	{
		$em   = $this->getDoctrine()->getManager('drug');
		$q    = $request->query->get('q', null);
		$l    = $request->query->get('l', null);
		$p    = $request->query->get('p', 1);
		$type = $request->query->get('type', null);

		$params = array(
			'menu_drugs' => 'companies',
			'title'      => 'Компании',
			'q'          => $q,
			'l'          => $l,
		);

		if ($l) {
			$params['search_companies'] = $em->getRepository('VidalDrugBundle:Company')->findByLetter($l);
			$params['search_infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByLetter($l);
		}
		elseif ($q) {
			$params['search_companies'] = $em->getRepository('VidalDrugBundle:Company')->findByQuery($q);
			$params['search_infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByQuery($q);
		}
		else {
			if (!$type || $type == 'c') {
				$query                          = $em->getRepository('VidalDrugBundle:Company')->getQuery($q);
				$params['pagination_companies'] = $this->get('knp_paginator')->paginate($query, $p, 40, array('type' => 'c'));
			}

			if (!$type || $type == 'i') {
				$query                          = $em->getRepository('VidalDrugBundle:InfoPage')->getQuery($q);
				$params['pagination_infoPages'] = $this->get('knp_paginator')->paginate($query, $p, 40, array('type' => 'i'));
			}
		}

		return $params;
	}

	public function syllablesAction($t, $l)
	{
		$path      = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Generated' . DIRECTORY_SEPARATOR;
		$file      = "{$path}syllables_$t.json";
		$syllables = json_decode(file_get_contents($file), true);
		$letters   = array_keys($syllables);

		return $this->render('VidalDrugBundle:Drugs:products_syllables.html.twig', array(
			'syllables' => $syllables,
			'letters'   => $letters,
			'l'         => $l,
			't'         => $t,
		));
	}

	/** @Route("/ajax-syllables", name="ajax_syllables", options={"expose":true}) */
	public function ajaxSyllablesAction(Request $request)
	{
		$t = $request->query->get('t', 'p');
		$l = $request->query->get('l', null);

		$path      = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Generated' . DIRECTORY_SEPARATOR;
		$file      = "{$path}syllables_$t.json";
		$syllables = json_decode(file_get_contents($file), true);
		$letters   = array_keys($syllables);

		$view = $this->renderView('VidalDrugBundle:Drugs:products_syllables.html.twig', array(
			'syllables' => $syllables,
			'letters'   => $letters,
			'l'         => $l,
			't'         => $t,
		));

		return new JsonResponse($view);
	}

	public function tableAction($t, $n)
	{
		$path      = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Generated' . DIRECTORY_SEPARATOR;
		$file      = "{$path}table_$t.json";
		$table     = json_decode(file_get_contents($file), true);
		$file      = "{$path}syllables_$t.json";
		$syllables = json_decode(file_get_contents($file), true);
		$letters   = array_keys($syllables);

		return $this->render('VidalDrugBundle:Drugs:products_table.html.twig', array(
			'table'   => $table,
			'letters' => $letters,
			't'       => $t,
			'n'       => $n,
		));
	}

	/**
	 * @Route("/drugs/products", name="products", options={"expose":true})
	 * @Template("VidalDrugBundle:Drugs:products.html.twig")
	 */
	public function productsAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager('drug');
		$t  = $request->query->get('t', 'p'); // тип препараты-бады-вместе
		$p  = $request->query->get('p', 1); // номер страницы
		$l  = $request->query->get('l', null); // буква
		$n  = $request->query->has('n') && $request->query->get('n') != 'false'; // только безрецептурные препараты

		$params = array(
			't'          => $t,
			'p'          => $p,
			'l'          => $l,
			'n'          => $n,
			'menu_drugs' => 'products',
			'title'      => 'Поиск препаратов по алфавиту',
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
	 * @Route("/drugs/interaction", name="interaction")
	 * @Template("VidalDrugBundle:Drugs:interaction.html.twig")
	 */
	public function interactionAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager('drug');
		$l  = $request->query->get('l', null);
		$q  = $request->query->get('q', null);

		$params = array(
			'title' => 'Лекарственное взаимодействие',
			'l'     => $l,
			'q'     => $q,
		);

		if ($l) {
			$params['interactions'] = $em->getRepository('VidalDrugBundle:Interaction')->findByLetter($l);
		}
		elseif ($q) {
			$params['interactions'] = mb_strlen($q, 'utf-8') < 2
				? null
				: $em->getRepository('VidalDrugBundle:Interaction')->findByQuery($q);
		}
		else {
			$p                      = $request->query->get('p', 1);
			$query                  = $em->getRepository('VidalDrugBundle:Interaction')->getQuery();
			$params['interactions'] = $this->get('knp_paginator')->paginate($query, $p, self::PRODUCTS_PER_PAGE);
			$params['pagination']   = true;
		}

		return $params;
	}

	/**
	 * @Route("/drugs/interaction/{EngName}", name="interaction_item")
	 * @Template("VidalDrugBundle:Drugs:interaction_item.html.twig")
	 */
	public function interactionItemAction($EngName)
	{
		$em          = $this->getDoctrine()->getManager('drug');
		$interaction = $em->getRepository('VidalDrugBundle:Interaction')->findOneByEngName($EngName);

		if (!$interaction) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'title'       => $interaction->getRusName() . ' | Лекарственное взаимодействие',
			'interaction' => $interaction,
		);

		return $params;
	}

	/**
	 * @Route("/drugs/disease", name="disease")
	 * @Template("VidalDrugBundle:Drugs:disease.html.twig")
	 */
	public function diseaseAction(Request $request)
	{
		$l  = $request->query->get('l', null);
		$q  = $request->query->get('q', null);
		$em = $this->getDoctrine()->getManager('drug');

		$params = array(
			'title'      => 'Список болезней по алфавиту',
			'l'          => $l,
			'q'          => $q,
			'menu_drugs' => 'disease',
		);

		if ($l) {
			$articles           = $em->getRepository('VidalDrugBundle:Article')->findDisease($l);
			$params['articles'] = $this->highlight($articles, $l);
		}
		elseif ($q) {
			$q                  = trim($q);
			$params['articles'] = $em->getRepository('VidalDrugBundle:Article')->findByQuery($q);
		}

		return $params;
	}

	private function highlight($articles, $l)
	{
		foreach ($articles as &$article) {
			# подсвечиваем заголовок статьи
			$words = explode(' ', $article['title']);
			$title = '';
			foreach ($words as $word) {
				$firstLetter = mb_strtoupper(mb_substr($word, 0, 1, 'utf-8'), 'utf-8');
				$title[]     = $firstLetter == $l ? '<b>' . $word . '</b>' : $word;
			}
			$article['title'] = implode(' ', $title);

			# подсвечиваем синонимы
			$words = explode(' ', $article['synonym']);
			$title = '';
			foreach ($words as $word) {
				$firstLetter = mb_strtoupper(mb_substr($word, 0, 1, 'utf-8'), 'utf-8');
				$title[]     = $firstLetter == $l ? '<b>' . $word . '</b>' : $word;
			}
			$article['synonym'] = implode(' ', $title);
		}

		return $articles;
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

	private function strip($string)
	{
		$string = strip_tags(html_entity_decode($string, ENT_QUOTES, 'UTF-8'));
		return str_replace(explode(' ', '® ™'), '', $string);
	}
}
