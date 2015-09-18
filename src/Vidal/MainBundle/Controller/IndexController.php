<?php
namespace Vidal\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Vidal\MainBundle\Entity\MapRegion;
use Vidal\MainBundle\Entity\MapCoord;
use Vidal\MainBundle\Entity\QuestionAnswer;
use Lsw\SecureControllerBundle\Annotation\Secure;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Vidal\MainBundle\Form\DataTransformer\CityToStringTransformer;
use Vidal\MainBundle\Entity\DigestOpened;

class IndexController extends Controller
{
	const PUBLICATIONS_SHOW = 4;

	/**
	 * @Route("/", name="index")
	 * @Template("VidalMainBundle:Index:index.html.twig")
	 */
	public function indexAction(Request $request)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$testMode = $request->query->has('test');
		$articles = $em->getRepository('VidalDrugBundle:Article')->findLast($testMode);

		$arts     = $em->getRepository('VidalDrugBundle:Art')->atIndex();
		$articles = array_merge($articles, $arts);

		$params = array(
			'indexPage'            => true,
			'seotitle'             => 'Справочник лекарственных препаратов Видаль. Описание лекарственных средств',
			'publications'         => $em->getRepository('VidalDrugBundle:Publication')->findLast(self::PUBLICATIONS_SHOW, $testMode),
			'publicationsPriority' => $em->getRepository('VidalDrugBundle:Publication')->findLastPriority(),
			'articles'             => $articles,
		);

		return $params;
	}

	/**
	 * Открыли письмо - записали в БД и вернули как бы картинку
	 * @Route("/digest_q/opened/{digestName}/{doctorId}")
	 */
	public function digestOpenedAction($digestName, $doctorId)
	{
		$em       = $this->getDoctrine()->getManager();
		$delivery = $em->getRepository('VidalMainBundle:Digest')->findOneBy(array('uniqueid' => $digestName));
		$doctor   = $em->getRepository('VidalMainBundle:User')->findOneById($doctorId);

		//$delivery->addDoctor($doctor);
		$do = new DigestOpened();
		$do->setUser($doctorId);
		$do->setUniqueid($digestName);
		$em->persist($do);
		$em->flush();


		$em->flush();

		$imagePath = $this->get('kernel')->getRootDir() . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR
			. 'web' . DIRECTORY_SEPARATOR . 'bundles' . DIRECTORY_SEPARATOR . 'vidalmain' . DIRECTORY_SEPARATOR
			. 'images' . DIRECTORY_SEPARATOR . 'delivery' . DIRECTORY_SEPARATOR . '1px.png';

		$file = readfile($imagePath);

		$headers = array(
			'Content-Type'        => 'image/png',
			'Content-Disposition' => 'inline; filename="1px.png"'
		);

		return new Response($file, 200, $headers);
	}

	/** @Route("/otvety_specialistov", name="qa_redirect") */
	public function redirectQa()
	{
		return $this->redirect($this->generateUrl('qa'), 301);
	}

	/**
	 * @Route("/otvety_farmakologov", name="qa")
	 * @Template("VidalMainBundle:Index:qa.html.twig")
	 */
	public function qaAction(Request $request)
	{
		$em                      = $this->getDoctrine()->getManager();
		$cityToStringTransformer = new CityToStringTransformer($em);
		$faq                     = new QuestionAnswer();

		if ($user = $this->getUser()) {
			$faq->setAuthorFirstName($user->getFirstname());
			$faq->setAuthorEmail($user->getUsername());
		}

		$builder = $this->createFormBuilder($faq);
		$builder
			->add('authorFirstName', null, array('label' => 'Ваше имя'))
			->add('authorEmail', null, array('label' => 'Ваш e-mail'))
			->add(
				$builder->create('city', 'text', array('label' => 'Город'))->addModelTransformer($cityToStringTransformer)
			)
			->add('question', null, array('label' => 'Вопрос'))
			->add('captcha', 'captcha', array('label' => 'Введите код с картинки'))
			->add('submit', 'submit', array('label' => 'Задать вопрос', 'attr' => array('class' => 'btn')));

		$form = $builder->getForm();
		$form->handleRequest($request);

		$t = 0;
		if ($request->isMethod('POST')) {
			$t = 1;
			if ($form->isValid()) {
				$t   = 2;
				$faq = $form->getData();

				$checkFaq = $em->getRepository('VidalMainBundle:QuestionAnswer')->findOneByQuestion($faq->getQuestion());
				if ($checkFaq) {
					return $this->redirect($this->generateUrl('qa_asked', array('id' => $checkFaq->getId())),301);
				}

				$faq->setEnabled(0);
				$em->persist($faq);
				$em->flush();
				$em->refresh($faq);

				$this->get('email.service')->send(
					array_diff($this->container->getParameter('manager_emails'), array('olesya2383@mail.ru.')),
					array('VidalMainBundle:Email:qa_question.html.twig', array('faq' => $faq)),
					'Вопрос на сайте vidal.ru'
				);

				return $this->redirect($this->generateUrl('qa_asked', array('id' => $faq->getId())),301);
			}
		}
		$qus          = $this->getDoctrine()->getRepository('VidalMainBundle:QuestionAnswer')->findByEnabled();
		$qaPagination = $this->get('knp_paginator')->paginate($qus, $request->query->get('p', 1), 10);

		return array(
			'title'           => 'Ответы фармакологов',
			'menu_left'       => 'qa',
			'questionAnswers' => $qus,
			'form'            => $form->createView(),
			't'               => $t,
			'qaPagination'    => $qaPagination
		);
	}

	/** @Route("/otvety_specialistov/asked/{id}", name="qa_asked_redirect") */
	public function redirectQaAsked($id)
	{
		return $this->redirect($this->generateUrl('qa_asked', array('id' => $id)), 301);
	}

	/**
	 * @Route("/otvety_farmakologov/asked/{id}", name="qa_asked")
	 * @Template("VidalMainBundle:Index:qa_asked.html.twig")
	 */
	public function qaAskedAction($id)
	{
		$qa = $this->getDoctrine()->getRepository('VidalMainBundle:QuestionAnswer')->findOneById($id);

		if (!$qa) {
			throw $this->createNotFoundException();
		}

		return array('qa' => $qa);
	}

	/**
	 * @Secure(roles="ROLE_QA")
	 * @Route("/otvety_specialistov_doctor/{party}", name="qa_admin", defaults={"party"="0"}, options={"expose"=true})
	 * @Template()
	 */
	public function doctorAnswerListAction(Request $request, $party = 0)
	{
		if ($this->getUser()->getConfirmation() == 1) {
			$parties = $this->getDoctrine()->getRepository('VidalMainBundle:QuestionAnswerPlace')->findAll();
			if ($party == 0) {
				$questions   = $this->getDoctrine()->getRepository('VidalMainBundle:QuestionAnswer')->findByAnswer(null);
				$thisPartyId = 0;
			}
			else {
				$party       = $this->getDoctrine()->getRepository('VidalMainBundle:QuestionAnswerPlace')->findOneById($party);
				$questions   = $this->getDoctrine()->getRepository('VidalMainBundle:QuestionAnswer')->findBy(array('answer' => null, 'place' => $party));
				$thisPartyId = $party->getId();
			}

			return array('questions' => $questions, 'parties' => $parties, 'thisPartyId' => $thisPartyId);
		}
		else {
			return $this->redirect($this->generateUrl('confirmation_doctor'),301);
		}
	}

	/**
	 * @Route("/confirmation-doctor", name="confirmation_doctor")
	 * @Secure(roles="ROLE_DOCTOR")
	 * @Template()
	 */
	public function confirmationDoctorAction(Request $request)
	{
		$user = $this->getUser();
		$scan = $user->getConfirmation();
		if ($scan == 0) {
			return $this->redirect($this->generateUrl('profile') . '#work',301);
		}
		else {
			return array();
		}

	}

	/**
	 * @Secure(roles="ROLE_QA")
	 * @Route("/otvety_farmakologov_doctor_edit/{faqId}", name="qa_admin_edit")
	 * @Template()
	 */
	public function doctorAnswerEditAction(Request $request, $faqId)
	{
		$em       = $this->getDoctrine()->getManager();
		$faq      = $em->getRepository('VidalMainBundle:QuestionAnswer')->findOneById($faqId);
		$question = $faq->getQuestion();

		if ($faq->getAnswer() == null) {
			$builder = $this->createFormBuilder($faq);
			$builder
				//				->add('question', null, array('label' => 'Вопрос', 'attr' => array('class' => 'ckeditor')))
				->add('answer', null, array('label' => 'Ответ', 'attr' => array('class' => 'ckeditor')))
				->add('submit', 'submit', array('label' => 'Сохранить', 'attr' => array('class' => 'btn')));

			$form = $builder->getForm();
		}
		else {
			return $this->redirect($this->generateUrl('qa_admin'),301);
		}

		$form->handleRequest($request);

		if ($form->isValid()) {
			$faq->setEnabled(true);
			$faq->setAnswerUser($this->getUser());

			if ($faq->getEmailSent() == false) {
				$this->get('email.service')->send(
					$faq->getAuthorEmail(),
					array('VidalMainBundle:Email:qa_answer.html.twig', array('faq' => $faq)),
					'Ответ специалиста на сайте vidal.ru'
				);

				$faq->setEmailSent(true);
			}

			$em->flush();

			return $this->redirect($this->generateUrl('qa_admin'),301);
		}

		return array('form' => $form->createView(), 'question' => $question);
	}

	/**
	 * Наши услуги
	 * @Route("/services", name="services")
	 * @Route("/Vidal/vidal-russia/", name="vidal_russia")
	 *
	 * @Template
	 */
	public function servicesAction()
	{
		$params = array(
			'title'     => 'Наши услуги',
			'menu_left' => 'services',
			'items'     => $this->getDoctrine()->getRepository('VidalMainBundle:AboutService')->findServices(),
		);

		return $params;
	}

	/**
	 * Наши услуги
	 * @Route("/services/{url}", name="services_item")
	 *
	 * @Template()
	 */
	public function servicesItemAction($url)
	{
		$about = $this->getDoctrine()->getRepository('VidalMainBundle:AboutService')->findOneByUrl($url);

		if (empty($about)) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'title'     => $about . ' | Наши услуги',
			'menu_left' => 'services',
			'about'     => $about,
		);

		return $params;
	}

	/**
	 * @Route("/kontakty-aptek", name="kontakty_aptek")
	 *
	 * @Template("VidalMainBundle:Index:kontaktyAptek.html.twig")
	 */
	public function kontaktyAptekAction()
	{
		return array('title' => 'Контакты аптек');
	}

	/**
	 * О компании
	 * @Route("/about/{url}", name="about_item")
	 *
	 * @Template()
	 */
	public function aboutItemAction($url)
	{
		$about = $this->getDoctrine()->getRepository('VidalMainBundle:About')->findOneByUrl($url);

		if (empty($about)) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'title'     => $about . ' | О компании',
			'menu_left' => 'about',
			'about'     => $about,
		);

		return $params;
	}

	/**
	 * О компании
	 * @Route("/about", name="about")
	 *
	 * @Template()
	 */
	public function aboutAction()
	{
		$em = $this->getDoctrine()->getManager();

		$params = array(
			'title'     => 'О компании',
			'menu_left' => 'about',
			'items'     => $this->getDoctrine()->getRepository('VidalMainBundle:About')->findAbout()
		);

		return $params;
	}

	/**
	 * Школа здоровья
	 *
	 * @Route("/shkola_zdorovya/")
	 * @Route("/shkola_zdorovya", name="shkola")
	 * @Template
	 */
	public function shkolaAction()
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$rubrique = $em->getRepository('VidalDrugBundle:ArticleRubrique')->findOneByRubrique('shkola-zdorovya');

		$params = array(
			'title'     => 'Школа здоровья',
			'menu_left' => 'shkola',
			'rubrique'  => $rubrique,
			'articles'  => $em->getRepository('VidalDrugBundle:Article')->findByRubriqueId($rubrique->getId()),
		);

		return $params;
	}

	/**
	 * Школа здоровья - статья
	 *
	 * @Route("/shkola_zdorovya/{link}.{ext}", name="shkola_article", defaults={"ext":null})
	 * @Template
	 */
	public function shkolaArticleAction($link, $ext)
	{
		if (!empty($ext)) {
			return $this->redirect($this->generateUrl('shkola_article', array('link' => $link)), 301);
		}

		$em      = $this->getDoctrine()->getManager('drug');
		$article = $em->getRepository('VidalDrugBundle:Article')->findOneByLink($link);

		if (empty($article)) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'title'     => $article->getTitle() . ' | Школа здоровья',
			'menu_left' => 'shkola',
			'article'   => $article,
		);

		return $params;
	}

	/**
	 * @Route("/module/{moduleId}", name="module")
	 *
	 * @Template("VidalMainBundle:Index:module.html.twig")
	 */
	public function moduleAction($moduleId)
	{
		$em     = $this->getDoctrine()->getManager();
		$module = $em->getRepository('VidalMainBundle:Module')->findOneById($moduleId);

		return array('module' => $module);
	}

	/**
	 * @Route("/pharmacies-map/{regionId}", name="pharmacies_map", options={"expose"=true})
	 * @Template("VidalMainBundle:Index:map2.html.twig")
	 */
	public function pharmaciesMapAction($regionId = 87)
	{
		$regions = $this->getDoctrine()->getRepository('VidalMainBundle:MapRegion')->findAll();

		return array(
			'title'    => 'Карта аптек',
			'menu'     => 'pharmacies_map',
			'regions'  => $regions,
			'regionId' => $regionId,
		);
	}

	/** @Route("/pharmacies-data/{regionId}", name="pharmacies_data", options={"expose"=true}) */
	public function pharmaciesDataAction($regionId)
	{
		$em = $this->getDoctrine()->getManager();

		return new JsonResponse(array(
			'region' => $em->getRepository('VidalMainBundle:MapRegion')->byRegion($regionId),
		));
	}

	/** @Route("/pharmacies-objects/{regionId}/{full}", name="pharmacies_objects", options={"expose"=true}) */
	public function pharmaciesObjectsAction($regionId, $full = false)
	{
		$em = $this->getDoctrine()->getManager();

		$data           = array();
		$data['region'] = $em->getRepository('VidalMainBundle:MapRegion')->byRegion($regionId);
		$data['coords'] = $em->getRepository('VidalMainBundle:MapCoord')->getObjects($full ? null : $regionId);

		return new JsonResponse($data);
	}

	/** @Route("/pharmacies-objects-rest/{regionId}", name="pharmacies_objects_rest", options={"expose"=true}) */
	public function pharmaciesObjectsRestAction($regionId)
	{
		$em = $this->getDoctrine()->getManager();

		return new JsonResponse(array(
			'coords' => $em->getRepository('VidalMainBundle:MapCoord')->getObjects($regionId),
		));
	}

	/** @Route("/pharmacies-region/{regionId}", name="pharmacies_region", options={"expose"=true}) */
	public function pharmaciesRegionAction($regionId)
	{
		$em   = $this->getDoctrine()->getManager();
		$data = $em->getRepository('VidalMainBundle:MapRegion')->byRegion($regionId);

		return new JsonResponse($data);
	}

	/**
	 * @Route("/pharmacies-map-ajax/{cityId}", name="pharmacies_map_ajax", options={"expose"=true})
	 * @Template("VidalMainBundle:Index:map_ajax.json.twig")
	 */
	public function ajaxmapAction($cityId)
	{
		$region = $this->getDoctrine()->getRepository('VidalMainBundle:MapRegion')->findOneById($cityId);
		$coords = $this->getDoctrine()->getRepository('VidalMainBundle:MapCoord')->findByRegion($region);

		return array('coords' => $coords);
	}

	/**
	 * @Route("/getMapHintContent/{id}", name="getMapHintContent", options={"expose"=true})
	 */
	public function getMapHintContentaction($id)
	{
		$em    = $this->getDoctrine()->getManager();
		$coord = $this->getDoctrine()->getRepository('VidalMainBundle:MapCoord')->findOneByOfferId($id);
		if ($coord->getTitle() == '' or $coord->getTitle() == null) {
			$html = @file_get_contents('http://apteka.ru/_action/DrugStore/getMapHintContent/' . $id . '/');
			$html = preg_replace('#<a.*>.*</a>#USi', '', $html);
			$coord->setTitle($html);
			$em->flush($coord);
		}
		else {
			$html = $coord->getTitle();
		}
		return new Response($html);
	}

	/**
	 * @Route("/getMapBalloonContent/{id}", name="getMapBalloonContent", options={"expose"=true})
	 */
	public function getMapBalloonContent($id)
	{
		$em    = $this->getDoctrine()->getManager();
		$coord = $this->getDoctrine()->getRepository('VidalMainBundle:MapCoord')->findOneByOfferId($id);
		if ($coord->getText() == '' or $coord->getText() == null) {
			$html = @file_get_contents('http://apteka.ru/_action/DrugStore/getMapBalloonContent/' . $id . '/');
			$html = preg_replace('/Аптека не относится к выбранному региону/', '', $html);
			$html = preg_replace('#<a.*>.*</a>#USi', '', $html);
			$coord->setText($html);
			$em->flush($coord);
		}
		else {
			$html = $coord->getTitle();
		}
		return new JsonResponse($html);
	}

	/**
	 * @Route("/sitemap", name="sitemap")
	 * @Template("VidalMainBundle:Sitemap:sitemap.html.twig")
	 */
	public function sitemapAction()
	{
		$params = array('title' => 'Карта сайта');
		$emDrug = $this->getDoctrine()->getManager('drug');
		$em     = $this->getDoctrine()->getManager();

		$params['articleRubriques'] = $emDrug->getRepository('VidalDrugBundle:ArticleRubrique')->findSitemap();
		$params['artRubriques']     = $emDrug->getRepository('VidalDrugBundle:ArtRubrique')->findSitemap();
		$params['abouts']           = $em->getRepository('VidalMainBundle:About')->findSitemap();
		$params['services']         = $em->getRepository('VidalMainBundle:AboutService')->findSitemap();

		return $params;
	}

	private function sortArticles($a, $b)
	{
		$dateA = $a->getDate();
		$dateB = $b->getDate();

		if ($dateA < $dateB) {
			return 1;
		}
		elseif ($dateA > $dateB) {
			return -1;
		}
		else {
			return 0;
		}
	}

	/**
	 * @Route("/first-set", name="first_set")
	 * @Template()
	 */
	public function firstSetAction()
	{
		$em       = $this->getDoctrine()->getManager();
		$firstset = false;
		if ($this->getUser()) {
			$user = $em->getRepository('VidalMainBundle:User')->find($this->getUser()->getId());
			if ($user == null || $user->getFirstset() == false) {
				$firstset = false;
			}
			else {
				$user->setFirstset(true);
				$em->flush($user);
				$firstset = true;
			}
		}
		return array('firstset' => $firstset);
	}

	/**
	 * @Route("/profile/{userId}", name="profile_user")
	 * @Template()
	 */
	public function profileAction($userId)
	{
		$user = $this->getDoctrine()->getRepository('VidalMainBundle:User')->find($userId);

		return array('user' => $user);
	}

	/**
	 * @Route("/vebinar-pulmo", name="vebinar-pulmo")
	 * @Template()
	 */
	public function vebinarAction()
	{
		return Array();
	}
}
