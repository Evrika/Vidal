<?php

namespace Vidal\MainBundle\Entity;

use Doctrine\ORM\EntityRepository;

class DeliveryRepository extends EntityRepository
{
	public function getOrCreate($deliveryName)
	{
		$delivery = $this->_em->createQuery('SELECT d FROM EvrikaMainBundle:Delivery d WHERE d.name = :deliveryName')
			->setParameter('deliveryName', $deliveryName)
			->getOneOrNullResult();

		if (null == $delivery) {
			$delivery = new Delivery;
			$delivery->setName($deliveryName);
			$this->_em->persist($delivery);
			$this->_em->flush($delivery);
			$this->_em->refresh($delivery);
		}

		return $delivery;
	}
}