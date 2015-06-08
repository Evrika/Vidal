<?php

namespace Vidal\MainBundle\Entity;

use Doctrine\ORM\EntityRepository;

class DigestRepository extends EntityRepository
{
	public function get()
	{
		$digest = $this->_em->createQuery('
		 	SELECT d
		 	FROM VidalMainBundle:Digest d
		')->setMaxResults(1)
			->getOneOrNullResult();

		if (!$digest) {
			$digest = new Digest();
			$digest->setSubject('Тема письма');
			$digest->setText('<p>Текст письма</p>');
			$this->_em->persist($digest);
			$this->_em->flush($digest);
			$this->_em->refresh($digest);
		}

		return $digest;
	}

	public function countSubscribed()
	{
		return $this->_em->createQuery('
			SELECT COUNT(d.id)
			FROM VidalMainBundle:User d
			WHERE d.digestSubscribed = TRUE
				AND d.enabled = TRUE
		')->getSingleScalarResult();
	}

	public function countUnsubscribed()
	{
		$intervals = array('day', 'week', 'month', 'year');
		$unsub     = array();

		foreach ($intervals as $interval) {
			$unsub[$interval] = $this->_em->createQuery("
				SELECT COUNT(u.id)
				FROM VidalMainBundle:User u
				WHERE u.digestSubscribed = FALSE
					AND u.enabled = TRUE
					AND u.emailConfirmed = TRUE
					AND u.digestUnsubscribed > '" . date('Y-m-d', strtotime("-1 $interval")) . "'"
			)->getSingleScalarResult();
		}

		return $unsub;
	}
}