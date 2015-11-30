<?php
namespace Vidal\VeterinarBundle\Entity;

use Doctrine\ORM\EntityRepository;

class CompanyRepository extends EntityRepository
{
	public function findByLetter($l)
	{
		return $this->_em->createQuery('
		 	SELECT c.CompanyID, c.LocalName, c.Property, country.RusName Country, c.Name
		 	FROM VidalVeterinarBundle:Company c
		 	LEFT JOIN VidalVeterinarBundle:Country country WITH country.CountryCode = c.CountryCode
		 	WHERE c.LocalName LIKE :letter
		')->setParameter('letter', $l . '%')
			->getResult();
	}

	public function findByCompanyID($CompanyID)
	{
		return $this->_em->createQuery('
			SELECT c.CompanyID, c.LocalName CompanyName, c.Property, country.RusName Country
			FROM VidalVeterinarBundle:Company c
			LEFT JOIN VidalVeterinarBundle:Country country WITH c.CountryCode = country
			WHERE c = :CompanyID
		')->setParameter('CompanyID', $CompanyID)
			->getOneOrNullResult();
	}

	public function findOneByName($name)
	{
		return $this->_em->createQuery('
			SELECT c.CompanyID, c.LocalName CompanyName, c.Property, country.RusName Country
			FROM VidalVeterinarBundle:Company c
			LEFT JOIN VidalVeterinarBundle:Country country WITH c.CountryCode = country
			WHERE c.Name = :name
		')->setParameter('name', $name)->setMaxResults(1)
			->getOneOrNullResult();
	}

	public function findAllOrdered()
	{
		return $this->_em->createQuery('
			SELECT c.LocalName, c.Name, country.RusName Country
			FROM VidalVeterinarBundle:Company c
			LEFT JOIN VidalVeterinarBundle:Country country WITH c.CountryCode = country
			ORDER BY c.LocalName ASC
		')->getResult();
	}

	public function findByQuery($q)
	{
		$qb = $this->_em->createQueryBuilder();

		$qb
			->select('c.CompanyID, c.LocalName, c.Property, country.RusName Country, c.Name')
			->from('VidalVeterinarBundle:Company', 'c')
			->leftJoin('VidalVeterinarBundle:Country', 'country', 'WITH', 'country.CountryCode = c.CountryCode')
			->orderBy('c.LocalName', 'ASC');

		# поиск по всем словам
		$where = '';
		$words = explode(' ', $q);

		for ($i = 0; $i < count($words); $i++) {
			$word = $words[$i];
			if ($i > 0) {
				$where .= ' AND ';
			}
			$where .= "(c.LocalName LIKE '$word%' OR c.LocalName LIKE '% $word%')";
		}

		$qb->where($where);
		$results = $qb->getQuery()->getResult();

		# поиск по одному слову
		if (empty($results)) {
			$where = '';
			for ($i = 0; $i < count($words); $i++) {
				$word = $words[$i];
				if ($i > 0) {
					$where .= ' OR ';
				}
				$where .= "(c.LocalName LIKE '$word%' OR c.LocalName LIKE '% $word%')";
			}
			$qb->where($where);

			return $qb->getQuery()->getResult();
		}

		return $results;
	}

	public function findOwnersByProducts($productIds)
	{
		return $this->_em->createQuery('
			SELECT DISTINCT c.CompanyID, pc.CompanyRusNote, pc.CompanyEngNote, c.LocalName, c.Property, c.Name,
				country.RusName Country
			FROM VidalVeterinarBundle:Company c
			LEFT JOIN VidalVeterinarBundle:ProductCompany pc WITH pc.CompanyID = c
			LEFT JOIN VidalVeterinarBundle:Country country WITH c.CountryCode = country
			WHERE pc.ProductID IN (:productIds) AND
				pc.ItsMainCompany = 1
		')->setParameter('productIds', $productIds)
			->getResult();
	}

	public function findDistributorsByProducts($productIds)
	{
		return $this->_em->createQuery('
			SELECT DISTINCT c.CompanyID, pc.CompanyRusNote, pc.CompanyEngNote, c.LocalName, c.Property, c.Name,
				country.RusName Country
			FROM VidalVeterinarBundle:Company c
			LEFT JOIN VidalVeterinarBundle:ProductCompany pc WITH pc.CompanyID = c
			LEFT JOIN VidalVeterinarBundle:Country country WITH c.CountryCode = country
			WHERE pc.ProductID IN (:productIds) AND
				pc.ItsMainCompany = 0
			ORDER BY pc.CompanyRusNote ASC
		')->setParameter('productIds', $productIds)
			->getResult();
	}

	public function findByProducts($productIds)
	{
		$companies = $this->_em->createQuery('
			SELECT c.CompanyID, pc.CompanyRusNote, pc.CompanyEngNote, c.LocalName, c.Property, c.Name,
				country.RusName Country, pc.ItsMainCompany, p.ProductID
			FROM VidalVeterinarBundle:Company c
			LEFT JOIN VidalVeterinarBundle:ProductCompany pc WITH pc.CompanyID = c
			LEFT JOIN VidalVeterinarBundle:Country country WITH c.CountryCode = country
			LEFT JOIN VidalVeterinarBundle:Product p WITH p = pc.ProductID
			WHERE pc.ProductID IN (:productIds)
			ORDER BY pc.ItsMainCompany DESC
		')->setParameter('productIds', $productIds)
			->getResult();

		$productCompanies = array();

		# надо получить компании и сгруппировать их по продукту
		foreach ($companies as $company) {
			$key = $company['ProductID'];
			isset($productCompanies[$key])
				? $productCompanies[$key][] = $company
				: $productCompanies[$key] = array($company);
		}

		return $productCompanies;
	}
}