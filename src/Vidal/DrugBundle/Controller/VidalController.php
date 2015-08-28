<?php
namespace Vidal\DrugBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class VidalController extends Controller
{
	const PRODUCTS_PER_PAGE = 40;
	const COMPANIES_PER_PAGE = 50;
	const MOLECULES_PER_PAGE = 50;

	/** @Route("/poisk_preparatov/") */
	public function r1()
	{
		return $this->redirect($this->generateUrl('searche'), 301);
	}

	/** @Route("/BAD/opisanie/") */
	public function r3()
	{
		return $this->redirect($this->generateUrl('searche_letter', array('t' => 'b',)), 301);
	}

	/** @Route("/BAD/opisanie/{url}", requirements={"url"=".+"}) */
	public function r4($url)
	{
		$em      = $this->getDoctrine()->getManager('drug');
		$name    = substr($url, 0, strpos($url, '.'));
		$matches = array();

		if (preg_match('/_([0-9]+).html$/', $url, $matches)) {
			$id      = $matches[1];
			$product = $em->getRepository('VidalDrugBundle:Product')->findByProductID($id);
		}
		else {
			$product = $em->getRepository('VidalDrugBundle:Product')->findBadByName($name);
		}

		if ($product) {
			return $this->redirect($this->generateUrl('product', array(
				'ProductID' => $product->getProductID(),
				'EngName'   => $product->getName(),
			)), 301);
		}

		return $this->redirect($this->generateUrl('searche_letter'), 301);
	}

	/** @Route("/patsientam/spisok-boleznei-po-alfavitu/") */
	public function r5()
	{
		return $this->redirect($this->generateUrl('searche_disease'), 301);
	}

	/** @Route("/poisk_preparatov/fir_{url}", requirements={"url"=".+"}) */
	public function redirectFirm($url)
	{
		if ($pos = strrpos($url, '.')) {
			$url = substr($url, 0, $pos);
		}

		return $this->redirect($this->generateUrl('firm_item', array('CompanyID' => $url)), 301);
	}

	/** @Route("/poisk_preparatov/lfir_{url}", requirements={"url"=".+"}) */
	public function redirectLfirm($url)
	{
		if ($pos = strrpos($url, '.')) {
			$url = substr($url, 0, $pos);
		}

		return $this->redirect($this->generateUrl('firm_item', array('CompanyID' => $url)), 301);
	}

	/**
	 * Список препаратов по компании
	 *
	 * @Route("/drugs/firm/{CompanyID}", name="firm_item", requirements={"CompanyID":"\d+"})
	 * @Template("VidalDrugBundle:Vidal:firm_item.html.twig")
	 */
	public function firmItemAction($CompanyID)
	{
		$em      = $this->getDoctrine()->getManager('drug');
		$company = $em->getRepository('VidalDrugBundle:Company')->findByCompanyID($CompanyID);

		if ($company == null) {
			throw $this->createNotFoundException();
		}

		$products = $em->getRepository('VidalDrugBundle:Product')->findByOwner($CompanyID);

		# находим представительства
		$productsRepresented = array();
		for ($i = 0; $i < count($products); $i++) {
			$key = $products[$i]['InfoPageID'];
			if (!empty($key) && !isset($productsRepresented[$key])) {
				$productsRepresented[$key] = $products[$i];
			}
		}

		$params = array(
			'title'               => $this->strip($company['CompanyName']) . ' | Фирмы-производители',
			'company'             => $company,
			'productsRepresented' => $productsRepresented,
			'products'            => $products,
		);

		if (!empty($products)) {
			$productIds          = $this->getProductIds($products);
			$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
			$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
			$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($products);
		}

		return $params;
	}

	/**
	 * Список препаратов по клиннико-фармакологической группе
	 *
	 * @Route("/drugs/cl-ph-group/{description}", name="clphgroup")
	 * @Template("VidalDrugBundle:Vidal:clphgroup.html.twig")
	 */
	public function clphgroupAction($description)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$products = $em->getRepository('VidalDrugBundle:Product')->findByClPhGroup($description);
		$params   = array(
			'products'    => $products,
			'description' => $description,
			'title'       => 'Клинико-фармакологическая группа',
		);

		if (!empty($products)) {
			$productIds          = $this->getProductIds($products);
			$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
			$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
			$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($products);
		}

		return $params;
	}

	/** @Route("/poisk_preparatov/inf_{url}", requirements={"url"=".+"}) */
	public function redirectInfopage($url)
	{
		if ($pos = strrpos($url, '.')) {
			$url = substr($url, 0, $pos);
		}

		return $this->redirect($this->generateUrl('inf_item', array('InfoPageID' => $url)), 301);
	}

	/** @Route("/poisk_preparatov/linf_{url}", requirements={"url"=".+"}) */
	public function redirectLInfopage($url)
	{
		if ($pos = strrpos($url, '.')) {
			$url = substr($url, 0, $pos);
		}

		return $this->redirect($this->generateUrl('inf_item', array('InfoPageID' => $url)), 301);
	}

	/**
	 * Страничка представительства и список препаратов
	 *
	 * @Route("/drugs/company/{InfoPageID}", name="inf_item", requirements={"InfoPageID":"\d+"})
	 * @Template("VidalDrugBundle:Vidal:inf_item.html.twig")
	 */
	public function infItemAction($InfoPageID)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$infoPage = $em->getRepository('VidalDrugBundle:InfoPage')->findOneByInfoPageID($InfoPageID);

		if (!$infoPage) {
			throw $this->createNotFoundException();
		}

		$picture     = $em->getRepository('VidalDrugBundle:Picture')->findByInfoPageID($InfoPageID);
		$documentIds = $em->getRepository('VidalDrugBundle:Document')->findIdsByInfoPageID($InfoPageID);
		$params      = array(
			'infoPage'   => $infoPage,
			'picture'    => $picture,
			'title'      => $this->strip($infoPage->getRusName()) . ' | Представительства фирм',
			'portfolios' => $em->getRepository('VidalDrugBundle:InfoPage')->findPortfolios($InfoPageID),
		);

		if (!empty($documentIds)) {
			$products = $em->getRepository('VidalDrugBundle:Product')->findByDocumentIDs($documentIds);

			if (!empty($products)) {
				$productIds          = $this->getProductIds($products);
				$params['products']  = $products;
				$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
				$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
				$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($products);
			}
		}

		return $params;
	}

	/**
	 * @Route("/drugs/molecules", name="molecules")
	 * @Template("VidalDrugBundle:Vidal:molecules.html.twig")
	 */
	public function moleculesAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager('drug');
		$q  = $request->query->get('q', null);
		$l  = $request->query->get('l', null);
		$p  = $request->query->get('p', 1);

		if ($l) {
			$query = $em->getRepository('VidalDrugBundle:Molecule')->getQueryByLetter($l);
		}
		elseif ($q) {
			$query = $em->getRepository('VidalDrugBundle:Molecule')->getQueryByString($q);
		}
		else {
			$query = $em->getRepository('VidalDrugBundle:Molecule')->getQuery();
		}

		$params = array(
			'menu_drugs' => 'molecule',
			'title'      => 'Активные вещества',
			'q'          => $q,
			'l'          => $l,
			'pagination' => $this->get('knp_paginator')->paginate($query, $p, self::MOLECULES_PER_PAGE),
		);

		return $params;
	}

	/** @Route("/poisk_preparatov/act_{url}", requirements={"url"=".+"}) */
	public function redirectMolecule($url)
	{
		if ($pos = strrpos($url, '.')) {
			$url = substr($url, 0, $pos);
		}

		return $this->redirect($this->generateUrl('molecule', array('MoleculeID' => $url)), 301);
	}

	/**
	 * Список препаратов по активному веществу: одно-монокомпонентные
	 * @Route("/drugs/molecule/{MoleculeID}/{search}", name="molecule", requirements={"MoleculeID":"\d+"})
	 * @Template("VidalDrugBundle:Vidal:molecule.html.twig")
	 */
	public function moleculeAction($MoleculeID, $search = 0)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$molecule = $em->getRepository('VidalDrugBundle:Molecule')->findByMoleculeID($MoleculeID);

		if (!$molecule) {
			throw $this->createNotFoundException();
		}

		$document = $em->getRepository('VidalDrugBundle:Document')->findByMoleculeID($MoleculeID);
		$params   = array(
			'molecule' => $molecule,
			'document' => $document,
			'title'    => mb_strtoupper($molecule->getTitle(), 'utf-8') . ' | Активные вещества',
		);

		return $search ? $this->render('VidalDrugBundle:Vidal:search_molecule.html.twig', $params) : $params;
	}

	/** @Route("/poisk_preparatov/lact_{url}", requirements={"url"=".+"}) */
	public function redirectLMolecule($url)
	{
		if ($pos = strrpos($url, '.')) {
			$url = substr($url, 0, $pos);
		}

		return $this->redirect($this->generateUrl('molecule_included', array('MoleculeID' => $url)), 301);
	}

	/**
	 * Отображение списка препаратов, в состав которых входит активное вещество (Molecule)
	 *
	 * @Route("/drugs/molecule-in/{MoleculeID}", name="molecule_included", requirements={"MoleculeID":"\d+"})
	 * @Template("VidalDrugBundle:Vidal:molecule_included.html.twig")
	 */
	public function moleculeIncludedAction($MoleculeID)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$molecule = $em->getRepository('VidalDrugBundle:Molecule')->findByMoleculeID($MoleculeID);

		if (!$molecule) {
			throw $this->createNotFoundException();
		}

		# все продукты по активному веществу и отсеиваем дубли
		$productsRaw = $em->getRepository('VidalDrugBundle:Product')->findByMoleculeID($MoleculeID);

		if (empty($productsRaw)) {
			return array('molecule' => $molecule);
		}

		$products   = array();
		$productIds = array();

		for ($i = 0; $i < count($productsRaw); $i++) {
			$key = $productsRaw[$i]['ProductID'];

			if (!isset($products[$key])) {
				$products[$key] = $productsRaw[$i];
				$productIds[]   = $key;
			}
		}

		# препараты надо разбить на монокомнонентные и многокомпонентные группы
		$components = $em->getRepository('VidalDrugBundle:Molecule')->countComponents($productIds);
		$products1  = array();
		$products2  = array();

		foreach ($products as $id => $product) {
			$components[$id] == 1
				? $products1[$id] = $product
				: $products2[$id] = $product;
		}

		uasort($products1, array($this, 'sortProducts'));
		uasort($products2, array($this, 'sortProducts'));

		return array(
			'molecule'  => $molecule,
			'products1' => $products1,
			'products2' => $products2,
			'companies' => $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds),
			'pictures'  => $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds),
			'infoPages' => $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($productsRaw),
			'title'     => mb_strtoupper($molecule->getTitle(), 'utf-8') . ' | Активные вещества в препаратах',
		);
	}

	/**
	 * Страничка рассшифровки МНН аббревиатур
	 *
	 * @Route("drugs/gnp", name="gnp")
	 * @Route("poisk_preparatov/gnp.{ext}", name="gnp_old", defaults={"ext"="htm"})
	 * @Template("VidalDrugBundle:Vidal:gnp.html.twig")
	 */
	public function gnpAction(Request $request)
	{
		if ($request->get('_route') == 'gnp_old') {
			return $this->redirect($this->generateUrl('gnp'));
		}

		$em = $this->getDoctrine()->getManager('drug');

		$params = array(
			'title' => 'Международные наименования - МНН',
			'gnps'  => $em->getRepository('VidalDrugBundle:MoleculeBase')->findAll(),
		);

		return $params;
	}

	/** @Route("/poisk_preparatov/{EngName}__{ProductID}.{ext}", requirements={"ProductID":"\d+", "EngName"=".+"}, defaults={"ext"="htm"}) */
	public function redirectProduct($EngName, $ProductID)
	{
		$em      = $this->getDoctrine()->getManager('drug');
		$product = $em->getRepository('VidalDrugBundle:Product')->findByProductID($ProductID);

		if (!$product) {
			return $this->redirect($this->generateUrl('index'), 301);
		}

		return $this->redirect($this->generateUrl('product', array(
			'ProductID' => $ProductID,
			'EngName'   => $product->getName(),
		)),301);
	}

	/**
	 * @Route("/drugs/product-group/{ids}", name="product-group")
	 * @Template("VidalDrugBundle:Vidal:product_group.html.twig")
	 */
	public function productGroupAction($ids)
	{
		$em         = $this->getDoctrine()->getManager('drug');
		$ids        = explode('-', $ids);
		$products   = array();
		$productIds = array();

		$params = array();

		foreach ($ids as $id) {
			$id           = intval($id);
			$productIds[] = $id;
			$products[]   = $em->getRepository('VidalDrugBundle:Product')->findFieldsByProductID($id);
		}

		$params['products']  = $products;
		$params['companies'] = $em->getRepository('VidalDrugBundle:Company')->findByProducts($productIds);
		$params['pictures']  = $em->getRepository('VidalDrugBundle:Picture')->findByProductIds($productIds);
		$params['infoPages'] = $em->getRepository('VidalDrugBundle:InfoPage')->findByProducts($products);

		return $params;
	}

	/**
	 * Описание препарата
	 * @Route("/drugs/{EngName}__{ProductID}", name="product", requirements={"ProductID":"\d+", "EngName"=".+"})
	 * @Template("VidalDrugBundle:Vidal:document.html.twig")
	 */
	public function productAction($EngName, $ProductID)
	{
		$em = $this->getDoctrine()->getManager('drug');

		$product = $em->getRepository('VidalDrugBundle:Product')->findByProductID($ProductID);

		if (!$product || $product->getInactive()) {
			throw $this->createNotFoundException();
		}

		if ($product->getName() != str_replace(' ', '_', $EngName)) {
			$url = $this->generateUrl('product', array('EngName' => $product->getName(), 'ProductID' => $ProductID));
			return $this->redirect($url, 301);
		}

		if (!in_array($product->getMarketStatusID()->getMarketStatusID(), array(1, 2, 7)) || $product->getInactive()) {
			return $this->render('VidalDrugBundle:Vidal:product_restricted.html.twig');
		}

		$params   = array();
		$document = $product->getDocument();

		# условите от Марии, что бады должны иметь Document.ArticleID = 6
		if ($product->getProductTypeCode() == 'BAD' && $document && $document->getArticleID() != 6) {
			$document = null;
		}

		if ($document) {
			$documentId              = $document->getDocumentID();
			$params['document']      = $document;
			$params['infoPages']     = $em->getRepository('VidalDrugBundle:InfoPage')->findByDocumentID($documentId);
			$params['nozologies']    = $em->getRepository('VidalDrugBundle:Nozology')->findByDocumentID($documentId);
			$params['parentATCCode'] = $em->getRepository('VidalDrugBundle:ATC')->getParent($product);
		}

		$productId  = $product->getProductID();
		$productIds = array($productId);
		$atcCodes   = $em->getRepository('VidalDrugBundle:Product')->findAllATC($product);

		$params['product']      = $product;
		$params['alt_product']	= preg_replace('/<sup\b[^>]*>(.*?)<\/sup>/i', '', $product->getRusName());
		$params['products']     = array($product);
		$params['owners']       = $em->getRepository('VidalDrugBundle:Company')->findOwnersByProducts($productIds);
		$params['distributors'] = $em->getRepository('VidalDrugBundle:Company')->findDistributorsByProducts($productIds);
		$params['molecules']    = $em->getRepository('VidalDrugBundle:Molecule')->findByProductID($productId);

		$params['publicationsByProduct']  = $em->getRepository('VidalDrugBundle:Product')->publicationsByProduct($productId);
		$params['publicationsByMolecule'] = $em->getRepository('VidalDrugBundle:Product')->publicationsByMolecule($productId);

		$params['articlesByProduct']  = $em->getRepository('VidalDrugBundle:Product')->articlesByProduct($productId);
		$params['articlesByMolecule'] = $em->getRepository('VidalDrugBundle:Product')->articlesByMolecule($productId);

		$params['artsByProduct']  = $em->getRepository('VidalDrugBundle:Product')->artsByProduct($productId);
		$params['artsByMolecule'] = $em->getRepository('VidalDrugBundle:Product')->artsByMolecule($productId);

		if (count($atcCodes) > 0) {
			$params['publicationsByAtc'] = $em->getRepository('VidalDrugBundle:Product')->publicationsByAtc($atcCodes);
			$params['articlesByAtc']     = $em->getRepository('VidalDrugBundle:Product')->articlesByAtc($atcCodes);
			$params['artsByAtc']         = $em->getRepository('VidalDrugBundle:Product')->artsByAtc($atcCodes);
		}

		$title                 = $this->strip($product->getRusName());
		$params['ogTitle']     = $title;
		$params['description'] = $product->getZipInfo();
		$params['zip']         = $this->strip($product->getZipInfo());

		# медицинские изделия выводятся по-другому
		if ($product->isMI()) {
			$params['pictures'] = $em->getRepository('VidalDrugBundle:Picture')->findAllByProductIds($productIds);
			$params['title']    = $title . ' - ' . $product->getZipInfo() . ' | Медицинские изделия';
			$params['isMI']     = true;

			return $this->render("VidalDrugBundle:Vidal:bad_document.html.twig", $params);
		}

		# БАДы выводятся по-другому
		if ($product->isBAD() || ($document && $document->isBAD())) {
			$params['pictures'] = $em->getRepository('VidalDrugBundle:Picture')->findAllByProductIds($productIds);
			$params['title']    = $title . ' - ' . $product->getZipInfo() . ' | БАДы';

			return $this->render("VidalDrugBundle:Vidal:bad_document.html.twig", $params);
		}
		else {
			$params['pictures'] = $em->getRepository('VidalDrugBundle:Picture')->findAllByProductIds($productIds);
			$params['title']    = $title . ' - ' . $product->getZipInfo() . ' | Препараты';
		}

		return $params;
	}

	/** @Route("/poisk_preparatov/{name}.htm", requirements={"name":"[^~]+"}) */
	public function moleculeRedirect($name)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$molecule = $em->getRepository('VidalDrugBundle:Molecule')->findByName($name);

		if (!$molecule) {
			return $this->redirect($this->generateUrl('index'), 301);
		}

		return $this->redirect($this->generateUrl('molecule', array('MoleculeID' => $molecule['MoleculeID'])),301);
	}

	/**
	 * @Route("/drugs/{EngName}~{DocumentID}", requirements={"DocumentID":"\d+"})
	 * @Route("/poisk_preparatov/{EngName}~{DocumentID}.{ext}", requirements={"DocumentID":"\d+"}, defaults={"ext"="htm"})
	 */
	public function redirectDocument($EngName, $DocumentID = null)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$document = null;

		if ($DocumentID) {
			$document = $em->getRepository('VidalDrugBundle:Document')->findOneByDocumentID($DocumentID);
		}

		if (!$document) {
			$document = $em->getRepository('VidalDrugBundle:Document')->findOneByName($EngName);
		}

		if (!$document) {
			throw $this->createNotFoundException();
		}

		$products = $document->getProducts();

		if (empty($products)) {
			throw $this->createNotFoundException();
		}

		return $this->redirect($this->generateUrl('product', array(
			'EngName'   => $products[0]->getName(),
			'ProductID' => $products[0]->getProductID(),
		)), 301);
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

	/** Отсортировать препараты по имени */
	private function sortProducts($a, $b)
	{
		return strcasecmp($a['RusName'], $b['RusName']);
	}

	private function strip($string)
	{
		$string = strip_tags(html_entity_decode($string, ENT_QUOTES, 'UTF-8'));
		return trim(str_replace(explode(' ', '® ™'), '', $string));
	}
}
