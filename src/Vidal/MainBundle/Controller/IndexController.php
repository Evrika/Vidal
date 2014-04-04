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

class IndexController extends Controller
{
	const PUBLICATIONS_SHOW = 4;
	const PUBLICATIONS_LOAD = 4;
	const ARTICLES_SHOW     = 4;
	const ARTICLES_LOAD     = 4;

	/**
	 * @Route("/", name="index")
	 * @Template()
	 */
	public function indexAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager('drug');

		$params = array(
			'indexPage'    => true,
			'seotitle'     => 'Справочник лекарственных препаратов Видаль. Описание лекарственных средств',
			'publications' => $em->getRepository('VidalDrugBundle:Publication')->findLast(self::PUBLICATIONS_SHOW),
			'articles'     => $em->getRepository('VidalDrugBundle:Article')->findLast(self::ARTICLES_SHOW),
		);

		return $params;
	}

	/**
	 * [AJAX] Подгрузка еще нескольких статей на главную
	 * @Route("/ajax-articles/{from}", name="ajax_articles", options={"expose":true})
	 */
	public function ajaxArticlesAction($from)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$articles = $em->getRepository('VidalDrugBundle:Article')->findFrom($from, self::ARTICLES_LOAD);
		$html     = $this->renderView('VidalMainBundle:Article:ajax_articles.html.twig', array('articles' => $articles));

		return new JsonResponse($html);
	}

	/**
	 * [AJAX] Подгрузка еще нескольких новостей на главную
	 * @Route("/ajax-news/{from}", name="ajax_news", options={"expose":true})
	 */
	public function ajaxNewsAction($from)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$news = $em->getRepository('VidalDrugBundle:Publication')->findFrom($from, self::PUBLICATIONS_LOAD);
		$html     = $this->renderView('VidalMainBundle:Article:ajax_news.html.twig', array('news' => $news));

		return new JsonResponse($html);
	}

	/**
	 * @Route("/otvety_specialistov", name="qa")
	 * @Template()
	 */
	public function qaAction()
	{
		return array(
			'title'           => 'Вопрос-ответ',
			'menu_left'       => 'qa',
			'questionAnswers' => $this->getDoctrine()->getRepository('VidalMainBundle:QuestionAnswer')->findAll(),
		);
	}

	/**
	 * @Route("/pharmacies-map/{id}", name="pharmacies_map", defaults = { "id" = 87 })
	 * @Template("VidalMainBundle:Index:map.html.twig")
	 */
	public function pharmaciesMapAction($id = 87)
	{
        $cities = $this->getDoctrine()->getRepository('VidalMainBundle:MapRegion')->findAll();
        $thisCities = $this->getDoctrine()->getRepository('VidalMainBundle:MapRegion')->findOneById($id);

		return array('cities' => $cities, 'thisCity' => $thisCities);
	}

    /**
     * @Route("/pharmacies-map-ajax", name="pharmacies_map_ajax", options={"expose"=true})
     * @Template("VidalMainBundle:Index:map_ajax.json.twig")
     */
    public function ajaxmapAction(){


        return array();
    }

    /**
     * @Route("/getMapHintContent/{id}", name="getMapHintContent", options={"expose"=true})
     */
    public function getMapHintContentaction($id){
        $html = @file_get_contents('http://apteka.ru/_action/DrugStore/getMapHintContent/'.$id.'/');
        $html = preg_replace('#<a.*>.*</a>#USi', '', $html);
        return new Response($html);
    }
    /**
     * @Route("/getMapBalloonContent/{id}", name="getMapBalloonContent", options={"expose"=true})
     */
    public function getMapBalloonContent($id){
        $html = @file_get_contents('http://apteka.ru/_action/DrugStore/getMapBalloonContent/'.$id.'/');
//        $html = preg_replace('Аптека не относится к выбранному региону', '', $html);
        $html = preg_replace('#<a.*>.*</a>#USi', '', $html);
        return new Response($html);
    }


}
