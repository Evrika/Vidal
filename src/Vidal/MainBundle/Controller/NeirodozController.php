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

class NeirodozController extends Controller
{
    /**
     * @Route("/neiro_doz", name="neirodoz")
     * @Template("VidalMainBundle:NeiroDoz:home.html.twig")
     */
    public function numb11erAction()
    {
        $params = array();
        return $params;
    }
}