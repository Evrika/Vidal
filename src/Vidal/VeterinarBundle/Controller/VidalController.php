<?php
namespace Vidal\VeterinarBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Vidal\VeterinarBundle\Entity\Product;

class VidalController extends Controller
{
	const PRODUCTS_PER_PAGE = 40;

	/**
	 * @Route("/veterinar", name="veterinar")
	 * @Template("VidalVeterinarBundle:Vidal:veterinar.html.twig")
	 */
	public function veterinarAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager('veterinar');
		$t  = $request->query->get('t', 'p'); // тип препараты=p / производители=c / представительства=r
		$p  = $request->query->get('p', 1); // номер страницы
		$l  = $request->query->get('l', null); // буква
		$q  = $request->query->get('q', null); // текстовый запрос

		$params = array(
			't'              => $t,
			'p'              => $p,
			'l'              => $l,
			'q'              => $q,
			'title'          => 'Видаль-Ветеринар',
			'menu_veterinar' => 'veterinar',
		);

		switch ($t) {
			case 'p':
				# препараты по букве алфавита или по поисковому запросу
				if ($l) {
					$products           = $em->getRepository('VidalVeterinarBundle:Product')->findByLetter($l);
					if (in_array($l, array("А","Б","В","Г","Д","Е","З","И","Й","К"))) {
						foreach ($products as $key => $value) {
							if(in_array($value['ProductID'],array(27840,27869,27868,27934,27935,27996,28175,27710,28247,27987,27705,27964,27962,28004,28080,28086,28086,27949,27947,28078,27784,27785,27720))) {
								$name = $value['Name'];
								$products[$key]['new'] = false;
								$products[$key]['urlFor'] = $value['Name'];
							} else {
								$name = $value['Name'];
								$products[$key]['urlFor'] = preg_replace('/[^A-Za-z0-9 -]/u', '', strip_tags(strtolower(str_replace("&reg;","",str_replace(" ","-",$name)))));
								$products[$key]['new'] = true;
							}
						}
					} else {
						foreach ($products as $key => $value) {
							$name = $value['Name'];
							$products[$key]['new'] = false;
							$products[$key]['urlFor'] = $value['Name'];
						}
					}

					$pagination         = $this->get('knp_paginator')->paginate($products, $p, self::PRODUCTS_PER_PAGE);
					$params['products'] = $pagination;

					if ($pagination->getTotalItemCount()) {
						$productIds          = $this->getProductIds($pagination);
						$params['companies'] = $em->getRepository('VidalVeterinarBundle:Company')->findByProducts($productIds);
						$params['pictures']  = $em->getRepository('VidalVeterinarBundle:Picture')->findByProductIds($productIds);
					}
				}
				elseif (!empty($q)) {
					$products           = $em->getRepository('VidalVeterinarBundle:Product')->findByQuery($q);
					
					if (in_array($l, array("А","Б","В","Г","Д","Е","З","И","Й","К"))) {
						foreach ($products as $key => $value) {
							if(in_array($value['ProductID'],array(27840,27869,27868,27934,27935,27996,28175,27710,28247,27987,27705,27964,27962,28004,28080,28086,28086,27949,27947,28078,27784,27785,27720))) {
								$name = $value['Name'];
								$products[$key]['new'] = false;
								$products[$key]['urlFor'] = $value['Name'];
							} else {
								$name = $value['Name'];
								$products[$key]['urlFor'] = preg_replace('/[^A-Za-z0-9 -]/u', '', strip_tags(strtolower(str_replace("&reg;","",str_replace(" ","-",$name)))));
								$products[$key]['new'] = true;
							}
						}
					} else {
						foreach ($products as $key => $value) {
							$name = $value['Name'];
							$products[$key]['new'] = false;
							$products[$key]['urlFor'] = $value['Name'];
						}
					}

					$pagination         = $this->get('knp_paginator')->paginate($products, $p, self::PRODUCTS_PER_PAGE);
					$params['products'] = $pagination;

					if ($pagination->getTotalItemCount()) {
						$productIds          = $this->getProductIds($pagination);
						$params['companies'] = $em->getRepository('VidalVeterinarBundle:Company')->findByProducts($productIds);
						$params['pictures']  = $em->getRepository('VidalVeterinarBundle:Picture')->findByProductIds($productIds);
					}
				}
				break;
			case 'c':
				# производители
				if ($l) {
					$params['companies'] = $em->getRepository('VidalVeterinarBundle:Company')->findByLetter($l);
				}
				elseif (!empty($q)) {
					$params['companies'] = $em->getRepository('VidalVeterinarBundle:Company')->findByQuery($q);
				}
				break;
			case 'r':
				# представительства
				if ($l) {
					$params['infoPages'] = $em->getRepository('VidalVeterinarBundle:InfoPage')->findByLetter($l);
				}
				elseif (!empty($q)) {
					$params['infoPages'] = $em->getRepository('VidalVeterinarBundle:InfoPage')->findByQuery($q);
				}
				break;
		}
		

		return $params;
	}

	/**
	 * Клинико-фармакологический указатель ветеринарной базы
	 *
	 * @Route("/veterinar/kfu", name = "v_kfu")
	 * @Template("VidalVeterinarBundle:Vidal:kfu.html.twig")
	 */
	public function kfuAction()
	{
		$params = array(
			'title'          => 'Клинико-фармакологические указатели | Видаль-Ветеринар',
			'menu_veterinar' => 'kfu',
		);

		return $params;
	}

	/**
	 * Клинико-фармакологический указатель ветеринарной базы
	 *
	 * @Route("/veterinar/kfu/{url}.{ext}", name="v_kfu_item", defaults={"ext"="htm"}, options={"expose":true})
	 * @Template("VidalVeterinarBundle:Vidal:kfu_item.html.twig")
	 */
	public function kfuItemAction($url)
	{
		$em  = $this->getDoctrine()->getManager('veterinar');
		$kfu = $em->getRepository('VidalVeterinarBundle:ClinicoPhPointers')->findOneByUrl($url);

		if (!$kfu) {
			throw $this->createNotFoundException();
		}

		$documentIds = $this->getDocumentIds($kfu->getDocuments());
		$params      = array(
			'title'          => $kfu->getName() . ' | Клинико-фармакологические указатели | Видаль-Ветеринар',
			'menu_veterinar' => 'kfu',
			'kfu'            => $kfu,
		);

		if (!empty($documentIds)) {
			$products            = $em->getRepository('VidalVeterinarBundle:Product')->findByDocumentIds($documentIds);
			$productIds          = $this->getProductIds($products);
			$params['products']  = $products;
			$params['companies'] = $em->getRepository('VidalVeterinarBundle:Company')->findByProducts($productIds);
			$params['pictures']  = $em->getRepository('VidalVeterinarBundle:Picture')->findByProductIds($productIds);
		}

		return $params;
	}

	/**
	 * Список препаратов по компании
	 *
	 * @Route("/veterinar/proizvoditeli", name="proizvoditeli")
	 * @Template("VidalVeterinarBundle:Vidal:proizvoditeli.html.twig")
	 */
	public function proizvoditeliAction()
	{
		$em        = $this->getDoctrine()->getManager('veterinar');
		$companies = $em->getRepository('VidalVeterinarBundle:Company')->findAllOrdered();

		return array(
			'title'          => 'Фирмы-производители | Видаль-Ветеринар',
			'menu_veterinar' => 'company',
			'companies'      => $companies,
		);
	}

	/**
	 * Список препаратов по компании
	 * @Route("/veterinar/proizvoditeli/{Name}.{ext}", name="v_company", defaults={"ext"="htm"})
	 *
	 * @Template("VidalVeterinarBundle:Vidal:company.html.twig")
	 */
	public function companyAction($Name)
	{
		$em      = $this->getDoctrine()->getManager('veterinar');
		$company = $em->getRepository('VidalVeterinarBundle:Company')->findOneByName($Name);

		if (!$company) {
			throw $this->createNotFoundException();
		}

		$CompanyID = $company['CompanyID'];
		$products  = $em->getRepository('VidalVeterinarBundle:Product')->findByCompany($CompanyID);
		$params    = array(
			'title'          => $this->strip($company['CompanyName']) . ' | Фирмы-производители | Видаль-Ветеринар',
			'menu_veterinar' => 'company',
			'company'        => $company,
			'products'       => $products,
		);

		if (!empty($products)) {
			$productIds          = $this->getProductIds($products);
			$params['companies'] = $em->getRepository('VidalVeterinarBundle:Company')->findByProducts($productIds);
			$params['pictures']  = $em->getRepository('VidalVeterinarBundle:Picture')->findByProductIds($productIds);
		}

		return $params;
	}

	/**
	 * Страничка представительства и список препаратов
	 *
	 * @Route("/veterinar/predstavitelstvo", name="predstavitelstvo")
	 * @Template("VidalVeterinarBundle:Vidal:predstavitelstvo.html.twig")
	 */
	public function predstavitelstvaAction()
	{
		$em        = $this->getDoctrine()->getManager('veterinar');
		$infoPages = $em->getRepository('VidalVeterinarBundle:InfoPage')->findAllOrdered();

		return array(
			'title'          => 'Представительства фирм | Видаль-Ветеринар',
			'menu_veterinar' => 'infoPage',
			'infoPages'      => $infoPages,
		);
	}

	/**
	 * Страничка представительства и список препаратов
	 *
	 * @Route("/veterinar/predstavitelstvo/{Name}.{ext}", name="v_inf", defaults={"ext"="htm"})
	 * @Template("VidalVeterinarBundle:Vidal:inf.html.twig")
	 */
	public function infAction($Name)
	{
		$em       = $this->getDoctrine()->getManager('veterinar');
		$infoPage = $em->getRepository('VidalVeterinarBundle:InfoPage')->findOneByName($Name);

		if (!$infoPage) {
			throw $this->createNotFoundException();
		}

		$InfoPageID = $infoPage['InfoPageID'];

		$picture     = $em->getRepository('VidalVeterinarBundle:Picture')->findByInfoPageID($InfoPageID);
		$params      = array(
			'title'          => $this->strip($infoPage['RusName']) . ' | Представительства фирм | Видаль-Ветеринар',
			'menu_veterinar' => 'infoPage',
			'infoPage'       => $infoPage,
			'picture'        => $picture,
			'portfolios' => $em->getRepository('VidalVeterinarBundle:InfoPage')->findPortfolios($InfoPageID)
		);
		$documentIds = $em->getRepository('VidalVeterinarBundle:Document')->findIdsByInfoPageID($InfoPageID);

		if (!empty($documentIds)) {
			$products = $em->getRepository('VidalVeterinarBundle:Product')->findByDocumentIDs($documentIds);

			if (!empty($products)) {
				$productIds          = $this->getProductIds($products);
				$params['products']  = $products;
				$params['companies'] = $em->getRepository('VidalVeterinarBundle:Company')->findByProducts($productIds);
				$params['pictures']  = $em->getRepository('VidalVeterinarBundle:Picture')->findByProductIds($productIds);
			}
		}

		return $params;
	}

	/**
	 * Описание по документу и отображение информации по препаратам или веществу
	 * @Route("/veterinar/opisanie/{name}.{ext}", name="v_document", requirements={"DocumentID":"\d+"}, defaults={"ext"="htm"})
	 *
	 * @Template("VidalVeterinarBundle:Vidal:document.html.twig")
	 */
	public function documentAction($name)
	{
		$em         = $this->getDoctrine()->getManager('veterinar');
		$params     = array();
		$DocumentID = intval(substr($name, strrpos($name, '_') + 1));
		$product    = $em->getRepository('VidalVeterinarBundle:Product')->findOneByDocumentID($DocumentID);

		if (!$product) {
			throw $this->createNotFoundException();
		}

		return $this->redirect($this->generateUrl('v_product', array(
			'ProductID' => $product['ProductID'],
			'EngName'   => $product['Name'],
		)));
	}

	/**
	 * Список препаратов по активному веществу: одно-монокомпонентные
	 * @Route("/veterinar/molecule/{MoleculeID}", name="v_molecule", requirements={"MoleculeID":"\d+"})
	 * @Template("VidalVeterinarBundle:Vidal:molecule.html.twig")
	 */
	public function moleculeAction($MoleculeID)
	{
		$em       = $this->getDoctrine()->getManager('veterinar');
		$molecule = $em->getRepository('VidalVeterinarBundle:Molecule')->findByMoleculeID($MoleculeID);

		if (!$molecule) {
			throw $this->createNotFoundException();
		}

		$document = $em->getRepository('VidalVeterinarBundle:Document')->findByMoleculeID($MoleculeID);
		$params   = array(
			'molecule' => $molecule,
			'document' => $document,
			'title'    => mb_strtoupper($this->strip($molecule->getTitle()), 'utf-8') . ' | Активные вещества',
		);

		return $params;
	}

	/**
	 * Отображение списка препаратов, в состав которых входит активное вещество (Molecule)
	 *
	 * @Route("/veterinar/molecule-in/{MoleculeID}", name="v_molecule_included", requirements={"MoleculeID":"\d+"})
	 * @Template("VidalVeterinarBundle:Vidal:molecule_included.html.twig")
	 */
	public function moleculeIncludedAction($MoleculeID)
	{
		$em       = $this->getDoctrine()->getManager('veterinar');
		$molecule = $em->getRepository('VidalVeterinarBundle:Molecule')->findByMoleculeID($MoleculeID);

		if (!$molecule) {
			throw $this->createNotFoundException();
		}

		$params   = array(
			'molecule' => $molecule,
			'title'    => mb_strtoupper($this->strip($molecule->getTitle()), 'utf-8') . ' | Активные вещества в препаратах',
		);
		$products = $em->getRepository('VidalVeterinarBundle:Product')->findByMoleculeID($MoleculeID);

		if (!empty($products)) {
			$productIds          = $this->getProductIds($products);
			$params['products']  = $products;
			$params['companies'] = $em->getRepository('VidalVeterinarBundle:Company')->findByProducts($productIds);
			$params['pictures']  = $em->getRepository('VidalVeterinarBundle:Picture')->findByProductIds($productIds);
		}

		return $params;
	}

	/**
	 * Страничка рассшифровки МНН аббревиатур
	 *
	 * @Route("/veterinar/gnp", name="v_gnp")
	 * @Template("VidalVeterinarBundle:Vidal:gnp.html.twig")
	 */
	public function gnpAction()
	{
		$em = $this->getDoctrine()->getManager('veterinar');

		$params = array(
			'title' => 'Международные наименования - МНН',
			'gnps'  => $em->getRepository('VidalVeterinarBundle:MoleculeBase')->findAll(),
		);

		return $params;
	}

	/**
	 * Описание препарата
	 * @Route("/veterinar/{EngName}-{ProductID}.{ext}", name="v_product", requirements={"ProductID":"\d+", "EngName"=".*"}, defaults={"ext"="htm"})
	 * @Route("/veterinar/{EngName}~{ProductID}.{ext}", name="v_product_old", requirements={"ProductID":"\d+", "EngName"=".+"}, defaults={"ext"="htm"})
	 *
	 * @Template("VidalVeterinarBundle:Vidal:document.html.twig")
	 */
	public function productAction($EngName, $ProductID)
	{

		$em     = $this->getDoctrine()->getManager('veterinar');
		$params = array();

		/** @var Product $product */
		$product = $em->getRepository('VidalVeterinarBundle:Product')->findByProductID($ProductID);
		$firstChar = mb_substr($product['RusName'],0,1);

//		if (in_array($firstChar, array("А","Б","В","Г","Д","Е","З","И","Й","К"))) {
//				if(!in_array($product['ProductID'],array(27840,27869,27868,27934,27935,27996,28175,27710,28247,27987,27705,27964,27962,28004,28080,28086,28086,27949,27947,28078,27784,27785,27720))) {
//					$name = $product['Name'];
//
//					$request = $this->container->get('request');
//					$routeName = $request->get('_route');
//					if($routeName == "v_product_old") {
//						$name = preg_replace('/[^A-Za-z0-9 -]/u', '', strip_tags(strtolower(str_replace("&reg;","",str_replace(" ","-",$name)))));
//						return $this->redirect($this->generateUrl('v_product', array('EngName' => $name,'ProductID' => $product['ProductID'])), 301);
//					}
//				}
//		}

		if (empty($product) || $product['inactive'] === true) {
			throw $this->createNotFoundException();
		}

		$params['title'] = $this->strip($product['RusName']) . ' - ' . $product['ZipInfo'] . ' | Видаль-Ветеринар';
		$document        = $em->getRepository('VidalVeterinarBundle:Document')->findByProductID($ProductID);

		if ($document) {
			$articleId           = $document->getArticleID();
			$params['document']  = $document;
			$params['articleId'] = $articleId;
			$params['infoPages'] = $em->getRepository('VidalVeterinarBundle:InfoPage')->findByDocumentID($document->getDocumentID());
		}
		else {
			# если связи ProductDocument не найдено, то это описание конкретного вещества (Molecule)
			$molecule = $em->getRepository('VidalVeterinarBundle:Molecule')->findOneByProductID($ProductID);

			if ($molecule) {
				$document = $em->getRepository('VidalVeterinarBundle:Document')->findByMoleculeID($molecule['MoleculeID']);

				if (!$document) {
					throw $this->createNotFoundException();
				}

				$params['document']  = $document;
				$params['molecule']  = $molecule;
				$params['articleId'] = $document->getArticleId();
				$params['infoPages'] = $em->getRepository('VidalVeterinarBundle:InfoPage')->findByDocumentID($document->getDocumentID());
			}
		}

		$productIds             = array($product['ProductID']);
		$params['product']      = $product;
		$params['products']     = array($product);
		$params['owners']       = $em->getRepository('VidalVeterinarBundle:Company')->findOwnersByProducts($productIds);
		$params['distributors'] = $em->getRepository('VidalVeterinarBundle:Company')->findDistributorsByProducts($productIds);
		$params['pictures']     = $em->getRepository('VidalVeterinarBundle:Picture')->findAllByProductIds($productIds);
		$params['molecules']    = $em->getRepository('VidalVeterinarBundle:Molecule')->findByProductID($ProductID);

		return $params;
	}

	/**
	 * Функция генерации дерева с кодами КФУ
	 * @Route("/veterinar/kfu-generator", name="v_kfu_generator")
	 * @Template("VidalVeterinarBundle:Vidal:kfu_generator.html.twig")
	 */
	public function kfuGeneratorAction()
	{
		$em    = $this->getDoctrine()->getManager('veterinar');
		$repo  = $em->getRepository('VidalVeterinarBundle:ClinicoPhPointers');
		$codes = $repo->findForTree();

		# надо сгруппировать по родителю (запихпуть в list родителя дочерние)
		for ($i = 11; $i > 0; $i = $i - 3) {
			foreach ($codes as $codeValue => $code) {
				if (strlen($codeValue) == $i) {
					$key = substr($codeValue, 0, -3);
					if (isset($codes[$key]) && strlen($codeValue) > strlen($key)) {
						$codes[$key]['list'][$codeValue] = $code;
					}
				}
			}
		}

		$grouped = array();

		foreach ($codes as $codeValue => $code) {
			if (strlen($codeValue) == 2) {
				$grouped[] = $code;
			}
		}

		return array('codes' => $grouped);
	}

	/** @Route("/elastic/autocomplete_veterinar/{term}", name="elastic_autocomplete_veterinar", options={"expose":true}) */
	public function autocompleteNozologyAction($term)
	{
		$words  = explode(' ', $term);
		$query  = implode('* ', $words) . '*';
		$client = new \Elasticsearch\Client();

		$s['index'] = 'website';
		$s['type']  = 'veterinar_autocomplete';

		$s['body']['size']                                                 = 15;
		$s['body']['query']['filtered']['query']['query_string']['query']  = $query;
		$s['body']['query']['filtered']['query']['query_string']['fields'] = array('code', 'name');
		$s['body']['highlight']['fields']['name']                          = array("fragment_size" => 100);
		$s['body']['sort']['name']['order']                                = 'asc';

		$results = $client->search($s);

		return new JsonResponse($results);
	}

	/**
	 * @Route("/veterinar/podrobno-o-preparate", name="veterinar_portfolios")
	 * @Template("VidalVeterinarBundle:Vidal:portfolios.html.twig")
	 */
	public function portfoliosAction()
	{
		$em         = $this->getDoctrine()->getManager('veterinar');
		$portfolios = $em->getRepository('VidalVeterinarBundle:PharmPortfolio')->findActive();

		return array(
			'title'          => 'Портфели препаратов | Видаль-Ветеринар',
			'menu_veterinar' => 'portfolios',
			'portfolios'     => $portfolios,
		);
	}

	/**
	 * @Route("/veterinar/podrobno-o-preparate/{url}", name="veterinar_portfolio")
	 * @Template("VidalVeterinarBundle:Vidal:portfolio.html.twig")
	 */
	public function portfolioAction($url)
	{
		$em        = $this->getDoctrine()->getManager('veterinar');
		$portfolio = $em->getRepository('VidalVeterinarBundle:PharmPortfolio')->findOneByUrl($url);

		if (!$portfolio) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'title'     => $this->strip($portfolio->getTitle()) . ' | Портфель препарата | Видаль-Ветеринар',
			'menu_veterinar' => 'portfolios',
			'portfolio' => $portfolio,
			'products'  => $em->getRepository('VidalVeterinarBundle:Product')->findByPortfolio($portfolio->getId()),
		);

		return $params;
	}

	/** Получить массив идентификаторов продуктов */
	private function getProductIds($products)
	{
		$productIds = array();

		if ($products[0] instanceof Product) {
			foreach ($products as $product) {
				$productIds[] = $product->getProductID();
			}
		}
		else {
			foreach ($products as $product) {
				$productIds[] = $product['ProductID'];
			}
		}

		return $productIds;
	}

	private function getDocumentIds($documents)
	{
		$ids = array();

		foreach ($documents as $document) {
			$ids[] = $document->getDocumentID();
		}

		return $ids;
	}

	private function strip($string)
	{
		return strip_tags(html_entity_decode($string, ENT_QUOTES, 'UTF-8'));
	}
}
