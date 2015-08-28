<?php

namespace Vidal\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Vidal\MainBundle\Entity\User;
use Vidal\MainBundle\Form\Type\RegisterType;
use Vidal\MainBundle\Form\Type\ProfileType;
use Lsw\SecureControllerBundle\Annotation\Secure;
use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;

class AuthController extends Controller
{
	/**
	 * @Route("/login", name="login")
	 * @Template("VidalMainBundle:Auth:login.html.twig")
	 */
	public function loginAction(Request $request)
	{
		if ($this->get('security.context')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
			return new RedirectResponse($this->generateUrl('index'));
		}

		$success  = false;
		$username = $request->request->get('username', null);
		$password = $request->request->get('password', null);
		$em       = $this->getDoctrine()->getManager();
		$user     = $em->getRepository('VidalMainBundle:User')->findOneByLogin($username);

		if ($user) {
			$pwReal = $user->getPassword();

			# пользователей со старой БД проверям с помощью mysql-функций
			if ($password === $pwReal) {
				$success = true;
			}
			elseif ($user->getOldUser()) {
				$success = $em->getRepository('VidalMainBundle:User')->checkOldPassword($password, $pwReal);
			}
		}

		if ($success) {
			$this->resetToken($user);
			$user->setLastLogin(new \DateTime('now'));
			$em->flush();

			return $this->redirect($this->generateUrl('index'),301);
		}

		return array(
			'loginAuthError' => $username && !$success,
			'title'          => 'Вход для специалистов',
			'moduleId'       => 7,
			'username'       => $username,
			'password'       => $password,
		);
	}

	/**
	 * @Route("/confirm", name="confirm")
	 * @Secure(roles="IS_AUTHENTICATED_REMEMBERED")
	 * @Template("VidalMainBundle:Auth:confirm.html.twig")
	 */
	public function confirmAction()
	{
		$params = array(
			'title' => 'Подтвердите e-mail',
			'menu'  => 'vracham',
			'user'  => $this->getUser(),
		);

		return $params;
	}

	/**
	 * @Route("/registration-resend", name="registration_resend")
	 */
	public function registrationResend()
	{
		$security = $this->get('security.context');
		if (!$security->isGranted('IS_AUTHENTICATED_REMEMBERED') || $security->isGranted('ROLE_DOCTOR')) {
			throw $this->createNotFoundException();
		}

		$user = $this->getUser();

		# уведомление пользователя о регистрации
		$this->get('email.service')->send(
			$user->getUsername(),
			array('VidalMainBundle:Email:registration.html.twig', array('user' => $user)),
			'Благодарим за регистрацию на нашем портале!'
		);

		return new JsonResponse('OK');
	}

	/**
	 * Регистрация врача на сайте
	 *
	 * @Route("/registration", name="registration")
	 * @Template("VidalMainBundle:Auth:registration.html.twig")
	 */
	public function registrationAction(Request $request)
	{
		if ($this->get('security.context')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
			return $this->redirect($this->generateUrl('index'),301);
		}

		$em     = $this->getDoctrine()->getManager();
		$user   = new User();
		$form   = $this->createForm(new RegisterType($em), $user);
		$params = array('title' => 'Регистрация');

		$form->handleRequest($request);

		if ($form->isValid()) {
			$oldUser = $em->getRepository('VidalMainBundle:User')->findByUsername($user->getUsername());

			if (empty($oldUser)) {
				# e-mail свободен, сохраняем пользователя
				$user->refreshHash();
				$user->setLastLogin(new \DateTime('now'));

				$em->persist($user);
				$em->flush();
				$em->refresh($user);

				$this->resetToken($user);

				# уведомление пользователя о регистрации
				$this->get('email.service')->send(
					$user->getUsername(),
					array('VidalMainBundle:Email:registration.html.twig', array('user' => $user)),
					'Благодарим за регистрацию на нашем портале!'
				);

				# уведомление администраторов о регистрации
				//				$this->get('email.service')->send(
				//					$this->container->getParameter('manager_emails'),
				//					array('VidalMainBundle:Email:registration_notice.html.twig', array('user' => $user)),
				//					'Зарегистрировался новый пользователь'
				//				);

				return $this->redirect($this->generateUrl('profile'),301);
			}
			else {
				$params['error'] = true;
			}
		}

		$params['form'] = $form->createView();

		return $params;
	}

	/**
	 * Редактирование профиля
	 *
	 * @Route("/profile", name="profile")
	 * @Template()
	 * @Secure(roles="IS_AUTHENTICATED_REMEMBERED")
	 */
	public function profileAction(Request $request)
	{
		$em   = $this->getDoctrine()->getManager();
		$user = $this->getUser();
		$form = $this->createForm(new ProfileType($em), $user);

		$form->handleRequest($request);

		if ($form->isValid()) {
			$em->persist($user);
			if ($user->getConfirmationScan()) {
				$user->setConfirmationHas(true);
			}
			$em->flush();
			$this->get('session')->getFlashBag()->add('saved', '');

			if (!empty($_FILES['profile']['name']['ConfirmationScan']['file'])) {
				# отправить письмо Максимилиану, что загрузили скан
				$this->get('email.service')->send(
					$user->getUsername(),
					array('VidalMainBundle:Email:scan_uploaded.html.twig', array('user' => $user)),
					'Сертификат специалиста был загружен пользователем',
					'm.vlasenko@vidal.ru'
				);
			}

			return $this->redirect($this->generateUrl('profile'),301);
		}

		return array(
			'form'  => $form->createView(),
			'user'  => $user,
			'title' => 'Профиль',
		);
	}

	/**
	 * Подтверждает адрес электронной почты пользователя. Если пользователь подтвердил адрес и прошел тест, ему выставляется роль ROLE_DOCTOR или ROLE_STUDENT вместо ROLE_UNCONFIRMED
	 *
	 * @Route("/confirm-email/{userId}/{hash}", name="confirm_email")
	 */
	public function confirmEmailAction($userId, $hash)
	{
		$em = $this->getDoctrine()->getManager();

		$user = $em->find('VidalMainBundle:User', $userId);

		if (!empty($user) && $user->getHash() == $hash) {
			$user->setEmailConfirmed(true);
			$user->addRole('ROLE_DOCTOR');
			$user->setLastLogin(new \DateTime('now'));
			$em->flush();

			$this->resetToken($user);

			$this->get('session')->getFlashBag()->add('confirmed', '');

			return $this->redirect($this->generateUrl('profile'),301);
		}

		return $this->redirect($this->generateUrl('index'),301);
	}

	/**
	 * @Route("/eula", name="eula")
	 * @Template()
	 */
	public function eulaAction()
	{
		return array();
	}

	/**
	 * @Route("/reset-avatar", name="reset_avatar")
	 * @Secure(roles="IS_AUTHENTICATED_REMEMBERED")
	 */
	public function resetAvatarAction()
	{
		$sql        = 'UPDATE user SET avatar = NULL WHERE id = ' . $this->getUser()->getId();
		$connection = $this->getDoctrine()->getManager()->getConnection();
		$stmt       = $connection->prepare($sql);

		$stmt->execute();

		return $this->redirect($this->generateUrl('profile'),301);
	}

	private function resetToken($user)
	{
		$secret = $this->container->getParameter('secret');
		$token  = new RememberMeToken($user, 'everything', $secret);
		$this->get('security.context')->setToken($token);
	}

	private function generatePassword()
	{
		return substr(chr(rand(103, 122)) . chr(rand(103, 122)) . chr(rand(103, 122)) . md5(time() + rand(100, 999) . chr(rand(97, 122)) . chr(rand(97, 122)) . chr(rand(97, 122))), 0, 8);
	}

	/**
	 * [AJAX] Автозаполнение городов
	 * @Route("/ajax/city", name="ajax_city", options={"expose"=true})
	 */
	public function ajaxCityAction(Request $request)
	{
		if ($request->query->has('term') == false) {
			return new JsonResponse();
		}

		$term = $request->query->get('term');

		$words  = explode(' ', $term);
		$query  = implode('* ', $words) . '*';
		$client = new \Elasticsearch\Client();

		$s['index']                                                        = 'website';
		$s['type']                                                         = 'autocomplete_city';
		$s['body']['size']                                                 = 10;
		$s['body']['query']['filtered']['query']['query_string']['query']  = $query;
		$s['body']['query']['filtered']['query']['query_string']['fields'] = array('name', 'title');
		$s['body']['highlight']['fields']['name']                          = array("fragment_size" => 100);
		$s['body']['sort']['name']['order']                                = 'asc';

		$results = $client->search($s);
		$titles  = array();

		if (isset($results['hits']['hits']) && !empty($results['hits']['hits'])) {
			foreach ($results['hits']['hits'] as $result) {
				$titles[] = $result['_source']['title'];
			}
		}

		if (!empty($titles)) {
			return new JsonResponse($titles);
		}

		$em     = $this->getDoctrine()->getManager();
		$titles = $em->getRepository('VidalMainBundle:City')->findAutocomplete($term);

		return new JsonResponse($titles);
	}

	/**
	 * Сброс пароля
	 * @Route("/password-reset", name="password_reset")
	 * @Template("VidalMainBundle:Auth:password_reset.html.twig")
	 */
	public function passwordResetAction(Request $request)
	{
		if ($this->get('security.context')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
			return $this->redirect($this->generateUrl('index'),301);
		}

		$params = array('title' => 'Восстановление пароля');
		$form   = $this->createFormBuilder()
			->add('email', 'email', array(
				'label'       => 'Введите ваш e-mail адрес',
				'required'    => true,
				'constraints' => new NotBlank(array('message' => 'Укажите e-mail'))
			))
			->add('submit', 'submit', array('label' => 'Сбросить пароль'))
			->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$em       = $this->getDoctrine()->getManager();
			$formData = $form->getData();
			$email    = $formData['email'];
			$user     = $em->getRepository('VidalMainBundle:User')->findOneByUsername($email);

			if ($user) {
				$em->flush();
				$this->get('email.service')->send(
					$user->getUsername(),
					array('VidalMainBundle:Email:password_reset.html.twig', array('user' => $user)),
					'Сброс пароля'
				);
				$this->get('session')->getFlashBag()->add('notice', '');

				return $this->redirect($this->generateUrl('password_reset'),301);
			}
			else {
				$form->addError(new FormError('Такой e-mail адрес не зарегистрирован в системе'));
			}
		}

		$params['form'] = $form->createView();

		return $params;
	}

	/**
	 * Cброс забытого пароля
	 * @Route("/confirm-password-reset/{userId}/{hash}", name = "confirm_password_reset")
	 * @Template("VidalMainBundle:Auth:password_reseted.html.twig")
	 */
	public function confirmPasswordResetAction($userId, $hash)
	{
		if ($this->get('security.context')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
			return $this->redirect($this->generateUrl('index'),301);
		}

		$em   = $this->getDoctrine()->getManager();
		$user = $em->getRepository('VidalMainBundle:User')->findOneById($userId);

		if (empty($user) || $user->getHash() != $hash) {
			throw $this->createNotFoundException();
		}

		$params = array(
			'title' => 'Пароль успешно сброшен',
			'user'  => $user,
		);

		$user->refreshPassword();
		$em->flush();

		$this->get('email.service')->send(
			$user->getUsername(),
			array('VidalMainBundle:Email:confirm_password_reset.html.twig', array('user' => $user)),
			'Новый пароль'
		);

		$this->resetToken($user);

		return $params;
	}

	/**
	 * Cмены пароля
	 *
	 * @Route("/password-change", name="password_change")
	 * @Secure(roles="IS_AUTHENTICATED_REMEMBERED")
	 * @Template("VidalMainBundle:Auth:password_change.html.twig")
	 */
	public function passwordChangeAction(Request $request)
	{
		$user = $this->getUser();

		$form = $this->createFormBuilder()
			->add('password', null, array(
				'label'       => 'Текущий пароль',
				'required'    => true,
				'constraints' => new NotBlank(array('message' => 'Укажите текущий пароль'))
			))
			->add('new', 'repeated', array(
				'type'            => 'password',
				'invalid_message' => 'Пароли должны совпадать',
				'options'         => array('attr' => array('class' => 'password-field')),
				'required'        => true,
				'first_options'   => array('label' => 'Укажите новый пароль'),
				'second_options'  => array('label' => 'Повторите пароль'),
			))
			->add('submit', 'submit', array('label' => 'Сменить пароль'))
			->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$formData = $form->getData();

			if ($user->getPassword() === $formData['password']) {
				$user->setPassword($formData['new']);
				$em = $this->getDoctrine()->getManager();
				$em->flush();
				$this->get('session')->getFlashBag()->add('notice', '');

				return $this->redirect($this->generateUrl('password_change'),301);
			}
			else {
				$form->addError(new FormError('Неверно указан текущий пароль'));
			}
		}

		return array(
			'title' => 'Смена пароля',
			'form'  => $form->createView(),
			'user'  => $user
		);
	}

	/**
	 * Отправка данных для входа на сайт
	 *
	 * @Route("/credentials", name="credentials")
	 * @Template("VidalMainBundle:Auth:credentials.html.twig")
	 */
	public function credentialsAction(Request $request)
	{
		$form = $this->createFormBuilder()
			->add('email', null, array(
				'label'       => 'Укажите E-mail',
				'required'    => true,
				'constraints' => new Assert\Email(array(
					'message' => 'E-mail {{ value }} указан некорректно',
					'checkMX' => false,
				)),
			))
			->add('submit', 'submit', array('label' => 'Отправить'))
			->getForm();

		$form->handleRequest($request);

		if ($form->isValid()) {
			$formData = $form->getData();
			$email    = $formData['email'];
			$em       = $this->getDoctrine()->getManager();
			$user     = $em->getRepository('VidalMainBundle:User')->findOneByUsername($email);

			if (!$user) {
				$form->get('email')->addError(new FormError('Пользователь с таким e-mail адресом в системе не обнаружен'));
			}
			else {
				$this->get('email.service')->send(
					$email,
					array('VidalMainBundle:Email:credentials.html.twig', array('user' => $user)),
					'Напоминание данных для входа на сайт Vidal.ru'
				);

				$this->get('session')->getFlashBag()->add('notice', $email);

				return $this->redirect($this->generateUrl('credentials'),301);
			}
		}

		return array(
			'title' => 'Напомнить данные для входа на сайт',
			'form'  => $form->createView(),
		);
	}

	/** @Route("/unsubscribe-digest/{userId}/{userCreated}", name = "unsubscribe_digest") */
	public function unsubscribeDigestAction($userId, $userCreated)
	{
		$em   = $this->getDoctrine()->getManager();
		$user = $em->getRepository('VidalMainBundle:User')->findOneById($userId);

		if (!$user || $user->getCreated()->format('Y-m-d_H:i:s') != $userCreated) {
			throw $this->createNotFoundException();
		}

		$user->setDigestSubscribed(false);
		$em->flush();

		return $this->render('VidalMainBundle:Auth:unsubscribe_digest.html.twig', array(
			'title' => 'Подписка на рассылку новостей',
			'user'  => $user
		));
	}

	/**
	 * [AJAX] Логин через асинхронный запрос
	 * @Route("/ajax-login", name="ajax_login", options={"expose"=true})
	 */
	public function ajaxLoginAction(Request $request)
	{
		$username = $request->request->get('username');
		$password = $request->request->get('password');
		$em       = $this->getDoctrine()->getManager();
		$user     = $em->getRepository('VidalMainBundle:User')->findOneByLogin($username);

		if (!$user) {
			return new JsonResponse(array('success' => 'no'));
		}

		$pwReal = $user->getPassword();
		$auth   = false;

		# пользователей со старой БД проверям с помощью mysql-функций
		if ($user->getOldUser()) {
			$pdo = $em->getConnection();

			$stmt = $pdo->prepare("SELECT PASSWORD('$password') as password");
			$stmt->execute();
			$pw1 = $stmt->fetch();
			$pw1 = $pw1['password'];

			$stmt = $pdo->prepare("SELECT OLD_PASSWORD('$password') as password");
			$stmt->execute();
			$pw2 = $stmt->fetch();
			$pw2 = $pw2['password'];

			if ($pw1 === $pwReal || $pw2 === $pwReal) {
				$auth = true;
			}
		}

		if (!$auth && $pwReal === $password) {
			$auth = true;
		}

		if (!$auth) {
			return new JsonResponse(array('success' => 'no'));
		}

		$this->resetToken($user);
		$user->setLastLogin(new \DateTime('now'));
		$em->flush();

		return new JsonResponse(array('success' => 'yes'));
	}
}
