<?php

namespace Vidal\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Vidal\MainBundle\Entity\Appointment;
use Vidal\MainBundle\Appointment\AppSoap;

class AppointmentController extends Controller
{

	# Если пользователь авторизован возвращает TRUE, иначе FALSE
	protected function isAuth()
	{

		$session        = new Session();
		$emiasBirthdate = $session->get('EmiasBirthdate');
		$emiasOms       = $session->get('EmiasOms');

		if ($emiasBirthdate == null || $emiasOms == null) {
			return false;
		}
		else {
			return true;
		}
	}

	/**
	 * @Route("/appointment", name="appointment")
	 * @Template()
	 */
	public function appointmentAction()
	{
		return array(
			'title' => 'Запись на прием к врачу',
			'noYad' => true,
		);
	}

	/**
	 * @Route("/appointment12345", name="")
	 * @Template()
	 */
	public function indexAction(Request $request)
	{
		if ($this->isAuth()) {
			$soap        = $this->createConnection();
			$specialties = $soap->getSpecialitiesInfo(array('omsNumber' => '9988889785000068', 'birthDate' => '2011-04-14T00:00:00', 'externalSystemId' => 'MPGU'));

			$apps = $soap->getAppointmentReceptionsByPatient(array('omsNumber' => '9988889785000068', 'birthDate' => '2011-04-14T00:00:00', 'externalSystemId' => 'MPGU'));

			if (isset($apps->return)) {
				if (isset($apps->return->id)) {
					$apps = array('0' => $apps->return);
				}
				else {
					$apps = $apps->return;
				}
			}

			if (is_array($specialties->return)) {
				return $this->render('VidalMainBundle:Appointment:appointment_set_spec.html.twig', array('specialties' => $specialties->return, 'apps' => $apps));
			}
		}
		$em          = $this->getDoctrine()->getManager();
		$appointment = new Appointment();
		$builder     = $this->createFormBuilder($appointment);
		$builder
			->add('email', null, array('label' => 'E-mail'))
			->add('OMSCode', null, array('label' => 'Номер полиса ОМС'))
			->add('birthdate', 'date', array(
				'label'  => 'Дата рождения',
				'years'  => range(date('Y') - 111, date('Y')),
				'format' => 'dd MMMM yyyy',
			))
			//            ->add('captcha', 'captcha', array('label' => 'Введите код с картинки'))
			->add('submit', 'submit', array('label' => 'Продолжить', 'attr' => array('class' => 'btn')));
		$form = $builder->getForm();
		$form->handleRequest($request);
		if ($request->isMethod('POST')) {
			if ($form->isValid()) {
				$appointment = $form->getData();
				# Авторизовываем полльзователя
				$session = $request->getSession();
				$session->set('EmiasBirthdate', $appointment->getBirthdate());
				$session->set('EmiasOms', $appointment->getOMSCode());
				$session->set('EmiasEmail', $appointment->getEmail());
				$session->save();

				$soap        = $this->createConnection();
				$specialties = $soap->getSpecialitiesInfo(array('omsNumber' => '9988889785000068', 'birthDate' => '2011-04-14T00:00:00', 'externalSystemId' => 'MPGU'));

				$apps = $soap->getAppointmentReceptionsByPatient(array('omsNumber' => '9988889785000068', 'birthDate' => '2011-04-14T00:00:00', 'externalSystemId' => 'MPGU'));

				if (isset($apps->return)) {
					if (isset($apps->return->id)) {
						$apps = array('0' => $apps->return);
					}
					else {
						$apps = $apps->return;
					}
				}

				if (is_array($specialties->return)) {
					return $this->render('VidalMainBundle:Appointment:appointment_set_spec.html.twig', array('specialties' => $specialties->return, 'apps' => $apps));
				}
			}
		}
		return array('form' => $form->createView());
	}

	/**
	 * @Route("/appointment-doctors/{doctorId}", name="appointment_doctor", options={"expose"=true})
	 */
	public function doctorsActions($doctorId)
	{
		if ($this->isAuth() == false) {
			return $this->redirect($this->generateUrl('appointment'),301);
		}
		$soap    = $this->createConnection();
		$doctors = $soap->getDoctorsInfo(array('omsNumber' => '9988889785000068', 'birthDate' => '2011-04-14T00:00:00', 'specialityId' => $doctorId, 'externalSystemId' => 'MPGU'));

		return new JsonResponse(array('data' => $doctors));
	}

	/**
	 * @Route("/appointment-datetime/{availableResourceId}/{complexResourceId}", name="appointment_datetime", options={"expose"=true})
	 */
	public function datetimeActions($availableResourceId, $complexResourceId)
	{
		if ($this->isAuth() == false) {
			return $this->redirect($this->generateUrl('appointment'),301);
		}
		$soap     = $this->createConnection();
		$datetime = $soap->getAvailableResourceScheduleInfo(array('omsNumber' => '9988889785000068', 'birthDate' => '2011-04-14T00:00:00', 'availableResourceId' => $availableResourceId, 'complexResourceId' => $complexResourceId, 'externalSystemId' => 'MPGU'));

		return new JsonResponse(array('data' => $datetime));
	}

	/**
	 * @Route("/appointment-create", name="appointment_create", options={"expose"=true})
	 */
	public function createAppointment(Request $request)
	{
		if ($this->isAuth() == false) {
			return $this->redirect($this->generateUrl('appointment'),301);
		}

		$request                        = $request->request;
		$availableResourceId            = $request->get('availableResourceId');
		$complexResourceId              = $request->get('complexResourceId');
		$receptionDate                  = new \DateTime($request->get('receptionDate'));
		$startDate                      = new \DateTime($request->get('startTime'));
		$endDate                        = new \DateTime($request->get('endTime'));
		$receptionTypeCodeOrLdpTypeCode = 1863;

		$soap = $this->createConnection();
		$id   = $soap->createAppointment(
			array(
				'omsNumber'                      => '9988889785000068',
				'birthDate'                      => '2011-04-14T00:00:00',
				'availableResourceId'            => $availableResourceId,
				'complexResourceId'              => $complexResourceId,
				'externalSystemId'               => 'MPGU',
				'receptionDate'                  => $receptionDate->format('Y-m-d') . 'T' . $receptionDate->format('H:i:s'),
				'startTime'                      => $startDate->format('Y-m-d') . 'T' . $startDate->format('H:i:s'),
				'endTime'                        => $endDate->format('Y-m-d') . 'T' . $endDate->format('H:i:s'),
				'receptionTypeCodeOrLdpTypeCode' => $receptionTypeCodeOrLdpTypeCode
			)
		);

		$data = $soap->getAppointmentReceptionsByPatient(
			array(
				'omsNumber'        => '9988889785000068',
				'birthDate'        => '2011-04-14T00:00:00',
				'externalSystemId' => 'MPGU'
			)
		);

		if (isset($id->return->appointmentId)) {
			if (isset($data->return)) {
				if (isset($data->return->id)) {
					$data = array('0' => $data->return);
				}
				else {
					$data = $data->return;
				}
			}

			foreach ($data as $val) {
				if ($id->return->appointmentId == $val->id) {
					$data = $val;
					break;
				}
			}

			if (isset($data->id)) {
				$this->get('email.service')->send(
					"tulupov.m@gmail.com",
					//                array('zakaz@zdravzona.ru'),
					array('VidalMainBundle:Email:Appointment_create.html.twig', array('data' => $data)),
					'Запись ко врачу на сайте Vidal.ru'
				);
			}
		}

		return $this->redirect($this->generateUrl('appointment'),301);
	}

	/**
	 * @Route("/appointment-list", name="appointment_list", options={"expose"=true})
	 */
	public function listActions()
	{
		if ($this->isAuth() == false) {
			return $this->redirect($this->generateUrl('appointment'),301);
		}
		$soap = $this->createConnection();
		$data = $soap->getAppointmentReceptionsByPatient(
			array(
				'omsNumber'        => '9988889785000068',
				'birthDate'        => '2011-04-14T00:00:00',
				'externalSystemId' => 'MPGU'
			)
		);

		return new JsonResponse(array('data' => $data));
	}

	/**
	 * @Route("/appointment-delete/{appointmentId}", name="appointment_delete", options={"expose"=true})
	 */
	public function deleteAction($appointmentId)
	{
		if ($this->isAuth() == false) {
			return $this->redirect($this->generateUrl('appointment'),301);
		}
		$soap = $this->createConnection();
		$data = $soap->cancelAppointment(
			array(
				'omsNumber'        => '9988889785000068',
				'birthDate'        => '2011-04-14T00:00:00',
				'appointmentId'    => $appointmentId,
				'externalSystemId' => 'MPGU'
			)
		);

		if (isset($appointmentId)) {
			$this->get('email.service')->send(
				"tulupov.m@gmail.com",
				//                array('zakaz@zdravzona.ru'),
				array('VidalMainBundle:Email:Appointment_remove.html.twig', array('data' => $appointmentId)),
				'Отмена записи ко врачу на сайте Vidal.ru'
			);
		}

		return $this->redirect($this->generateUrl('appointment'),301);
	}

	protected function createConnection()
	{
		$cert = "/var/www/vidal/web/sert/testSSLClient.pem"; //Сертификат
		$wsdl = "https://mosmedzdrav.ru:10002/emias-soap-service/PGUServicesInfo2?wsdl"; //Адрес wdsl сервиса
		$pass = 'testSSLClient';
		if (!is_file($cert)) {
			echo 'file certificate not found!';
			exit;
		}
		$sslOptions = array(
			'ssl' => array(
				'cafile'            => "/var/www/vidal/web/sert/RootMedCA.cer",
				'allow_self_signed' => true,
				'verify_peer'       => false,
			),
		);
		$sslContext = stream_context_create($sslOptions);
		$sp         = new \SoapClient($wsdl, array(
			'local_cert'         => $cert,
			'passphrase'         => $pass,
			'stream_context'     => $sslContext,
			'trace'              => 0,
			'exceptions'         => 0,
			'cache_wsdl'         => WSDL_CACHE_NONE,
			'wsdl_cache_enabled' => false
		));

		return $sp;
	}

}