<?php
namespace Vidal\DrugBundle\Entity;

use Doctrine\ORM\EntityRepository;

class ProductRepository extends EntityRepository
{
	protected $atcCodes = array();

	public function findFieldsByProductID($ProductID)
	{
		return $this->_em->createQuery('
			SELECT DISTINCT p.ProductID, p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto,
				d.Indication, d.DocumentID, d.ClPhGrDescription
			FROM VidalDrugBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalDrugBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE p.ProductID = :ProductID
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
		')->setParameter('ProductID', $ProductID)
			->getOneOrNullResult();
	}

	public function findByProductID($ProductID)
	{
		return $this->_em->createQuery("
			SELECT p
			FROM VidalDrugBundle:Product p
			WHERE p = :ProductID
				AND p.inactive = FALSE
		")->setParameter('ProductID', $ProductID)
			->getOneOrNullresult();
	}

	public function findOneByProductID($ProductID)
	{
		return $this->_em->createQuery("
			SELECT p
			FROM VidalDrugBundle:Product p
			WHERE p = :ProductID
				AND p.inactive = FALSE
		")->setParameter('ProductID', $ProductID)
			->getOneOrNullresult();
	}

	public function findBadByName($name)
	{
		return $this->_em->createQuery("
			SELECT p
			FROM VidalDrugBundle:Product p
			WHERE p.Name = :name
				AND p.inactive = FALSE
				AND p.ProductTypeCode = 'BAD'
		")->setParameter('name', $name)
			->setMaxResults(1)
			->getOneOrNullresult();
	}

	public function findByDocumentID($DocumentID)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatusID, p.ProductID,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto
			FROM VidalDrugBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalDrugBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d = :DocumentID
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('DocumentID', $DocumentID)
			->getResult();
	}

	public function findByPortfolio($portfolio)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatusID, p.ProductID,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto
			FROM VidalDrugBundle:Product p
			JOIN p.document d
			JOIN d.portfolios portfolio WITH portfolio.id = :portfolioId
			LEFT JOIN VidalDrugBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('portfolioId', $portfolio->getId())
			->getResult();
	}

	public function findByDocumentIDs($documentIds)
	{
		$raw = $this->_em->createQuery('
			SELECT p.ProductID, p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, d.ArticleID, d.Indication, d.DocumentID
			FROM VidalDrugBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalDrugBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d IN (:DocumentIDs)
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('DocumentIDs', $documentIds)
			->getResult();

		$products = array();

		foreach ($raw as $product) {
			$key = $product['ProductID'];
			if (!isset($products[$key])) {
				$products[$key] = $product;
			}
		}

		return array_values($products);
	}

	public function findByMolecules($molecules)
	{
		$moleculeIds = array();
		foreach ($molecules as $molecule) {
			$moleculeIds[] = $molecule->getMoleculeID();
		}

		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatusID, p.ProductID,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto
			FROM VidalDrugBundle:Product p
			LEFT JOIN p.moleculeNames mn
			LEFT JOIN VidalDrugBundle:Molecule m WITH m = mn.MoleculeID
			LEFT JOIN VidalDrugBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE m IN (:moleculeIds)
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('moleculeIds', $moleculeIds)
			->getResult();
	}

	public function findByATCCode($ATCCode)
	{
		return $this->_em->createQuery('
			SELECT p.ProductID, p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, p.NonPrescriptionDrug,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto,
				d.Indication, d.DocumentID, d.ArticleID, d.RusName DocumentRusName, d.EngName DocumentEngName,
				d.Name DocumentName
			FROM VidalDrugBundle:Product p
			JOIN p.atcCodes a WITH a = :ATCCode
			LEFT JOIN p.document d
			WHERE p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('ATCCode', $ATCCode)
			->getResult();
	}

	public function findByArticle($articleId, $isDoctor = false)
	{
		$qb = $this->_em->createQueryBuilder();

		$qb->select('p.ProductID, p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, p.NonPrescriptionDrug,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto,
				d.Indication, d.DocumentID, d.ArticleID, d.RusName DocumentRusName, d.EngName DocumentEngName,
				d.Name DocumentName')
			->from('VidalDrugBundle:Product', 'p')
			->join('p.document', 'd')
			->join('d.nozologies', 'n')
			->join('n.articles', 'a')
			->where('p.MarketStatusID IN (1,2,7)')
			->andWhere('p.ProductTypeCode NOT IN (\'SUBS\')')
			->andWhere('p.inactive = FALSE')
			->andWhere('a.id = :articleId')
			->andWhere('d.ArticleID IN (2,5)')
			->setParameter('articleId', $articleId)
			->orderBy('p.RusName', 'ASC');

		if (!$isDoctor) {
			$qb->andWhere('p.NonPrescriptionDrug = TRUE');
		}

		return $qb->getQuery()->getResult();
	}

	public function findByClPhGroupID($ClPhGroupsID)
	{
		return $this->_em->createQuery('
			SELECT p.ProductID, p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, p.NonPrescriptionDrug,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto,
				d.Indication, d.DocumentID, d.ArticleID, d.RusName DocumentRusName, d.EngName DocumentEngName,
				d.Name DocumentName
			FROM VidalDrugBundle:Product p
			JOIN p.clphGroups g
			LEFT JOIN p.document d
			WHERE g = :ClPhGroupsID
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('ClPhGroupsID', $ClPhGroupsID)
			->getResult();
	}

	public function findByMoleculeID($MoleculeID)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.ProductID, p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug,
				p.RegistrationNumber, p.RegistrationDate, p.photo, p.hidePhoto,
				d.Indication, d.DocumentID, d.ArticleID, d.RusName DocumentRusName, d.EngName DocumentEngName,
				d.Name DocumentName
			FROM VidalDrugBundle:Product p
			LEFT JOIN p.moleculeNames mn
			LEFT JOIN p.document d
			WHERE mn.MoleculeID = :MoleculeID
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY d.ArticleID ASC
		')->setParameter('MoleculeID', $MoleculeID)
			->getResult();
	}

	public function findByOwner($CompanyID)
	{
		$productsRaw = $this->_em->createQuery('
			SELECT p.ProductID, p.RusName, p.EngName, p.ProductTypeCode, p.Name, p.NonPrescriptionDrug, p.ZipInfo,
				p.RegistrationNumber, p.RegistrationDate, p.photo, p.hidePhoto,
				country.RusName CompanyCountry,
				d.Indication, d.DocumentID, d.ArticleID, d.RusName DocumentRusName, d.EngName DocumentEngName,
				d.Name DocumentName,
				i.InfoPageID, i.RusName InfoPageName, co.RusName InfoPageCountry
			FROM VidalDrugBundle:Product p
			JOIN VidalDrugBundle:ProductCompany pc WITH pc.ProductID = p
			JOIN VidalDrugBundle:Company c WITH pc.CompanyID = c
			LEFT JOIN VidalDrugBundle:Country country WITH c.CountryCode = country
			LEFT JOIN p.document d
			LEFT JOIN d.infoPages i
			LEFT JOIN VidalDrugBundle:Country co WITH i.CountryCode = co
			WHERE c = :CompanyID
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('CompanyID', $CompanyID)
			->getResult();

		# надо отсеять дубли препаратов
		$products = array();

		foreach ($productsRaw as $product) {
			$key = $product['ProductID'];

			if (!isset($products[$key])) {
				$products[$key] = $product;
			}
		}

		return array_values($products);
	}

	public function findByInfoPageID($InfoPageID)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.ProductID, p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.hidePhoto,
				p.RegistrationNumber, p.RegistrationDate
			FROM VidalDrugBundle:Product p
			LEFT JOIN p.document d
			JOIN d.infoPages i
			WHERE i.InfoPageID = :InfoPageID
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('InfoPageID', $InfoPageID)
			->getResult();
	}

	public function findAutocomplete()
	{
		$products = $this->_em->createQuery('
			SELECT DISTINCT p.RusName, p.EngName
			FROM VidalDrugBundle:Product p
			WHERE p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->getResult();

		$productNames = array();

		for ($i = 0; $i < count($products); $i++) {
			$patterns     = array('/<SUP>.*<\/SUP>/', '/<SUB>.*<\/SUB>/', '/&alpha;/', '/&plusmn;/', '/&reg;/', '/&shy;/');
			$replacements = array('', '', ' ', ' ', ' ', ' ');
			$RusName      = preg_replace($patterns, $replacements, $products[$i]['RusName']);
			$RusName      = mb_strtolower(str_replace('  ', ' ', $RusName), 'UTF-8');
			$EngName      = preg_replace($patterns, $replacements, $products[$i]['EngName']);
			$EngName      = mb_strtolower(str_replace('  ', ' ', $EngName), 'UTF-8');

			if (!empty($RusName)) {
				$productNames[] = $RusName;
			}

			if (!empty($EngName)) {
				$productNames[] = $EngName;
			}
		}

		$productNames = array_unique($productNames);
		usort($productNames, 'strcasecmp');

		return $productNames;
	}

	public function findByQuery($q, $badIncluded = false)
	{
		$miIncluded = $badIncluded;
		$qb         = $this->_em->createQueryBuilder();
		$anyOfWord  = null;

		$qb->select('p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, pt.ProductTypeCode,
				d.Indication, d.ArticleID, d.DocumentID')
			->from('VidalDrugBundle:Product', 'p')
			->leftJoin('p.document', 'd')
			->leftJoin('VidalDrugBundle:ProductType', 'pt', 'WITH', 'p.ProductTypeCode = pt.ProductTypeCode')
			->orderBy('p.RusName', 'ASC')
			->andWhere('p.MarketStatusID IN (1,2,7)')
			->andWhere('p.inactive = FALSE');

		# включать ли бады или медицинские изделия
		if ($badIncluded) {
			$qb->andWhere("p.ProductTypeCode NOT IN ('SUBS')");
		}
		else {
			$qb->andWhere("p.ProductTypeCode NOT IN ('BAD', 'SUBS')");
		}

		$words = explode(' ', $q);

		# поиск по всем словам вместе
		$where = '';

		for ($i = 0; $i < count($words); $i++) {
			$word = $words[$i];
			if ($i > 0) {
				$where .= ' AND ';
			}
			$where .= "(p.RusName2 LIKE '$word%' OR p.EngName LIKE '$word%' OR p.RusName2 LIKE '% $word%' OR p.EngName LIKE '% $word%' OR p.RusName2 LIKE '%-$word' OR p.EngName LIKE '%-$word')";
		}

		$qb->andWhere($where);
		$productsRaw = $qb->getQuery()->getResult();

		# поиск по любому из слов, если по всем не дал результата
		if (empty($productsRaw)) {
			# определяем, стоит ли искать по любому слову, должно быть хотя бы одно слово от 3х символов
			for ($i = 0; $i < count($words); $i++) {
				if (mb_strlen($words[$i], 'utf-8') > 2) {
					$anyOfWord[] = $words[$i];
				}
			}

			if (empty($anyOfWord)) {
				return array(array(), null);
			}

			$where = '';

			for ($i = 0; $i < count($anyOfWord); $i++) {
				$word = $anyOfWord[$i];
				if ($i > 0) {
					$where .= ' OR ';
				}
				$where .= "(p.RusName2 LIKE '$word%' OR p.EngName LIKE '$word%' OR p.RusName2 LIKE '% $word%' OR p.EngName LIKE '% $word%' OR p.RusName2 LIKE '%-$word' OR p.EngName LIKE '%-$word')";
			}

			$productsRaw = $qb
				->andWhere('p.MarketStatusID IN (1,2,7)')
				->andWhere('p.inactive = FALSE')
				->andWhere($where)
				->getQuery()->getResult();
		}

		$products        = array();
		$articlePriority = array(2, 5, 4, 3, 1);

		# отсеиваем дубли препаратов
		for ($i = 0; $i < count($productsRaw); $i++) {
			$key = $productsRaw[$i]['ProductID'];
			if (!isset($products[$key])) {
				$products[$key] = $productsRaw[$i];
			}
			else {
				# надо взять препарат по приоритету Document.ArticleID [2,5,4,3,1]
				$curr = array_search($products[$key]['ArticleID'], $articlePriority);
				$new  = array_search($productsRaw[$i]['ArticleID'], $articlePriority);
				if ($new < $curr) {
					$products[$key] = $productsRaw[$i];
				}
			}
		}

		return array(array_values($products), $anyOfWord);
	}

	public function findByDocuments25($documents)
	{
		$documentIds = array();

		foreach ($documents as $document) {
			if ($document['CountryEditionCode'] == 'RUS' &&
				($document['ArticleID'] == 2 || $document['ArticleID'] == 5)
			) {
				$documentIds[] = $document['DocumentID'];
			}
		}

		if (empty($documentIds)) {
			return array();
		}

		$productsRaw = $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, d.Indication, d.DocumentID, p.photo, p.hidePhoto
			FROM VidalDrugBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalDrugBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d IN (:documentIds)
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('documentIds', $documentIds)
			->getResult();

		# исключаем повторения продуктов по приоритему
		$products = array();

		for ($i = 0, $c = count($productsRaw); $i < $c; $i++) {
			$key = $productsRaw[$i]['ProductID'];

			if (!isset($products[$key])) {
				$products[$key] = $productsRaw[$i];
			}
		}

		return $products;
	}

	public function findByDocuments4($documents)
	{
		$documentIds = array();

		foreach ($documents as $document) {
			if ($document['CountryEditionCode'] == 'RUS' &&
				$document['ArticleID'] == 4
			) {
				$documentIds[] = $document['DocumentID'];
			}
		}

		if (empty($documentIds)) {
			return array();
		}

		$productsRaw = $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, d.Indication, d.DocumentID
			FROM VidalDrugBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalDrugBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d IN (:documentIds)
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('documentIds', $documentIds)
			->getResult();

		# исключаем повторения продуктов по приоритему
		$products = array();

		for ($i = 0, $c = count($productsRaw); $i < $c; $i++) {
			$key = $productsRaw[$i]['ProductID'];

			if (!isset($products[$key])) {
				$products[$key] = $productsRaw[$i];
			}
		}

		return $products;
	}

	public function findByClPhGroup($description)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto,
				d.Indication, d.DocumentID, d.ClPhGrDescription
			FROM VidalDrugBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalDrugBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d.ClPhGrName = :description
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('description', $description)
			->getResult();
	}

	public function findByPhThGroup($id)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto,
				d.Indication, d.DocumentID
			FROM VidalDrugBundle:Product p
			JOIN p.phthgroups g WITH g.id = :id
			LEFT JOIN p.document d
			LEFT JOIN VidalDrugBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('id', $id)
			->getResult();
	}

	public function findPhThGroupsByQuery($q)
	{
		$qb = $this->_em->createQueryBuilder();

		$qb->select('DISTINCT g.Name, g.id')
			->from('VidalDrugBundle:Product', 'p')
			->join('p.phthgroups', 'g')
			->where("p.MarketStatusID IN (1,2,7) AND p.ProductTypeCode NOT IN ('SUBS') AND p.inactive = FALSE")
			->orderBy('g.Name', 'ASC');

		# поиск по всем словам словам
		$where = '';
		$words = explode(' ', $q);

		for ($i = 0; $i < count($words); $i++) {
			$word = $words[$i];
			if ($i > 0) {
				$where .= ' OR ';
			}
			$where .= "(g.Name LIKE '$word%' OR g.Name LIKE '% $word%')";
		}

		$qb->andWhere($where);
		$groups = $qb->getQuery()->getResult();

		# поиск по любому из слов
		if (empty($groups)) {
			foreach ($words as $word) {
				if (mb_strlen($word, 'utf-8') < 4) {
					return array();
				}
			}

			$where = '';

			for ($i = 0; $i < count($words); $i++) {
				$word = $words[$i];
				if ($i > 0) {
					$where .= ' AND ';
				}
				$where .= "(g.Name LIKE '$word%' OR g.Name LIKE '% $word%')";
			}

			$qb->where("p.MarketStatusID IN (1,2,7) AND p.ProductTypeCode NOT IN ('SUBS') AND p.inactive = FALSE");
			$qb->andWhere($where);
			$groups = $qb->getQuery()->getResult();
		}

		for ($i = 0, $c = count($groups); $i < $c; $i++) {
			$name               = $this->mb_ucfirst($groups[$i]['Name']);
			$groups[$i]['Name'] = preg_replace('/' . $q . '/iu', '<span class="query">$0</span>', $name);
		}

		return $groups;
	}

	public function getQueryByLetter($letter, $type, $nonPrescription)
	{
		$qb = $this->_em->createQueryBuilder();

		$qb->select('DISTINCT p')
			->from('VidalDrugBundle:Product', 'p')
			->andWhere('p.MarketStatusID IN (1,2,7)')
			->andWhere('p.inactive = FALSE')
			->orderBy('p.RusName', 'ASC');

		if ($letter) {
			$qb->andWhere('p.RusName LIKE :likeName')->setParameter('likeName', $letter . '%');
		}

		if ($type == 'p') {
			$qb->andWhere('p.ProductTypeCode NOT IN (\'SUBS\', \'BAD\')');
		}
		elseif ($type == 'b') {
			$qb->andWhere('p.ProductTypeCode = \'BAD\'');
		}
		else {
			$qb->andWhere('p.ProductTypeCode NOT IN (\'SUBS\')');
		}

		if ($nonPrescription) {
			$qb->andWhere('p.NonPrescriptionDrug = 1');
		}

		return $qb->getQuery();
	}

	public function findMarketStatusesByProductIds($productIds)
	{
		$raw = $this->_em->createQuery('
			SELECT p.ProductID, ms.RusName MarketStatus
			FROM VidalDrugBundle:Product p
			JOIN p.MarketStatusID ms
			WHERE p.ProductID IN (:productIds)
				AND p.inactive = FALSE
		')->setParameter('productIds', $productIds)
			->getResult();

		$marketStatuses = array();

		for ($i = 0; $i < count($raw); $i++) {
			$key              = $raw[$i]['ProductID'];
			$marketStatuses[] = $raw[$i]['MarketStatus'];
		}

		return $marketStatuses;
	}

	public function findByKfu($ClPhPointerID)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.ProductID, p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug,
				p.RegistrationNumber, p.RegistrationDate, p.photo, p.hidePhoto,
				d.Indication, d.DocumentID, d.ArticleID, d.RusName DocumentRusName, d.EngName DocumentEngName,
				d.Name DocumentName
			FROM VidalDrugBundle:Product p
			JOIN p.document d
			JOIN d.clphPointers pointer
			WHERE pointer = :id
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
				AND d.ArticleID IN (1,2,3,4,5)
			ORDER BY p.RusName ASC
		')->setParameter('id', $ClPhPointerID)
			->getResult();
	}

	public function findAllNames()
	{
		return $this->_em->createQuery('
			SELECT DISTINCT p.RusName
			FROM VidalDrugBundle:Product p
			ORDER BY p.RusName
		')->getResult();
	}

	public function countByCompanyID($CompanyID)
	{
		return $this->_em->createQuery('
			SELECT COUNT(DISTINCT p.ProductID)
			FROM VidalDrugBundle:Product p
			JOIN VidalDrugBundle:ProductCompany pc WITH pc.ProductID = p
			JOIN VidalDrugBundle:Company c WITH pc.CompanyID = c
			WHERE c = :CompanyID
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('CompanyID', $CompanyID)
			->getSingleScalarResult();
	}

	public function countByDocumentIds($documentIds)
	{
		if (empty($documentIds)) {
			return 0;
		}

		$count = $this->_em->createQuery('
			SELECT COUNT(DISTINCT p.ProductID)
			FROM VidalDrugBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalDrugBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d IN (:DocumentIDs)
				AND p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode NOT IN (\'SUBS\')
				AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('DocumentIDs', $documentIds)
			->getSingleScalarResult();

		return $count;
	}

	public function findByProductType($t = 'p', $n = false)
	{
		$pdo = $this->_em->getConnection();

		switch ($t) {
			case 'p':
				$where = "('DRUG', 'GOME', 'ALRG', 'DIAG', 'SRED')";
				break;
			case 'b':
				$where = "('BAD')";
				break;
			default:
				$where = "('DRUG', 'GOME', 'ALRG', 'DIAG', 'SRED', 'BAD')";
		}

		if ($n) {
			$where .= " AND NonPrescriptionDrug = 1";
		}

		$sql = "
			SELECT DISTINCT LEFT(RusName , 2) as letters
			FROM product
			WHERE LEFT(RusName, 1) NOT IN ('1','2','3','5','9','_','D','H','L','N','Q','S')
				AND MarketStatusID IN (1,2,7)
				AND ProductTypeCode IN {$where}
			ORDER BY letters
		";

		$stmt = $pdo->prepare($sql);
		$stmt->execute();

		$raw           = $stmt->fetchAll();
		$syllables     = array();
		$secondLetters = array();

		foreach ($raw as $r) {
			$first  = mb_substr($r['letters'], 0, 1, 'utf-8');
			$second = mb_substr($r['letters'], 1, 2, 'utf-8');

			isset($syllables[$first])
				? $syllables[$first][] = $r['letters']
				: $syllables[$first] = array($r['letters']);

			if (!isset($secondLetters[$second])) {
				$secondLetters[$second] = true;
			}
		}

		$raws          = array();
		$table         = array();
		$firstLetters  = array_keys($syllables);
		$secondLetters = array_keys($secondLetters);

		usort($secondLetters, 'strcmp');

		foreach ($raw as $r) {
			$key        = $r['letters'];
			$raws[$key] = true;
		}

		foreach ($secondLetters as $secondLetter) {
			$table[$secondLetter] = array();

			foreach ($firstLetters as $firstLetter) {
				$key                    = $firstLetter . $secondLetter;
				$table[$secondLetter][] = isset($raws[$key]) ? $key : null;
			}
		}

		return array($syllables, $table);
	}

	public function publicationsByProduct($ProductID)
	{
		return $this->_em->createQuery('
			SELECT p.id, p.title, p.date, product.RusName productTitle
			FROM VidalDrugBundle:Publication p
			JOIN p.products product WITH product.ProductID = :ProductID
			WHERE p.enabled = TRUE
			ORDER BY p.date DESC
		')->setParameter('ProductID', $ProductID)
			->getResult();
	}

	public function findAllATC($product)
	{
		foreach ($product->getATCCodes() as $atcCode) {
			$this->findParentATC($atcCode);
		}

		return $this->atcCodes;
	}

	public function publicationsByAtc($atcCodes)
	{
		$raw = $this->_em->createQuery('
			SELECT p.id, p.title, p.date, atc.ATCCode atcTitle
			FROM VidalDrugBundle:Publication p
			JOIN p.atcCodes atc WITH atc.ATCCode IN (:atcCodes)
			WHERE p.enabled = TRUE
			ORDER BY p.date DESC
		')->setParameter('atcCodes', $atcCodes)
			->getResult();

		$results = array();

		foreach ($raw as $r) {
			$key = $r['atcTitle'];

			isset ($results[$key])
				? $results[$key][] = $r
				: $results[$key] = array($r);
		}

		return $results;
	}

	public function publicationsByMolecule($ProductID)
	{
		$raw = $this->_em->createQuery('
			SELECT p.id, p.title, p.date, m.RusName moleculeTitle
			FROM VidalDrugBundle:Publication p
			JOIN p.molecules m
			JOIN m.moleculeNames mn
			JOIN mn.products product WITH product.ProductID = :ProductID
			WHERE p.enabled = TRUE
				AND SIZE(product.moleculeNames) = 1
			ORDER BY p.date DESC
		')->setParameter('ProductID', $ProductID)
			->getResult();

		$results = array();

		foreach ($raw as $r) {
			$key = $r['moleculeTitle'];

			isset ($results[$key])
				? $results[$key][] = $r
				: $results[$key] = array($r);
		}

		return $results;
	}

	public function articlesByProduct($ProductID)
	{
		return $this->_em->createQuery('
			SELECT a.id, a.title, a.link, r.rubrique rubrique, r.title rubriqueTitle, product.RusName productTitle
			FROM VidalDrugBundle:Article a
			JOIN a.products product WITH product.ProductID = :ProductID
			JOIN a.rubrique r
			WHERE a.enabled = TRUE
				AND r.enabled = TRUE
			ORDER BY a.date DESC
		')->setParameter('ProductID', $ProductID)
			->getResult();
	}

	public function articlesByAtc($atcCodes)
	{
		$raw = $this->_em->createQuery('
			SELECT a.id, a.title, a.link, r.rubrique rubrique, r.title rubriqueTitle, atc.ATCCode atcTitle
			FROM VidalDrugBundle:Article a
			JOIN a.atcCodes atc WITH atc.ATCCode IN (:atcCodes)
			JOIN a.rubrique r
			WHERE a.enabled = TRUE
				AND r.enabled = TRUE
			ORDER BY a.date DESC
		')->setParameter('atcCodes', $atcCodes)
			->getResult();

		$results = array();

		foreach ($raw as $r) {
			$key = $r['atcTitle'];

			isset ($results[$key])
				? $results[$key][] = $r
				: $results[$key] = array($r);
		}

		return $results;
	}

	public function articlesByMolecule($ProductID)
	{
		$raw = $this->_em->createQuery('
			SELECT a.id, a.title, a.link, r.rubrique rubrique, r.title rubriqueTitle, m.RusName moleculeTitle
			FROM VidalDrugBundle:Article a
			JOIN a.molecules m
			JOIN m.moleculeNames mn
			JOIN mn.products product WITH product.ProductID = :ProductID
			JOIN a.rubrique r
			WHERE a.enabled = TRUE
				AND r.enabled = TRUE
			ORDER BY a.date DESC
		')->setParameter('ProductID', $ProductID)
			->getResult();

		$results = array();

		foreach ($raw as $r) {
			$key = $r['moleculeTitle'];

			isset ($results[$key])
				? $results[$key][] = $r
				: $results[$key] = array($r);
		}

		return $results;
	}

	public function artsByProduct($ProductID)
	{
		return $this->_em->createQuery('
			SELECT a.id, a.title, a.link, r.url rubriqueUrl, r.title rubriqueTitle,
				c.url categoryUrl, t.url typeUrl, product.RusName productTitle
			FROM VidalDrugBundle:Art a
			JOIN a.products product WITH product.ProductID = :ProductID
			JOIN a.rubrique r
			LEFT JOIN a.category c
			LEFT JOIN a.type t
			WHERE a.enabled = TRUE
				AND r.enabled = TRUE
				AND (t IS NULL OR t.enabled = TRUE)
				AND (c IS NULL OR c.enabled = TRUE)
			ORDER BY a.date DESC
		')->setParameter('ProductID', $ProductID)
			->getResult();
	}

	public function artsByAtc($atcCodes)
	{
		$raw = $this->_em->createQuery('
			SELECT a.id, a.title, a.link, r.url rubriqueUrl, r.title rubriqueTitle,
				c.url categoryUrl, t.url typeUrl, atc.ATCCode atcTitle
			FROM VidalDrugBundle:Art a
			JOIN a.atcCodes atc WITH atc.ATCCode IN (:atcCodes)
			JOIN a.rubrique r
			LEFT JOIN a.category c
			LEFT JOIN a.type t
			WHERE a.enabled = TRUE
				AND r.enabled = TRUE
				AND (t IS NULL OR t.enabled = TRUE)
				AND (c IS NULL OR c.enabled = TRUE)
			ORDER BY a.date DESC
		')->setParameter('atcCodes', $atcCodes)
			->getResult();

		$results = array();

		foreach ($raw as $r) {
			$key = $r['atcTitle'];

			isset ($results[$key])
				? $results[$key][] = $r
				: $results[$key] = array($r);
		}

		return $results;
	}

	public function artsByMolecule($ProductID)
	{
		$raw = $this->_em->createQuery('
			SELECT a.id, a.title, a.link, r.url rubriqueUrl, r.title rubriqueTitle,
				c.url categoryUrl, t.url typeUrl, m.RusName moleculeTitle
			FROM VidalDrugBundle:Art a
			JOIN a.molecules m
			JOIN m.moleculeNames mn
			JOIN mn.products product WITH product.ProductID = :ProductID
			JOIN a.rubrique r
			LEFT JOIN a.category c
			LEFT JOIN a.type t
			WHERE a.enabled = TRUE
				AND r.enabled = TRUE
				AND (t IS NULL OR t.enabled = TRUE)
				AND (c IS NULL OR c.enabled = TRUE)
			ORDER BY a.date DESC
		')->setParameter('ProductID', $ProductID)
			->getResult();

		$results = array();

		foreach ($raw as $r) {
			$key = $r['moleculeTitle'];

			isset ($results[$key])
				? $results[$key][] = $r
				: $results[$key] = array($r);
		}

		return $results;
	}

	/**
	 * Функция возвращает слово с заглавной первой буквой (c поддержкой кирилицы)
	 *
	 * @param string $string
	 * @param string $encoding
	 * @return string
	 */
	private function mb_ucfirst($string, $encoding = 'utf-8')
	{
		$strlen    = mb_strlen($string, $encoding);
		$firstChar = mb_substr($string, 0, 1, $encoding);
		$then      = mb_substr($string, 1, $strlen - 1, $encoding);

		return mb_strtoupper($firstChar, $encoding) . $then;
	}

	private function findParentATC($atcCode)
	{
		$this->atcCodes[] = $atcCode->getATCCode();

		if ($parent = $atcCode->getParent()) {
			$this->findParentATC($parent);
		}
	}
}