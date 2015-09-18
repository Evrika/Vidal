<?php

namespace Vidal\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Lsw\SecureControllerBundle\Annotation\Secure;
use Vidal\MainBundle\Entity\Digest;
use Vidal\MainBundle\Entity\Delivery;
use Vidal\MainBundle\Entity\DeliveryOpen;

class DeliveryController extends Controller
{
	/**
	 * Открыли письмо - записали в БД и вернули как бы картинку
	 * @Route("/delivery/opened/{deliveryName}/{userId}", name="delivery_opened")
	 */
	public function deliveryOpened($deliveryName, $userId)
	{
		$em       = $this->getDoctrine()->getManager();
		$delivery = $em->getRepository('VidalMainBundle:Delivery')->findOneByName($deliveryName);
		$user     = $em->getRepository('VidalMainBundle:User')->findOneById($userId);

		if (null == $delivery || null == $user) {
			throw $this->createNotFoundException();
		}

		$do = new DeliveryOpen();
		$do->setUser($user);
		$do->setDelivery($delivery);
		$em->persist($do);
		$em->flush();

		header('Content-Type: image/gif');
		echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
		die();
	}

	public function deliveriesAction()
	{
		$em         = $this->getDoctrine()->getManager();
		$deliveries = $em->getRepository('VidalMainBundle:Delivery')->findAll();

		return $this->render('VidalMainBundle:Delivery:deliveries.html.twig', array(
			'deliveries' => $deliveries,
		));
	}

	/**
	 * @Route("/delivery/add", name="delivery_add")
	 * @Template("VidalMainBundle:Delivery:add.html.twig")
	 * @Secure(roles="ROLE_SUPERADMIN")
	 */
	public function addAction()
	{
		$delivery = new Delivery();

		$form = $this->createFormBuilder()
			->add('name', 'null', array('label' => 'Код рассылки', 'requered' => true))
			->add('subject', 'null', array('label' => 'Заголовок письма', 'requered' => true))
			->add('template', 'null', array('label' => 'Шаблон письма', 'requered' => true))
			->getForm();

		return array(
			'form' => $form->createView()
		);
	}

	/**
	 * @Route("/delivery/preview", name="delivery_preview")
	 * @Template("VidalMainBundle:Digest:preview.html.twig")
	 * @Secure(roles="ROLE_SUPERADMIN")
	 */
	public function previewAction()
	{
		return array();
	}

	/**************************************************************************************************************/

	/**************************************************************************************************************/

	/**************************************************************************************************************/

	/**
	 * @Route("/delivery/reset", name="delivery_reset")
	 * @Secure(roles="ROLE_SUPERADMIN")
	 */
	public function deliveryResetAction()
	{
		$em     = $this->getDoctrine()->getManager();
		$digest = $em->getRepository('VidalMainBundle:Digest')->get();

		$em->createQuery('UPDATE VidalMainBundle:User u SET u.send=0 WHERE u.send=1')->execute();
		$digest->setProgress(false);
		$this->calculateDigest($em, $digest);

		$this->get('session')->getFlashBag()->add('msg', 'Сброшены разосланные флаги, рассылка остановлена');

		return $this->redirect($this->generateUrl('delivery_control'),301);
	}

	/**
	 * @Route("/delivery/stop", name="delivery_stop")
	 * @Secure(roles="ROLE_SUPERADMIN")
	 */
	public function deliveryStopAction()
	{
		$em = $this->getDoctrine()->getManager();
		$em->createQuery('UPDATE VidalMainBundle:Digest d SET d.progress = 0')->execute();

		$this->get('session')->getFlashBag()->add('msg', 'Рассылка остановлена');

		return $this->redirect($this->generateUrl('delivery_control'),301);
	}

	/**
	 * @Route("/delivery/start", name="delivery_start")
	 * @Secure(roles="ROLE_SUPERADMIN")
	 */
	public function deliveryStartAction()
	{
		$em = $this->getDoctrine()->getManager();

		$em->createQuery('UPDATE VidalMainBundle:Digest d SET d.progress = 1')->execute();
		$this->get('session')->getFlashBag()->add('msg', 'Рассылка запущена');

		# если команда уже не запущена, то запускаем на выполнение
		exec("/bin/ps -axw", $out);
		if (!preg_match('/vidal:digest --all/', implode(' ', $out))) {
			$cmd = 'nohup php ' . $this->get('kernel')->getRootDir() . '/console vidal:digest --all > /home/twigavid/c.log 2>&1 &';
			system($cmd);
		}

		return $this->redirect($this->generateUrl('delivery_control'),301);
	}

	/**
	 * @Route("/delivery/control", name="delivery_control")
	 * @Template("VidalMainBundle:Digest:delivery_control.html.twig")
	 * @Secure(roles="ROLE_SUPERADMIN")
	 */
	public function deliveryControlAction(Request $request)
	{
		$em     = $this->getDoctrine()->getManager();
		$digest = $em->getRepository('VidalMainBundle:Digest')->get();

		$form = $this->createFormBuilder($digest)
			->add('specialties', null, array('label' => 'Специальности', 'required' => false))
			->add('allSpecialties', null, array('label' => 'Всем специальностям', 'required' => false))
			->add('total', null, array('label' => 'Всего к отправке', 'required' => false, 'disabled' => true))
			->add('totalSend', null, array('label' => 'Уже отправлено', 'required' => false, 'disabled' => true))
			->add('totalLeft', null, array('label' => 'Осталось отправить', 'required' => false, 'disabled' => true))
			->add('limit', null, array('label' => 'Лимит писем', 'required' => false))
			->add('submit', 'submit', array('label' => 'Сохранить', 'attr' => array('class' => 'btn-red')))
			->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$this->calculateDigest($em, $digest);

			$em->flush();
			$this->get('session')->getFlashBag()->add('msg', 'Изменения сохранены');

			return $this->redirect($this->generateUrl('delivery_control'),301);
		}

		$params = array(
			'title'  => 'Рассылка - управление',
			'form'   => $form->createView(),
			'digest' => $digest,
		);

		return $params;
	}

	/**
	 * @Route("/delivery", name="delivery")
	 * @Template("VidalMainBundle:Digest:delivery.html.twig")
	 * @Secure(roles="ROLE_SUPERADMIN")
	 */
	public function deliveryAction(Request $request)
	{
		$em     = $this->getDoctrine()->getManager();
		$digest = $em->getRepository('VidalMainBundle:Digest')->get();

		$form = $this->createFormBuilder($digest)
			->add('text', null, array('label' => 'Текст письма', 'required' => true, 'attr' => array('class' => 'ckeditorfull')))
			->add('subject', null, array('label' => 'Тема письма', 'required' => true))
			->add('font', null, array('label' => 'Название шрифта без кавычек', 'required' => true))
			->add('emails', null, array('label' => 'Тестовые e-mail через ;', 'required' => false))
			->add('submit', 'submit', array('label' => 'Сохранить', 'attr' => array('class' => 'btn-red')))
			->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$em->flush();
			$this->get('session')->getFlashBag()->add('msg', 'Изменения сохранены');

			return $this->redirect($this->generateUrl('delivery'),301);
		}

		$params = array(
			'title'        => 'Рассылка - письмо',
			'digest'       => $digest,
			'form'         => $form->createView(),
			'total'        => $em->getRepository('VidalMainBundle:User')->total(),
			'subscribed'   => $em->getRepository('VidalMainBundle:Digest')->countSubscribed(),
			'unsubscribed' => $em->getRepository('VidalMainBundle:Digest')->countUnsubscribed(),
		);

		return $params;
	}

	/**
	 * @Route("/delivery/test", name="delivery_test")
	 * @Secure(roles="ROLE_SUPERADMIN")
	 */
	public function deliveryTestAction()
	{
		$em     = $this->getDoctrine()->getManager();
		$digest = $em->getRepository('VidalMainBundle:Digest')->get();

		$emails = explode(';', $digest->getEmails());
		$this->testTo($emails, $digest);
		$this->get('session')->getFlashBag()->add('msg', 'Было отправлено на адреса: ' . $digest->getEmails());

		return $this->redirect($this->generateUrl('delivery'),301);
	}

	private function testTo($emails, $digest)
	{
		$service = $this->get('email.service');
		$em      = $this->getDoctrine()->getManager();

		foreach ($emails as $email) {
			$email = trim($email);
			$user  = $em->getRepository('VidalMainBundle:User')->findOneByUsername($email);
			print_r($user);
			die();
			if ($user) {
				$service->send(
					$email,
					array('VidalMainBundle:Email:digest.html.twig', array('digest' => $digest, 'user' => $user)),
					$digest->getSubject()
				);
			}
		}
	}

	private function calculateDigest($em, $digest)
	{
		$specialties = $digest->getSpecialties();

		# считаем, сколько всего к отправке
		$qb = $em->createQueryBuilder();
		$qb->select("COUNT(u.id)")
			->from('VidalMainBundle:User', 'u')
			->andWhere('u.digestSubscribed = 1');

		if (count($specialties)) {
			$ids = array();
			foreach ($specialties as $specialty) {
				$ids[] = $specialty->getId();
			}
			$qb->andWhere('u.primarySpecialty IN (:ids) OR u.secondarySpecialty IN (:ids)')
				->setParameter('ids', $ids);
		}

		$total = $qb->getQuery()->getSingleScalarResult();
		$digest->setTotal($total);

		# считаем, сколько отправлено
		$totalSend = $em->createQuery('SELECT COUNT(u.id) FROM VidalMainBundle:User u WHERE u.send = 1')
			->getSingleScalarResult();
		$digest->setTotalSend($totalSend);

		# cчитаем, сколько осталось отправить
		$left = $digest->getTotal() - $digest->getTotalSend();
		$digest->setTotalLeft($left < 0 ? 0 : $left);

		$em->flush();
	}
}
