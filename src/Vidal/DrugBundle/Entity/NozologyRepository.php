<?php

namespace Vidal\DrugBundle\Entity;

use Doctrine\ORM\EntityRepository;

class NozologyRepository extends EntityRepository
{
	public function findByCode($code)
	{
		return $this->_em->createQuery('
			SELECT n.NozologyCode, n.Code, n.Name
			FROM VidalDrugBundle:Nozology n
			WHERE n.Code = :code
		')->setParameter('code', $code)
			->getOneOrNullResult();
	}

	public function findOneByNozologyCode($NozologyCode)
	{
		return $this->_em->createQuery('
			SELECT n
			FROM VidalDrugBundle:Nozology n
			WHERE n.NozologyCode = :NozologyCode
		')->setParameter('NozologyCode', $NozologyCode)
			->getOneOrNullResult();
	}

	public function findOneByCode($code)
	{
		$code = trim($code, ' ');

		$result = $this->_em->createQuery('
			SELECT n
			FROM VidalDrugBundle:Nozology n
			WHERE n.Code = :code
		')->setMaxResults(1)->setParameter('code', $code)
			->getOneOrNullResult();

		if (!$result) {
			$result = $this->_em->createQuery('
				SELECT n
				FROM VidalDrugBundle:Nozology n
				WHERE n.NozologyCode = :code
			')->setMaxResults(1)->setParameter('code', $code)
				->getOneOrNullResult();
		}

		return $result;
	}

	public function findByLetter($l)
	{
		return $this->_em->createQuery('
		 	SELECT n
		 	FROM VidalDrugBundle:Nozology n
		 	WHERE n.NozologyCode LIKE :l
		 ')->setParameter('l', $l . '%')
			->getResult();
	}

	public function findNozologyNames()
	{
		$names = array();

		$namesRaw = $this->_em->createQuery('
			SELECT n.Name
			FROM VidalDrugBundle:Nozology n
			ORDER BY n.Name ASC
		')->getResult();

		for ($i = 0, $c = count($namesRaw); $i < $c; $i++) {
			$names[] = mb_strtolower($namesRaw[$i]['Name'], 'UTF-8');
		}

		return $names;
	}

	public function findByQuery($q)
	{
		$qb = $this->_em->createQueryBuilder();

		$qb->select('DISTINCT n.Code, n.Name')
			->from('VidalDrugBundle:Nozology', 'n')
			->orderBy('n.Name', 'ASC');

		# поиск по словам
		$where = '';
		$words = explode(' ', $q);

		# находим все слова
		for ($i = 0; $i < count($words); $i++) {
			$word = $words[$i];
			if ($i > 0) {
				$where .= ' AND ';
			}
			$where .= "(n.Name LIKE '$word%' OR n.Name LIKE '% $word%')";
		}

		$qb->where($where);
		$nozologies = $qb->getQuery()->getResult();

		# находим какое-либо из слов, если нет результата
		if (empty($nozologies)) {
			foreach ($words as $word) {
				if (mb_strlen($word, 'utf-8') < 3) {
					return array();
				}
			}

			$where = '';
			for ($i = 0; $i < count($words); $i++) {
				$word = $words[$i];
				if ($i > 0) {
					$where .= ' OR ';
				}
				$where .= "(n.Name LIKE '$word%' OR n.Name LIKE '% $word%')";
			}

			$qb->where($where);
			$nozologies = $qb->getQuery()->getResult();
		}

		for ($i = 0, $c = count($nozologies); $i < $c; $i++) {
			$nozologies[$i]['Name'] = preg_replace('/' . $q . '/iu', '<span class="query">$0</span>', $nozologies[$i]['Name']);
		}

		return $nozologies;
	}

	public function findByDocumentId($DocumentID)
	{
		return $this->_em->createQuery('
			SELECT DISTINCT n.NozologyCode, n.Code, n.Name
			FROM VidalDrugBundle:Nozology n
			JOIN n.documents d WITH d = :DocumentID
			ORDER BY n.Name ASC
		')->setParameter('DocumentID', $DocumentID)
			->getResult();
	}

	public function findByCodes($nozologyCodes)
	{
		return $this->_em->createQuery('
		 	SELECT DISTINCT n.NozologyCode, n.Name
		 	FROM VidalDrugBundle:Nozology n
		 	WHERE n.NozologyCode IN (:nozologyCodes)
		')->setParameter('nozologyCodes', $nozologyCodes)
			->getResult();
	}

	public function findForTree()
	{
		return $this->_em->createQuery('
			SELECT n.Code id, n.Name text
			FROM VidalDrugBundle:Nozology n
			WHERE n.Level = 0
			ORDER BY n.NozologyCode
		')->getResult();
	}

	public function countProducts()
	{
		return $this->_em->createQuery('
			SELECT COUNT(DISTINCT p.ProductID) as countProducts, n.Code
			FROM VidalDrugBundle:Product p
			JOIN p.document d
			JOIN d.nozologies n
			WHERE p.MarketStatusID IN (1,2,7)
				AND p.ProductTypeCode IN (\'DRUG\',\'GOME\')
				AND p.inactive = FALSE
			GROUP BY n.Code
		')->getResult();
	}

	public function jsonForTree()
	{
		$raw = $this->_em->createQuery('
			SELECT n.Code id, n.Name text, n.Level, n.countProducts, n.NozologyCode nc
			FROM VidalDrugBundle:Nozology n
			ORDER BY n.NozologyCode2
		')->getResult();

		$nozologies = array();

		foreach ($raw as $nozology) {
			$key              = $nozology['id'];
			$nozologies[$key] = $nozology;
		}

		return $nozologies;
	}

	public function getOptions()
	{
		$raw = $this->_em->createQuery('
			SELECT n.NozologyCode, n.Name
			FROM VidalDrugBundle:Nozology n
		 	ORDER BY n.NozologyCode ASC
		 ')->getResult();

		$items = array();

		foreach ($raw as $r) {
			$items[] = array(
				'id'    => $r['NozologyCode'],
				'title' => $r['NozologyCode'] . ' - ' . $r['Name']
			);
		}

		return $items;
	}

	public function getChoices()
	{
		$raw = $this->_em->createQuery('
			SELECT n.NozologyCode, n.Name
			FROM VidalDrugBundle:Nozology n
		 	ORDER BY n.NozologyCode ASC
		 ')->getResult();

		$items = array();

		foreach ($raw as $r) {
			$key         = $r['NozologyCode'];
			$items[$key] = $r['NozologyCode'] . ' - ' . $r['Name'];
		}

		return $items;
	}

	public function adminAutocomplete($term)
	{
		$codes = $this->_em->createQuery('
			SELECT n.NozologyCode, n.Code, n.Name
			FROM VidalDrugBundle:Nozology n
			WHERE n.Code LIKE :code
				OR n.Name LIKE :name
			ORDER BY n.NozologyCode ASC
		')->setParameter('code', $term . '%')
			->setParameter('name', '%' . $term . '%')
			->setMaxResults(15)
			->getResult();

		$data = array();

		foreach ($codes as $code) {
			$data[] = array(
				'id'   => $code['NozologyCode'],
				'text' => $code['Code'] . ' - ' . $code['Name']
			);
		}

		return $data;
	}
}