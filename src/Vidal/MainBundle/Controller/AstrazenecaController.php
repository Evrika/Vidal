<?php
namespace Vidal\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\NotBlank;
use Vidal\MainBundle\Entity\AstrazenecaFaq;
use Vidal\MainBundle\Entity\AstrazenecaRegion;
use Vidal\MainBundle\Entity\AstrazenecaMap;
use Lsw\SecureControllerBundle\Annotation\Secure;

class AstrazenecaController extends Controller
{
	/**
	 * @Route("/shkola-gastrita", name="shkola_gastrita")
	 * @Template("VidalMainBundle:Astrazeneca:shkola.html.twig")
	 */
	public function shkolaAction(Request $request)
	{
		$params = array(
			'noYad'     => true,
			'title'     => 'Школа гастрита',
			'menu_left' => 'shkola',
		);

		$em                        = $this->getDoctrine()->getManager();
		$params['blogs']           = $em->getRepository('VidalMainBundle:AstrazenecaBlog')->findActive();
		$params['articles']        = $em->getRepository('VidalMainBundle:AstrazenecaNew')->findActive();
		$params['tests']           = $em->getRepository('VidalMainBundle:AstrazenecaTest')->findAll();
		$params['questionAnswers'] = $em->getRepository('VidalMainBundle:AstrazenecaFaq')->findActive();

		# форма задать вопрос
		$faq = new AstrazenecaFaq();

		$builder = $this->createFormBuilder($faq);
		$builder
			->add('authorFirstName', null, array('label' => 'Ваше имя', 'required' => true, 'constraints' => new NotBlank(array('message' => "Пожалуйста, укажите Имя"))))
			->add('authorEmail', null, array('label' => 'Ваш e-mail', 'required' => true, 'constraints' => new NotBlank(array('message' => "Пожалуйста, укажите Email"))))
			->add('question', null, array('label' => 'Вопрос', 'attr' => array('class' => 'ckeditor')))
			->add('captcha', 'captcha', array('label' => 'Введите код с картинки'))
			->add('submit', 'submit', array('label' => 'ОТПРАВИТЬ', 'attr' => array('class' => 'btn')));

		$form = $builder->getForm();
		$form->handleRequest($request);

		if ($request->isMethod('POST')) {
			if ($form->isValid()) {
				$faq = $form->getData();
				$faq->setEnabled(0);
				$em->persist($faq);
				$em->flush();

				$this->get('session')->getFlashBag()->add('questioned', '');

				return $this->redirect($this->generateUrl('shkola_gastrita') . '#qa',301);
			}
		}

		$params['form'] = $form->createView();

		return $params;
	}

	/** @Template("VidalMainBundle:Astrazeneca:menu.html.twig") */
	public function menuAction($request)
	{
		$em         = $this->getDoctrine()->getManager();
		$categories = $em->getRepository('VidalMainBundle:ShkolaCategory')->findAll();

		return array(
			'categories' => $categories,
			'request'    => $request
		);
	}

	/**
	 * @Route("/shkola-gastrita/online-test", name="shkola_test")
	 * @Template("VidalMainBundle:Astrazeneca:shkola_test.html.twig")
	 */
	public function testAction()
	{
		$params = array(
			'seotitle'    => 'Онлайн тест на наличие язвы желудка или гастрита | Vidal.ru/shkola-gastrita',
			'description' => 'Пройти онлайн тестирование на наличие язвы желудка или гастрита.',
			'keywords'    => 'онлайн тест язва гастрит',
			'tests'       => $this->getDoctrine()->getManager()->getRepository('VidalMainBundle:AstrazenecaTest')->findAll(),
		);

		return $params;
	}

	/**
	 * @Route("/shkola-gastrita/besplatnaya-konsultaciya-gastroenterologa", name="shkola_consult")
	 * @Template("VidalMainBundle:Astrazeneca:shkola_consult.html.twig")
	 */
	public function consultAction(Request $request)
	{
		$params = array(
			'seotitle'    => 'Бесплатная консультация гастроэнтеролога | Vidal.ru/shkola-gastrita',
			'description' => 'Бесплатные консультации врача гастроэнтеролога по вопросам язвы желудка и гастрита.',
			'keywords'    => 'бесплатная консультация гастроэнтеролог',
		);

		# форма задать вопрос
		$em  = $this->getDoctrine()->getManager();
		$faq = new AstrazenecaFaq();

		$builder = $this->createFormBuilder($faq);
		$builder
			->add('authorFirstName', null, array('label' => 'Ваше имя', 'required' => true, 'constraints' => new NotBlank(array('message' => "Пожалуйста, укажите Имя"))))
			->add('authorEmail', null, array('label' => 'Ваш e-mail', 'required' => true, 'constraints' => new NotBlank(array('message' => "Пожалуйста, укажите Email"))))
			->add('question', null, array('label' => 'Вопрос', 'attr' => array('class' => 'ckeditor')))
			->add('captcha', 'captcha', array('label' => 'Введите код с картинки'))
			->add('submit', 'submit', array('label' => 'ОТПРАВИТЬ', 'attr' => array('class' => 'btn')));

		$form = $builder->getForm();
		$form->handleRequest($request);

		if ($request->isMethod('POST')) {
			if ($form->isValid()) {
				$faq = $form->getData();
				$faq->setEnabled(0);
				$em->persist($faq);
				$em->flush();

				$this->get('session')->getFlashBag()->add('questioned', '');

				return $this->redirect($this->generateUrl('shkola_consult'),301);
			}
		}

		$params['form']            = $form->createView();
		$params['questionAnswers'] = $em->getRepository('VidalMainBundle:AstrazenecaFaq')->findByEnabled(1);

		return $params;
	}

	/**
	 * @Route("/shkola-gastrita/video", name="shkola_video")
	 * @Template("VidalMainBundle:Astrazeneca:shkola_video.html.twig")
	 */
	public function videoAction()
	{
		$params = array(
			'seotitle'    => 'Видео по гастриту и язве желудка | Vidal.ru/shkola-gastrita',
			'description' => 'Полезные видео по гастриту и язве желудка.',
			'keywords'    => 'видео гастрит язва',
		);

		return $params;
	}

	/**
	 * @Route("/shkola-gastrita/blizhajshie-polikliniki", name="shkola_maps")
	 * @Template("VidalMainBundle:Astrazeneca:shkola_maps.html.twig")
	 */
	public function mapsAction()
	{
		$params = array(
			'seotitle'    => 'Карта ближайших поликлиник | Vidal.ru/shkola-gastrita',
			'description' => 'Интерактивная карта ближайших поликлиник в Москве.',
			'keywords'    => 'карта поликлиник москва',
		);

		return $params;
	}

	/**
	 * @Route("/shkola-gastrita-map", name="shkola_map")
	 * @Template("VidalMainBundle:Astrazeneca:frame_map.html.twig")
	 */
	public function frameMapAction()
	{
		return array();
	}

	/** @Route("/shkola-gastrita/video", name="astrazeneca_video") */
	public function videoRedirectAction()
	{
		return $this->redirect($this->generateUrl('shkola_gastrita'), 301);
	}

	/** @Route("/shkola-gastrita/articles", name="astrazeneca_news") */
	public function newsRedirectAction(Request $request)
	{
		return $this->redirect($this->generateUrl('shkola_gastrita'), 301);
	}

	/** @Route("/shkola-gastrita/article/{newId}", name="astrazeneca_new") */
	public function showRedirectNewAction($newId)
	{
		return $this->redirect($this->generateUrl('shkola_gastrita'), 301);
	}

	/** @Route("/shkola-gastrita/map", name="astrazeneca_map") */
	public function mapRedirectAction()
	{
		return $this->redirect($this->generateUrl('shkola_gastrita'), 301);
	}

	/**
	 * @Route("/shkola-gastrita/map-ajax", name="astrazeneca_map_xml", options={"expose"=true})
	 * @Template("VidalMainBundle:Astrazeneca:map_xml.html.twig")
	 */
	public function mapXmlAction()
	{
		$coords[0] = $this->getRequest()->query->get('x1');
		$coords[1] = $this->getRequest()->query->get('y1');
		$coords[2] = $this->getRequest()->query->get('x2');
		$coords[3] = $this->getRequest()->query->get('y2');
		$zoom      = $this->getRequest()->query->get('z');

		if ($zoom <= 5) {
			$coords = $this->getDoctrine()->getRepository('VidalMainBundle:AstrazenecaRegion')->findAll();
		}
		else {
			$coords = $this->getDoctrine()->getRepository('VidalMainBundle:AstrazenecaMap')->findCoords($coords);
		}

		return array(
			'coords'    => $coords,
			'noYad'     => true,
			'menu_left' => 'shkola',
		);
	}

	/**
	 * @Route("/shkola-gastrita/testing", name="astrazeneca_testing")
	 * @Template("VidalMainBundle:Astrazeneca:test.html.twig")
	 */
	public function testingAction(Request $request)
	{

		$tests = $this->getDoctrine()->getRepository('VidalMainBundle:AstrazenecaTest')->findAll();

		return array(
			'tests'     => $tests,
			'noYad'     => true,
			'title'     => 'Тестирование | Школа гастрита',
			'menu_left' => 'shkola',
		);
	}

	/** @Route("/shkola-gastrita/sitemap.xml", name="shkola_sitemap") */
	public function sitemapAction()
	{
		$file     = $this->get('kernel')->getRootDir() . '/../web/sitemap3.xml';
		$xml      = file_get_contents($file);
		$response = new Response($xml);
		$response->headers->set('Content-Type', 'xml');

		return $response;
	}

	/**
	 * @Route("/shkola-gastrita/testing-ajax/{step}", name="astrazeneca_testing_ajax", options={"expose"=true})
	 */
	public function testingAjaxAction(Request $request, $step)
	{

		$question = $this->getDoctrine()->getRepository('VidalMainBundle:AstrazenecaTest')->findAll();
		if (isset($question[$step - 1])) {
			$question = $question[$step - 1];
		}
		else {
			$question = null;
		}

		return new Response($question->getTitle());
	}

	/**
	 * @Route("/shkola-gastrita/faq", name="astrazeneca_faq")
	 * @Template("VidalMainBundle:Astrazeneca:faq.html.twig")
	 */
	public function faqAction(Request $request)
	{
		$em  = $this->getDoctrine()->getManager();
		$faq = new AstrazenecaFaq();

		$builder = $this->createFormBuilder($faq);
		$builder
			->add('authorFirstName', null, array('label' => 'Ваше имя'))
			->add('authorEmail', null, array('label' => 'Ваш e-mail'))
			->add('question', null, array('label' => 'Вопрос', 'attr' => array('class' => 'ckeditor')))
			->add('captcha', 'captcha', array('label' => 'Введите код с картинки'))
			->add('submit', 'submit', array('label' => 'Задать вопрос', 'attr' => array('class' => 'btn')));

		$form = $builder->getForm();
		$form->handleRequest($request);

		$builder = $this->createFormBuilder($faq);
		$builder
			->add('authorFirstName', null, array('label' => 'Ваше имя', 'required' => true, 'constraints' => new NotBlank(array('message' => "Пожалуйста, укажите Имя"))))
			->add('authorEmail', null, array('label' => 'Ваш e-mail', 'required' => true, 'constraints' => new NotBlank(array('message' => "Пожалуйста, укажите Email"))))
			->add('question', null, array('label' => 'Вопрос', 'attr' => array('class' => 'ckeditor')))
			->add('captcha', 'captcha', array('label' => 'Введите код с картинки'))
			->add('submit', 'submit', array('label' => 'Отправить', 'attr' => array('class' => 'btn')));
		if ($request->isMethod('POST')) {
			if ($form->isValid()) {
				$faq = $form->getData();
				$faq->setEnabled(0);
				$em->persist($faq);
				$em->flush();
				$em->refresh($faq);
			}
		}

		return array(
			'title'           => 'Вопрос-ответ | Школа гастрита',
			'questionAnswers' => $this->getDoctrine()->getRepository('VidalMainBundle:AstrazenecaFaq')->findByEnabled(1),
			'form'            => $form->createView(),
			'noYad'           => true,
			'menu_left'       => 'shkola',
		);
	}

	/**
	 * @Secure(roles="ROLE_SHKOLA")
	 * @Route("/shkola-gastrita-admin", name="shkola_faq_list")
	 * @Template("VidalMainBundle:Astrazeneca:shkola_faq_list.html.twig")
	 */
	public function shkolaFaqListAction(Request $request)
	{
		$em      = $this->getDoctrine()->getManager();
		$perPage = 10;
		$params  = array(
			'noYad'     => true,
			'title'     => 'Вопрос-ответ | Школа гастрита',
			'menu_left' => 'shkola',
		);

		$params['pagination'] = $this->get('knp_paginator')->paginate(
			$em->getRepository('VidalMainBundle:AstrazenecaFaq')->findAll(),
			$request->query->get('p', 1),
			$perPage
		);

		return $params;
	}

	/**
	 * @Secure(roles="ROLE_SHKOLA")
	 * @Route("/shkola-gastrita-admin/faq/{id}", name="shkola_faq_edit")
	 * @Template("VidalMainBundle:Astrazeneca:shkola_faq_edit.html.twig")
	 */
	public function shkolaFaqEditAction(Request $request, $id)
	{
		$em  = $this->getDoctrine()->getManager();
		$faq = $em->getRepository('VidalMainBundle:AstrazenecaFaq')->findOneById($id);

		$builder = $this->createFormBuilder($faq);
		$builder
			->add('question', null, array('label' => 'Вопрос'))
			->add('answer', null, array('label' => 'Ответ', 'attr' => array('class' => 'ckeditormini')))
			->add('submit', 'submit', array('label' => 'Сохранить', 'attr' => array('class' => 'btn')));

		$form = $builder->getForm();
		$form->handleRequest($request);

		if ($request->isMethod('POST')) {
			if ($form->isValid()) {
				$faq = $form->getData();
				$em->flush($faq);
			}
		}

		return array(
			'form'      => $form->createView(),
			'noYad'     => true,
			'title'     => 'Редактировать Вопрос-ответ | Школа гастрита',
			'menu_left' => 'shkola',
		);
	}

	/**
	 * @Secure(roles="ROLE_SHKOLA")
	 * @Route("/shkola-gastrita-admin/faq/delete/{id}", name="shkola_faq_delete")
	 */
	public function shkolaFaqDeleteAction($id)
	{
		$em  = $this->getDoctrine()->getManager();
		$faq = $em->getRepository('VidalMainBundle:AstrazenecaFaq')->findOneById($id);

		$em->remove($faq);
		$em->flush();

		return $this->redirect($this->generateUrl('shkola_faq_list'),301);
	}

	/**
	 * @Route("/shkola-gastrita/zgetMapHintContent/{id}", name="zgetMapHintContent", options={"expose"=true})
	 */
	public function getMapHintContentaction($id)
	{
		$coord = $this->getDoctrine()->getRepository('VidalMainBundle:AstrazenecaMap')->findOneById($id);
		$html  = $coord->getTitle();
		return new Response($html);
	}

	/**
	 * @Route("/shkola-gastrita/zgetMapBalloonContent/{id}", name="zgetMapBalloonContent", options={"expose"=true})
	 */
	public function getMapBalloonContent($id)
	{
		$coord = $this->getDoctrine()->getRepository('VidalMainBundle:AstrazenecaMap')->findOneById($id);
		$html  = $coord->getAdr();

		return new Response($html);
	}

	/**
	 * @Route("/shkola-gastrita/{category}", name="shkola_category")
	 * @Template("VidalMainBundle:Astrazeneca:shkola_category.html.twig")
	 */
	public function categoryAction($category)
	{
		$em       = $this->getDoctrine()->getManager();
		$category = $em->getRepository('VidalMainBundle:ShkolaCategory')->findOneByUrl($category);

		if (!$category || !$category->getEnabled()) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'seotitle'    => $category->getTitle(),
			'description' => $category->getDescription(),
			'keywords'    => $category->getKeywords(),
			'category'    => $category,
		);

		return $params;
	}

	/**
	 * @Route("/shkola-gastrita/{category}/{article}", name="shkola_category_article")
	 * @Template("VidalMainBundle:Astrazeneca:shkola_category_article.html.twig")
	 */
	public function articleAction($category, $article)
	{
		$em       = $this->getDoctrine()->getManager();
		$category = $em->getRepository('VidalMainBundle:ShkolaCategory')->findOneByUrl($category);
		$article  = $em->getRepository('VidalMainBundle:ShkolaArticle')->findOneByUrl($article);

		if (!$category || !$category->getEnabled() || !$article || !$article->getEnabled()) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'category'    => $category,
			'article'     => $article,
			'seotitle'    => $article->getTitle(),
			'description' => $article->getDescription(),
			'keywords'    => $article->getKeywords(),
		);

		return $params;
	}
}