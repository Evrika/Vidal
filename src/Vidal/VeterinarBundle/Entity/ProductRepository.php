<?php
namespace Vidal\VeterinarBundle\Entity;

use Doctrine\ORM\EntityRepository;

class ProductRepository extends EntityRepository
{
	public function findByLetter($letter)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, d.ArticleID, d.Indication, d.DocumentID, p.inactive
			FROM VidalVeterinarBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalVeterinarBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE p.RusName LIKE :letter AND p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('letter', $letter . '%')
			->getResult();
	}

	public function findByCompany($CompanyID)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.ProductID, p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto,
				p.RegistrationNumber, p.RegistrationDate, p.inactive,
				country.RusName CompanyCountry,
				d.Indication, d.DocumentID, d.ArticleID, d.RusName DocumentRusName, d.EngName DocumentEngName,
				d.Name DocumentName,
				i.InfoPageID, i.RusName InfoPageName, co.RusName InfoPageCountry
			FROM VidalVeterinarBundle:Product p
			JOIN VidalVeterinarBundle:ProductCompany pc WITH pc.ProductID = p
			JOIN VidalVeterinarBundle:Company c WITH pc.CompanyID = c
			LEFT JOIN VidalVeterinarBundle:Country country WITH c.CountryCode = country
			LEFT JOIN p.document d
			LEFT JOIN d.infoPages i
			LEFT JOIN VidalVeterinarBundle:Country co WITH i.CountryCode = co
			WHERE c = :CompanyID AND p.inactive = false
			ORDER BY p.RusName ASC
		')->setParameter('CompanyID', $CompanyID)
			->getResult();
	}

	public function findByProductID($ProductID)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.Composition, p.inactive
			FROM VidalVeterinarBundle:Product p
			LEFT JOIN VidalVeterinarBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE p = :ProductID AND p.inactive = FALSE
		')->setParameter('ProductID', $ProductID)
			->getOneOrNullresult();
	}

	public function findOneByName($Name)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.Composition, p.inactive
			FROM VidalVeterinarBundle:Product p
			LEFT JOIN VidalVeterinarBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE p.Name = :Name AND p.inactive = FALSE
		')->setParameter('Name', $Name)
			->setMaxResults(1)
			->getOneOrNullresult();
	}

	public function findOneByDocumentID($DocumentID)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.Composition, p.inactive
			FROM VidalVeterinarBundle:Product p
			JOIN p.document d
			LEFT JOIN VidalVeterinarBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d = :DocumentID AND p.inactive = FALSE
		')->setParameter('DocumentID', $DocumentID)
			->setMaxResults(1)
			->getOneOrNullresult();
	}

	public function findByDocumentID($DocumentID)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.inactive
			FROM VidalVeterinarBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalVeterinarBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d = :DocumentID AND
				p.CountryEditionCode = \'RUS\' AND
				p.inactive = FALSE AND
				(p.ProductTypeCode = \'DRUG\' OR p.ProductTypeCode = \'GOME\')
			ORDER BY p.RusName ASC
		')->setParameter('DocumentID', $DocumentID)
			->getResult();
	}

	public function findByDocumentIDs($documentIds)
	{
		return $this->_em->createQuery('
			SELECT DISTINCT(p.ProductID), p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, d.ArticleID, d.Indication, d.DocumentID, p.inactive
			FROM VidalVeterinarBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalVeterinarBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d IN (:DocumentIDs) AND
				p.CountryEditionCode = \'RUS\' AND
				p.MarketStatusID IN (0,1,2,3,4,5,6,7) AND
				p.ProductTypeCode IN (\'DRUG\',\'GOME\') AND
				p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('DocumentIDs', $documentIds)
			->getResult();
	}


	public function findByInfoPageID($InfoPageID)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.ProductID, p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto,
				p.RegistrationNumber, p.RegistrationDate, p.inactive
			FROM VidalVeterinarBundle:Product p
			JOIN p.document d
			JOIN d.infoPages WITH i.InfoPageID = :InfoPageID
			WHERE p.CountryEditionCode = \'RUS\' AND
				(p.MarketStatusID = 1 OR p.MarketStatusID = 2) AND
				(p.ProductTypeCode = \'DRUG\' OR p.ProductTypeCode = \'GOME\') AND
				p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('InfoPageID', $InfoPageID)
			->getResult();
	}

	public function findProductNames()
	{
		$products = $this->_em->createQuery('
			SELECT DISTINCT p.RusName, p.EngName
			FROM VidalVeterinarBundle:Product p
			ORDER BY p.RusName ASC
		')->getResult();

		$productNames = array();

		for ($i = 0; $i < count($products); $i++) {
			$patterns     = array('/<SUP>.*<\/SUP>/', '/<SUB>.*<\/SUB>/');
			$replacements = array('', '');
			$RusName      = preg_replace($patterns, $replacements, $products[$i]['RusName']);
			$RusName      = mb_strtolower($RusName, 'UTF-8');
			$EngName      = preg_replace($patterns, $replacements, $products[$i]['EngName']);
			$EngName      = mb_strtolower($EngName, 'UTF-8');

			if (!empty($RusName)) {
				$productNames[] = $RusName;
			}

			if (!empty($EngName)) {
				$productNames[] = $EngName;
			}
		}

		return $productNames;
	}

	public function findByQuery($q)
	{
		$qb = $this->_em->createQueryBuilder();

		$qb
			->select('p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.inactive,
				d.Indication, d.ArticleID')
			->from('VidalVeterinarBundle:Product', 'p')
			->leftJoin('p.document', 'd')
			->orderBy('p.RusName', 'ASC');

		# поиск по словам
		$where = '';
		$words = explode(' ', $q);

		# ищем совпадение по всем словам
		for ($i = 0; $i < count($words); $i++) {
			$word = $words[$i];
			if ($i > 0) {
				$where .= ' AND ';
			}
			$where .= "(p.RusName LIKE '$word%' OR p.EngName LIKE '$word%' OR p.RusName LIKE '% $word%' OR p.EngName LIKE '% $word%')";
		}
		$qb->where($where);
		$qb->andWhere('p.inactive = FALSE');
		$productsRaw = $qb->getQuery()->getResult();

		# ищем совпадение по любому из слов
		if (empty($productsRaw)) {
			$where = '';
			for ($i = 0; $i < count($words); $i++) {
				$word = $words[$i];
				if ($i > 0) {
					$where .= ' OR ';
				}
				$where .= "(p.RusName LIKE '$word%' OR p.EngName LIKE '$word%' OR p.RusName LIKE '% $word%' OR p.EngName LIKE '% $word%')";
			}

			$qb->where($where);
			$qb->andWhere('p.inactive = FALSE');

			$productsRaw = $qb->getQuery()->getResult();
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

		return array_values($products);
	}

	public function findByDocuments25($documents)
	{
		$documentIds = array();

		//d.CountryEditionCode = 'RUS'
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
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, d.Indication, d.DocumentID, p.inactive
			FROM VidalVeterinarBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalVeterinarBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d IN (:documentIds) AND
				p.CountryEditionCode = \'RUS\' AND
				(p.ProductTypeCode = \'DRUG\' OR p.ProductTypeCode = \'GOME\') AND
				p.inactive = FALSE
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
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, d.Indication, d.DocumentID, p.inactive
			FROM VidalVeterinarBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalVeterinarBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d IN (:documentIds) AND
				p.CountryEditionCode = \'RUS\' AND
				(p.ProductTypeCode = \'DRUG\' OR p.ProductTypeCode = \'GOME\') AND
				p.inactive = FALSE
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
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.inactive
				d.Indication, d.DocumentID, d.ClPhGrDescription
			FROM VidalVeterinarBundle:Product p
			LEFT JOIN p.document d
			LEFT JOIN VidalVeterinarBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE d.ClPhGrName = :description AND
				p.CountryEditionCode = \'RUS\' AND
				p.ProductTypeCode IN (\'DRUG\',\'GOME\') AND
				p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('description', $description)
			->getResult();
	}

	public function findByPhThGroup($id)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, ms.RusName MarketStatus, p.ProductID, p.photo, p.hidePhoto,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.inactive
				d.Indication, d.DocumentID
			FROM VidalVeterinarBundle:Product p
			JOIN p.phthgroups g WITH g.id = :id
			LEFT JOIN p.document d
			LEFT JOIN VidalVeterinarBundle:MarketStatus ms WITH ms.MarketStatusID = p.MarketStatusID
			WHERE p.CountryEditionCode = \'RUS\' AND
				p.ProductTypeCode IN (\'DRUG\',\'GOME\') AND
				p.inactive = FALSE
			ORDER BY p.RusName ASC
		')->setParameter('id', $id)
			->getResult();
	}

	public function findPhThGroupsByQuery($q)
	{
		$qb = $this->_em->createQueryBuilder();

		$qb->select('DISTINCT g.Name, g.id')
			->from('VidalVeterinarBundle:Product', 'p')
			->join('p.phthgroups', 'g')
			->where("p.CountryEditionCode = 'RUS' AND
				p.ProductTypeCode IN ('veterinar','GOME') AND
				p.inactive = FALSE")
			->orderBy('g.Name', 'ASC');

		# поиск по словам
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

		for ($i = 0, $c = count($groups); $i < $c; $i++) {
			$name               = $this->mb_ucfirst($groups[$i]['Name']);
			$groups[$i]['Name'] = preg_replace('/' . $q . '/iu', '<span class="query">$0</span>', $name);
		}

		return $groups;
	}

	public function findMarketStatusesByProductIds($productIds)
	{
		$raw = $this->_em->createQuery('
			SELECT p.ProductID, ms.RusName MarketStatus
			FROM VidalVeterinarBundle:Product p
			JOIN p.MarketStatusID ms
			WHERE p.ProductID IN (:productIds)
		')->setParameter('productIds', $productIds)
			->getResult();

		$marketStatuses = array();

		for ($i = 0; $i < count($raw); $i++) {
			$key              = $raw[$i]['ProductID'];
			$marketStatuses[] = $raw[$i]['MarketStatus'];
		}

		return $marketStatuses;
	}

	public function findByMoleculeID($MoleculeID)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.ProductID, p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto,
				p.RegistrationNumber, p.RegistrationDate, p.inactive,
				d.Indication, d.DocumentID, d.ArticleID, d.RusName DocumentRusName, d.EngName DocumentEngName,
				d.Name DocumentName
			FROM VidalVeterinarBundle:Product p
			LEFT JOIN p.moleculeNames mn
			LEFT JOIN p.document d
			WHERE mn.MoleculeID = :MoleculeID AND p.inactive = FALSE
			ORDER BY d.ArticleID ASC
		')->setParameter('MoleculeID', $MoleculeID)
			->getResult();
	}

	public function findAllNames()
	{
		return $this->_em->createQuery('
			SELECT DISTINCT p.RusName
			FROM VidalVeterinarBundle:Product p
			ORDER BY p.RusName
		')->getResult();
	}

	public function findByPortfolio($portfolioId)
	{
		return $this->_em->createQuery('
			SELECT p.ZipInfo, p.RegistrationNumber, p.RegistrationDate, p.ProductID, p.inactive,
				p.RusName, p.EngName, p.Name, p.NonPrescriptionDrug, p.photo, p.hidePhoto
			FROM VidalVeterinarBundle:Product p
			JOIN p.document d
			JOIN d.portfolios portfolio
			WHERE p.inactive = FALSE
				AND portfolio.id = :id
			ORDER BY p.RusName ASC
		')->setParameter('id', $portfolioId)
			->getResult();
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
}