<?php

namespace Vidal\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class BannerController extends Controller
{
	/**
	 * Добавить клик по банеру
	 * @Route("/banner-clicked/{bannerId}", name="banner_clicked", options={"expose"=true})
	 */
	public function bannerClickedAction($bannerId)
	{
		$this->getDoctrine()
			->getRepository('VidalMainBundle:Banner')
			->countClick($bannerId);

		return new Response();
	}

	public function renderAction($groupId)
	{
		return $this->render('VidalMainBundle:Banner:render.html.twig', array(
			'banner' => $this->getDoctrine()->getRepository('VidalMainBundle:Banner')->findByGroup($groupId)
		));
	}
}
