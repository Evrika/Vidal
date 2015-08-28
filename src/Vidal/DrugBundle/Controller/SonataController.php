<?php

namespace Vidal\DrugBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Lsw\SecureControllerBundle\Annotation\Secure;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Vidal\DrugBundle\Entity\TagHistory;
use Vidal\DrugBundle\Entity\Tag;

/**
 * Класс для выполнения ассинхронных операций из админки Сонаты
 *
 * @Secure(roles="ROLE_ADMIN")
 */
class SonataController extends Controller
{
	/**
	 * Действие смены булева поля
	 * @Route("/swap/{field}/{entity}/{id}", name = "swap_field")
	 */
	public function swapAction($field, $entity, $id)
	{
		$em     = $this->getDoctrine()->getManager('drug');
		$entity = 'VidalDrugBundle:' . $entity;
		$field  = 'e.' . $field;

		$isActive = $em->createQueryBuilder()
			->select($field)
			->from($entity, 'e')
			->where('e.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleScalarResult();

		$swapActive = $isActive ? 0 : 1;

		$qb = $em->createQueryBuilder()
			->update($entity, 'e')
			->set($field, $swapActive)
			->where('e.id = :id')
			->setParameter('id', $id);

		$qb->getQuery()->execute();

		return new JsonResponse($swapActive);
	}

	/**
	 * Действие смены булева поля
	 * @Route("/swap-main/{field}/{entity}/{id}", name = "swap_main")
	 */
	public function swapMainAction($field, $entity, $id)
	{
		$em     = $this->getDoctrine()->getManager();
		$entity = 'VidalMainBundle:' . $entity;
		$field  = 'e.' . $field;

		$isActive = $em->createQueryBuilder()
			->select($field)
			->from($entity, 'e')
			->where('e.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleScalarResult();

		$swapActive = $isActive ? 0 : 1;

		$qb = $em->createQueryBuilder()
			->update($entity, 'e')
			->set($field, $swapActive)
			->where('e.id = :id')
			->setParameter('id', $id);

		$qb->getQuery()->execute();

		return new JsonResponse($swapActive);
	}

	/**
	 * [AJAX] Подгрузка категорий
	 * @Route("/admin/types-of-rubrique/{rubriqueId}", name="types_of_rubrique", options={"expose":true})
	 */
	public function typesOfRubrique($rubriqueId)
	{
		$em      = $this->getDoctrine()->getManager('drug');
		$results = $em->createQuery('
			SELECT t.id, t.title
			FROM VidalDrugBundle:ArtType t
			WHERE t.rubrique = :rubriqueId
			ORDER BY t.title ASC
		')->setParameter('rubriqueId', $rubriqueId)
			->getResult();

		return new JsonResponse($results);
	}

	/**
	 * [AJAX] Подгрузка категорий
	 * @Route("/admin/categories-of-type/{typeId}", name="categories_of_type", options={"expose":true})
	 */
	public function categoriesOfType($typeId)
	{
		$em      = $this->getDoctrine()->getManager('drug');
		$results = $em->createQuery('
			SELECT c.id, c.title
			FROM VidalDrugBundle:ArtCategory c
			WHERE c.type = :typeId
			ORDER BY c.title ASC
		')->setParameter('typeId', $typeId)
			->getResult();

		return new JsonResponse($results);
	}

	/** @Route("/admin/product-add/{type}/{id}/{ProductID}", name="product_add", options={"expose":true}) */
	public function productAddAction($type, $id, $ProductID)
	{
		$em      = $this->getDoctrine()->getManager('drug');
		$entity  = $em->getRepository("VidalDrugBundle:$type")->findOneById($id);
		$product = $em->getRepository("VidalDrugBundle:Product")->findOneByProductID($ProductID);

		$entity->addProduct($product);
		$em->flush();

		return new JsonResponse('OK');
	}

	/** @Route("/admin/product-remove/{type}/{id}/{ProductID}", name="product_remove", options={"expose":true}) */
	public function productRemoveAction($type, $id, $ProductID)
	{
		$em      = $this->getDoctrine()->getManager('drug');
		$entity  = $em->getRepository("VidalDrugBundle:$type")->findOneById($id);
		$product = $em->getRepository('VidalDrugBundle:Product')->findOneByProductID($ProductID);

		if (!$entity || !$product) {
			return new JsonResponse('FAIL');
		}

		$entity->removeProduct($product);
		$em->flush();

		return new JsonResponse('OK');
	}

	/**
	 * @Route("/admin/move-art", name="move_art")
	 *
	 * @Template("VidalDrugBundle:Sonata:move_art.html.twig")
	 */
	public function moveArtAction(Request $request)
	{
		$em        = $this->getDoctrine()->getManager('drug');
		$articles  = $em->getRepository('VidalDrugBundle:Art')->findAll();
		$rubriques = $em->getRepository('VidalDrugBundle:ArtRubrique')->findAll();

		$params = array(
			'title'     => 'Перемещение статей',
			'articles'  => $articles,
			'rubriques' => $rubriques,
		);

		if ($request->getMethod() == 'POST') {
			$articleIds = $request->request->get('articles');
			$rubriqueId = $request->request->get('rubrique', null);
			$typeId     = $request->request->get('type', null);
			$categoryId = $request->request->get('category', null);

			$em->createQuery('
				UPDATE VidalDrugBundle:Art a
				SET a.rubrique = :rubriqueId,
					a.type = :typeId,
					a.category = :categoryId
				WHERE a.id IN (:articleIds)
			')->setParameters(array(
				'articleIds' => $articleIds,
				'rubriqueId' => $rubriqueId,
				'typeId'     => $typeId,
				'categoryId' => $categoryId,
			))->execute();

			$this->get('session')->getFlashBag()->add('notice', '');

			return $this->redirect($this->generateUrl('move_art'),301);
		}

		return $params;
	}

	/**
	 * @Route("/admin/qa-email/{id}", name="qa_email")
	 */
	public function qaEmailAction($id)
	{
		$em = $this->getDoctrine()->getManager();
		$qa = $em->getRepository('VidalMainBundle:QuestionAnswer')->findOneById($id);

		if (!$qa || $qa->getEmailSent() == true) {
			return new JsonResponse(false);
		}

		$email = $qa->getAuthorEmail();

		$this->get('email.service')->send(
			$email,
			array('VidalMainBundle:Email:qa_answer.html.twig', array('faq' => $qa)),
			'Ответ на сайте vidal.ru'
		);

		$qa->setEmailSent(true);
		$em->flush();

		return new JsonResponse(true);
	}

	/**
	 * @Route("/admin/qa-email-test/{id}", name="qa_email_test")
	 */
	public function qaEmailTestAction($id)
	{
		$em = $this->getDoctrine()->getManager();
		$qa = $em->getRepository('VidalMainBundle:QuestionAnswer')->findOneById($id);

		$email = $qa->getAuthorEmail();

		$this->get('email.service')->send(
			$email,
			array('VidalMainBundle:Email:qa_answer.html.twig', array('faq' => $qa)),
			'Ответ на сайте vidal.ru'
		);

		$qa->setEmailSent(true);
		$em->flush();

		exit;
	}

	public function send($email, $html, $subject)
	{
		$mail = new \PHPMailer();

		$mail->isSMTP();
		$mail->isHTML(true);
		$mail->CharSet  = 'UTF-8';
		$mail->FromName = 'Портал Vidal.ru';
		$mail->Subject  = $subject;
		$mail->Body     = $html;
		$mail->addAddress($email);

		if ($this->container->getParameter('kernel.environment') == 'prod') {
			$mail->Host = '127.0.0.1';
			$mail->From = 'maillist@vidal.ru';
		}
		else {
			$mail->Host       = 'smtp.mail.ru';
			$mail->From       = '7binary@list.ru';
			$mail->SMTPSecure = 'ssl';
			$mail->Port       = 465;
			$mail->SMTPAuth   = true;
			$mail->Username   = '7binary@list.ru';
			$mail->Password   = 'ooo000)O';
		}

		return $mail->send();
	}

	/** @Route("/admin/check-document/{DocumentID}", name="check_document", options={"expose":true}) */
	public function checkDocument($DocumentID)
	{
		$em           = $this->getDoctrine()->getManager('drug');
		$documentInDb = $em->getRepository('VidalDrugBundle:Document')->findOneByDocumentID($DocumentID);
		$isFree       = $documentInDb ? 0 : 1;

		return new JsonResponse($isFree);
	}

	/** @Route("/admin/check-product/{ProductID}", name="check_product", options={"expose":true}) */
	public function checkProduct($ProductID)
	{
		$em          = $this->getDoctrine()->getManager('drug');
		$productInDb = $em->getRepository('VidalDrugBundle:Product')->findOneByProductID($ProductID);
		$isFree      = $productInDb ? 0 : 1;

		return new JsonResponse($isFree);
	}

	/** @Route("/admin/clone-document/{DocumentID}/{newDocumentID}", name="clone_document", options={"expose":true}) */
	public function cloneDocument($DocumentID, $newDocumentID)
	{
		$em = $this->getDoctrine()->getManager('drug');

		$columns = 'RusName, EngName, Name, CompiledComposition, ArticleID, YearEdition, DateOfIncludingText,
		 DateTextModified, Elaboration, CompaniesDescription, ClPhGrDescription, ClPhGrName, PhInfluence, PhKinetics,
		 Dosage, OverDosage, Interaction, Lactation, SideEffects, StorageCondition, Indication, ContraIndication,
		 SpecialInstruction, ShowGenericsOnlyInGNList, NewForCurrentEdition, CountryEditionCode, IsApproved,
		 CountOfColorPhoto,	PregnancyUsing, NursingUsing, RenalInsuf, RenalInsufUsing, HepatoInsuf, HepatoInsufUsing,
		 PharmDelivery, WithoutRenalInsuf, WithoutHepatoInsuf, ElderlyInsuf, ElderlyInsufUsing, ChildInsuf,
		 ChildInsufUsing, ed';

		$pdo   = $em->getConnection();
		$query = "
			INSERT INTO document (DocumentID, $columns)
			SELECT $newDocumentID, $columns
			FROM document
			WHERE DocumentID = $DocumentID
		";

		# отключаем проверку внешних ключей
		$stmt = $pdo->prepare('SET FOREIGN_KEY_CHECKS=0');
		$stmt->execute();

		# вставляем документ с новым идентификатором
		$stmt = $pdo->prepare($query);
		$stmt->execute();

		# надо склонировать связи старого документа на новый
		$tables = explode(' ', 'document_indicnozology document_clphpointers documentoc_atc document_infopage art_document article_document molecule_document pharm_article_document publication_document');
		$fields = explode(' ', 'NozologyCode ClPhPointerID ATCCode InfoPageID art_id article_id MoleculeID pharm_article_id publication_id');

		for ($i = 0; $i < count($tables); $i++) {
			$table = $tables[$i];
			$field = $fields[$i];
			$stmt  = $pdo->prepare("
				INSERT INTO $table ($field, DocumentID)
				SELECT $field, $newDocumentID
				FROM $table
				WHERE DocumentID = $DocumentID
			");
			$stmt->execute();
		}

		return $this->redirect($this->generateUrl('admin_vidal_drug_document_edit', array('id' => $newDocumentID)),301);
	}

	/** @Route("/admin/clone-product/{ProductID}/{newProductID}", name="clone_product", options={"expose":true}) */
	public function cloneProduct($ProductID, $newProductID)
	{
		$em = $this->getDoctrine()->getManager('drug');

		$columns = 'RusName, EngName, Name, NonPrescriptionDrug, CountryEditionCode, RegistrationDate,
			DateOfCloseRegistration, RegistrationNumber, PPR, ZipInfo, Composition, DateOfIncludingText, ProductTypeCode,
			ItsMultiProduct, BelongMultiProductID, CheckingRegDate, Personal, m, GNVLS, DLO, List_AB, List_PKKN,
			StrongMeans, Poison, MinAs,	ValidPeriod, StrCond, photo, inactive, MarketStatusID';

		$pdo   = $em->getConnection();
		$query = "
			INSERT INTO product (ProductID, document_id, $columns)
			SELECT $newProductID, NULL, $columns
			FROM product
			WHERE ProductID = $ProductID
		";

		# отключаем проверку внешних ключей
		$stmt = $pdo->prepare('SET FOREIGN_KEY_CHECKS=0');
		$stmt->execute();

		# вставляем документ с новым идентификатором
		$stmt = $pdo->prepare($query);
		$stmt->execute();

		# надо склонировать связи старого документа на новый
		$tables = explode(' ', 'product_atc product_clphgroups product_company product_phthgrp product_moleculename');
		$fields = explode(' ', 'ATCCode ClPhGroupsID CompanyID PhThGroupsID MoleculeNameID');

		for ($i = 0; $i < count($tables); $i++) {
			$table = $tables[$i];
			$field = $fields[$i];
			$stmt  = $pdo->prepare("
				INSERT INTO $table ($field, ProductID)
				SELECT $field, $newProductID
				FROM $table
				WHERE ProductID = $ProductID
			");
			$stmt->execute();
		}

		# надо склонировать картинки
		$stmt = $pdo->prepare("
			INSERT INTO productpicture (ProductID, PictureID, YearEdition, CountryEditionCode, EditionCode)
			SELECT $newProductID, PictureID, YearEdition, CountryEditionCode, EditionCode
			FROM productpicture
			WHERE ProductID = $ProductID
		");
		$stmt->execute();

		$this->get('session')->getFlashbag()->add('notice', '');

		return $this->redirect($this->generateUrl('admin_vidal_drug_product_edit', array('id' => $newProductID)),301);
	}

	/** @Route("/tag-clean/{tagId}", name="tag_clean", options={"expose":true}) */
	public function tagCleanAction($tagId)
	{
		$em  = $this->getDoctrine()->getManager('drug');
		$tag = $em->getRepository('VidalDrugBundle:Tag')->findOneById($tagId);

		if (!$tag) {
			throw $this->createNotFoundException();
		}

		$pdo = $em->getConnection();

		$tagId  = $tag->getId();
		$tables = explode(' ', 'art_tag article_tag publication_tag pharmarticle_tag');
		foreach ($tables as $table) {
			$stmt = $pdo->prepare("DELETE FROM $table WHERE tag_id = $tagId");
			$stmt->execute();
		}

		$tag->setTotal(0);
		$em->flush($tag);

		$pdo->prepare("DELETE FROM tag_history WHERE tag_id = $tagId")->execute();

		# добавляем для админки сонаты оповещение
		$this->get('session')->getFlashbag()->add('msg', 'Все связи данного тега с материалами очищены');

		return $this->redirect($this->generateUrl('admin_vidal_drug_tag_edit', array('id' => $tagId)),301);
	}

	/** @Route("/tag-set/{tagId}/{text}", name="tag_set", options={"expose":true}) */
	public function tagSetAction(Request $request, $tagId, $text = null)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$tag      = $em->getRepository('VidalDrugBundle:Tag')->findOneById($tagId);
		$pdo      = $em->getConnection();
		$isPartly = $request->query->has('partly');
		$total    = 0;
		$min      = 2;

		if (!$tag) {
			throw $this->createNotFoundException();
		}

		if (empty($text)) {
			$tagSearch = $tag->getSearch();
			$text      = empty($tagSearch) ? $tag->getText() : $tagSearch;
		}

		$tagHistoryText = $isPartly ? '*' . $text . '*' : $text;
		$tagHistory     = $em->getRepository('VidalDrugBundle:TagHistory')->findOneByTagText($tagId, $tagHistoryText);

		if (!$tagHistory) {
			$tagHistory = new TagHistory();
			$tagHistory->setText($tagHistoryText);

			$em->persist($tagHistory);
			$tag->addTagHistory($tagHistory);
			$em->flush($tagHistory);
			$em->refresh($tagHistory);
		}

		# проставляем тег у статей энкициклопедии
		$stmt = $isPartly
			? $pdo->prepare("SELECT id,body FROM article WHERE title LIKE '%{$text}%' OR body LIKE '%{$text}%' OR announce LIKE '%{$text}%'")
			: $pdo->prepare("SELECT id,body FROM article WHERE title REGEXP '[[:<:]]{$text}[[:>:]]' OR body REGEXP '[[:<:]]{$text}[[:>:]]' OR announce REGEXP '[[:<:]]{$text}[[:>:]]'");

		$stmt->execute();
		$articles = $stmt->fetchAll();

		foreach ($articles as $a) {
			$matched = mb_substr_count(mb_strtolower($a['body'], 'utf-8'), mb_strtolower($text, 'utf-8'), 'utf-8');

			if ($tag->getForCompany() || $matched >= $min) {
				$id   = $a['id'];
				$stmt = $pdo->prepare("INSERT IGNORE INTO article_tag (tag_id, article_id) VALUES ($tagId, $id)");
				$stmt->execute();
				if ($stmt->rowCount()) {
					$tagHistory->addArticleId($id);
					$total++;
				}
			}
		}

		# проставляем тег у статей специалистам
		$stmt = $isPartly
			? $pdo->prepare("SELECT id,body FROM art WHERE title LIKE '%{$text}%' OR body LIKE '%{$text}%' OR announce LIKE '%{$text}%'")
			: $pdo->prepare("SELECT id,body FROM art WHERE title REGEXP '[[:<:]]{$text}[[:>:]]' OR body REGEXP '[[:<:]]{$text}[[:>:]]' OR announce REGEXP '[[:<:]]{$text}[[:>:]]'");

		$stmt->execute();
		$articles = $stmt->fetchAll();

		foreach ($articles as $a) {
			$matched = mb_substr_count(mb_strtolower($a['body'], 'utf-8'), mb_strtolower($text, 'utf-8'), 'utf-8');

			if ($tag->getForCompany() || $matched >= $min) {
				$id   = $a['id'];
				$stmt = $pdo->prepare("INSERT IGNORE INTO art_tag (tag_id, art_id) VALUES ($tagId, $id)");
				$stmt->execute();
				if ($stmt->rowCount()) {
					$tagHistory->addArtId($id);
					$total++;
				}
			}
		}

		# проставляем тег у новостей
		$stmt = $isPartly
			? $pdo->prepare("SELECT id,body FROM publication WHERE title LIKE '%{$text}%' OR body LIKE '%{$text}%' OR announce LIKE '%{$text}%'")
			: $pdo->prepare("SELECT id,body FROM publication WHERE title REGEXP '[[:<:]]{$text}[[:>:]]' OR body REGEXP '[[:<:]]{$text}[[:>:]]' OR announce REGEXP '[[:<:]]{$text}[[:>:]]'");

		$stmt->execute();
		$articles = $stmt->fetchAll();

		foreach ($articles as $a) {
			$matched = mb_substr_count(mb_strtolower($a['body'], 'utf-8'), mb_strtolower($text, 'utf-8'), 'utf-8');

			if ($tag->getForCompany() || $matched >= $min) {
				$id   = $a['id'];
				$stmt = $pdo->prepare("INSERT IGNORE INTO publication_tag (tag_id, publication_id) VALUES ($tagId, $id)");
				$stmt->execute();
				if ($stmt->rowCount()) {
					$tagHistory->addPublicationId($id);
					$total++;
				}
			}
		}

		$em->flush();

		$this->get('drug.tag_total')->count($tagId);

		$this->get('session')->getFlashbag()->add('msg', "Выставлены теги в материалах по слову <b>$tagHistory</b>: $total");

		return $this->redirect($this->generateUrl('admin_vidal_drug_tag_edit', array('id' => $tagId)),301);
	}

	/** @Route("/tag-unset/{tagId}/{text}", name="tag_unset", options={"expose":true}) */
	public function tagUnsetAction(Request $request, $tagId, $text = null)
	{
		$em         = $this->getDoctrine()->getManager('drug');
		$tag        = $em->getRepository('VidalDrugBundle:Tag')->findOneById($tagId);
		$tagHistory = $em->getRepository('VidalDrugBundle:TagHistory')->findOneByTagText($tagId, $text);
		$pdo        = $em->getConnection();

		if (!$tag || !$tagHistory || !$text) {
			return $this->redirect($this->generateUrl('admin_vidal_drug_tag_edit', array('id' => $tagId)),301);
		}

		# удаляем tag-article
		$ids = $tagHistory->getArticleIds();
		if (!empty($ids)) {
			$ids  = implode(',', $tagHistory->getArticleIds());
			$stmt = $pdo->prepare("DELETE FROM article_tag WHERE tag_id = $tagId AND article_id IN ($ids)");
			$stmt->execute();
		}

		# удаляем tag-art
		$ids = $tagHistory->getArtIds();
		if (!empty($ids)) {
			$ids  = implode(',', $tagHistory->getArtIds());
			$stmt = $pdo->prepare("DELETE FROM art_tag WHERE tag_id = $tagId AND art_id IN ($ids)");
			$stmt->execute();
		}

		# удаляем tag-publication
		$ids = $tagHistory->getPublicationIds();
		if (!empty($ids)) {
			$ids  = implode(',', $tagHistory->getPublicationIds());
			$stmt = $pdo->prepare("DELETE FROM publication_tag WHERE tag_id = $tagId AND publication_id IN ($ids)");
			$stmt->execute();
		}

		$em->remove($tagHistory);
		$em->flush();

		$this->get('drug.tag_total')->count($tagId);

		# добавляем для админки сонаты оповещение
		$this->get('session')->getFlashbag()->add('msg', 'Очищены связи с материалами по слову <b>' . $text . '</b>');

		return $this->redirect($this->generateUrl('admin_vidal_drug_tag_edit', array('id' => $tagId)),301);
	}

	/** @Route("/admin-tag-total/{tagId}", name="admin_tag_total", options={"expose":true}) */
	public function tagTotalAction($tagId)
	{
		$total = $this->get('drug.tag_total')->count($tagId);

		return new JsonResponse($total);
	}

	/** @Route("/admin-tag-recalc", name="admin_tag_recalc", options={"expose":true}) */
	public function tagRecalcAction()
	{
		$em         = $this->getDoctrine()->getManager('drug');
		$tags       = $em->getRepository('VidalDrugBundle:Tag')->findAll();
		$tagService = $this->get('drug.tag_total');

		foreach ($tags as $tag) {
			$tagService->count($tag->getId());
		}

		return $this->redirect($this->generateUrl('admin_vidal_drug_tag_list'));
	}

	/** @Route("/admin-tag-editable", name="admin_tag_editable", options={"expose":true}) */
	public function tagEditableAction(Request $request)
	{
		$id = $request->request->get('id', null);
		$em = $this->getDoctrine()->getManager('drug');

		$tag = $em->getRepository('VidalDrugBundle:Tag')->findOneById($id);

		if (!$tag) {
			throw $this->createNotFoundException();
		}

		$text = trim($request->request->get('value'));

		if (!empty($text)) {
			$tag->setText($text);
			$em->flush($tag);
		}

		return new Response($text);
	}

	/** @Route("/admin-tag-search", name="admin_tag_search", options={"expose":true}) */
	public function tagSearchAction(Request $request)
	{
		$id = $request->request->get('id', null);
		$em = $this->getDoctrine()->getManager('drug');

		$tag = $em->getRepository('VidalDrugBundle:Tag')->findOneById($id);

		if (!$tag) {
			throw $this->createNotFoundException();
		}

		$search = trim($request->request->get('value'));

		if (!empty($search)) {
			$tag->setSearch($search);
			$em->flush($tag);
		}

		return new Response($search);
	}

	/** @Route("/admin-user-restrict/{userId}", name="admin_user_restrict", options={"expose":true}) */
	public function userRestrictAction($userId)
	{
		$em   = $this->getDoctrine()->getManager();
		$user = $em->getRepository('VidalMainBundle:User')->findOneById($userId);

		if (!$user) {
			return $this->createNotFoundException();
		}

		$this->get('email.service')->send(
			$user->getUsername(),
			array('VidalMainBundle:Email:admin_user_restrict.html.twig', array('user' => $user, 'adminEmail' => 'support@vidal.ru')),
			'Сертификат специалиста не действителен',
			'support@vidal.ru'
		);

		$user->addCountRestrictedSent();
		$em->flush($user);

		return new JsonResponse($user->getCountRestrictedSent());
	}

	/** @Route("/admin-user-confirm/{userId}", name="admin_user_confirm", options={"expose":true}) */
	public function userConfirmAction($userId)
	{
		$em   = $this->getDoctrine()->getManager();
		$user = $em->getRepository('VidalMainBundle:User')->findOneById($userId);

		if (!$user) {
			return $this->createNotFoundException();
		}

		$this->get('email.service')->send(
			$user->getUsername(),
			array('VidalMainBundle:Email:admin_user_confirm.html.twig', array('user' => $user, 'adminEmail' => 'support@vidal.ru')),
			'Сертификат специалиста подтвержден',
			'support@vidal.ru'
		);

		$user->addCountConfirmationSent();
		$em->flush($user);

		return new JsonResponse($user->getCountConfirmationSent());
	}

	/** @Route("/atc-add/{type}/{id}/{ATCCode}", name="atc_add", options={"expose"=true}) */
	public function atcAdd($type, $id, $ATCCode)
	{
		$em     = $this->getDoctrine()->getManager('drug');
		$entity = $em->getRepository("VidalDrugBundle:$type")->findOneById($id);
		$atc    = $em->getRepository('VidalDrugBundle:ATC')->findOneByATCCode($ATCCode);

		if ($entity && $atc) {
			$entity->addAtcCode($atc);
			$em->flush();

			return new JsonResponse('OK');
		}

		return new JsonResponse('FAIL');
	}

	/** @Route("/atc-remove/{type}/{id}/{ATCCode}", name="atc_remove", options={"expose"=true}) */
	public function atcRemove($type, $id, $ATCCode)
	{
		$em     = $this->getDoctrine()->getManager('drug');
		$entity = $em->getRepository("VidalDrugBundle:$type")->findOneById($id);
		$atc    = $em->getRepository('VidalDrugBundle:ATC')->findOneByATCCode($ATCCode);

		if ($entity && $atc) {
			$entity->removeAtcCode($atc);
			$em->flush();

			return new JsonResponse('OK');
		}

		return new JsonResponse('FAIL');
	}

	/** @Route("/nozology-add/{type}/{id}/{NozologyCode}", name="nozology_add", options={"expose"=true}) */
	public function nozologyAdd($type, $id, $NozologyCode)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$entity   = $em->getRepository("VidalDrugBundle:$type")->findOneById($id);
		$nozology = $em->getRepository('VidalDrugBundle:Nozology')->findOneByNozologyCode($NozologyCode);

		if ($entity && $nozology) {
			$entity->addNozology($nozology);
			$em->flush();

			return new JsonResponse('OK');
		}

		return new JsonResponse('FAIL');
	}

	/** @Route("/nozology-remove/{type}/{id}/{NozologyCode}", name="nozology_remove", options={"expose"=true}) */
	public function nozologyRemove($type, $id, $NozologyCode)
	{
		$em     = $this->getDoctrine()->getManager('drug');
		$entity = $em->getRepository("VidalDrugBundle:$type")->findOneById($id);
		$atc    = $em->getRepository('VidalDrugBundle:Nozology')->findOneByNozologyCode($NozologyCode);

		if ($entity && $atc) {
			$entity->removeNozology($atc);
			$em->flush();

			return new JsonResponse('OK');
		}

		return new JsonResponse('FAIL');
	}

	/** @Route("/molecule-add/{type}/{id}/{MoleculeID}", name="molecule_add", options={"expose"=true}) */
	public function moleculeAdd($type, $id, $MoleculeID)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$entity   = $em->getRepository("VidalDrugBundle:$type")->findOneById($id);
		$molecule = $em->getRepository('VidalDrugBundle:Molecule')->findOneByMoleculeID($MoleculeID);

		if ($entity && $molecule) {
			$entity->addMolecule($molecule);
			$em->flush();

			return new JsonResponse('OK');
		}

		return new JsonResponse('FAIL');
	}

	/** @Route("/molecule-remove/{type}/{id}/{MoleculeID}", name="molecule_remove", options={"expose"=true}) */
	public function moleculeRemove($type, $id, $MoleculeID)
	{
		$em       = $this->getDoctrine()->getManager('drug');
		$entity   = $em->getRepository("VidalDrugBundle:$type")->findOneById($id);
		$molecule = $em->getRepository('VidalDrugBundle:Molecule')->findOneByMoleculeID($MoleculeID);

		if ($entity && $molecule) {
			$entity->removeMolecule($molecule);
			$em->flush();

			return new JsonResponse('OK');
		}

		return new JsonResponse('FAIL');
	}

	/** @Route("/infopage-add/{type}/{id}/{InfoPageID}", name="infopage_add", options={"expose"=true}) */
	public function infopageAdd($type, $id, $InfoPageID)
	{
		$em     = $this->getDoctrine()->getManager('drug');
		$entity = $em->getRepository("VidalDrugBundle:$type")->findOneById($id);
		$ip     = $em->getRepository('VidalDrugBundle:InfoPage')->findOneByInfoPageID($InfoPageID);

		if ($entity && $ip) {
			$entity->addInfoPage($ip);
			$em->flush();

			return new JsonResponse('OK');
		}

		return new JsonResponse('FAIL');
	}

	/** @Route("/infopage-remove/{type}/{id}/{InfoPageID}", name="infopage_remove", options={"expose"=true}) */
	public function infopageRemove($type, $id, $InfoPageID)
	{
		$em     = $this->getDoctrine()->getManager('drug');
		$entity = $em->getRepository("VidalDrugBundle:$type")->findOneById($id);
		$ip     = $em->getRepository('VidalDrugBundle:InfoPage')->findOneByInfoPageID($InfoPageID);

		if ($entity && $ip) {
			$entity->removeInfoPage($ip);
			$em->flush();

			return new JsonResponse('OK');
		}

		return new JsonResponse('FAIL');
	}

	/** @Route("/admin/tag-export", name="admin_tag_export", options={"expose"=true}) */
	public function tagExportAction()
	{
		$em   = $this->getDoctrine()->getManager('drug');
		$tags = $em->getRepository('VidalDrugBundle:Tag')->export();

		$phpExcelObject = $this->get('phpexcel')->createPHPExcelObject();
		$phpExcelObject->getProperties()->setCreator("Evrika")
			->setTitle("Vidal.ru - все теги")
			->setSubject("Vidal.ru - все теги");

		$phpExcelObject->setActiveSheetIndex(0)
			->setCellValue('A1', 'Текст')
			->setCellValue('B1', 'Выставляется')
			->setCellValue('C1', 'Представительство')
			->setCellValue('D1', 'Для компании')
			->setCellValue('E1', 'Всего')
			->setCellValue('F1', 'Статей энциклопедии')
			->setCellValue('G1', 'Статей специалистам')
			->setCellValue('H1', 'Новостей')
			->setCellValue('I1', 'Новостей фарм. компаний');

		$worksheet = $phpExcelObject->getActiveSheet();
		$letters   = explode(' ', 'A B C D E F G H I');

		foreach ($letters as $letter) {
			$worksheet->getColumnDimension($letter)->setAutoSize('true');
			$worksheet->getStyle($letter . '1')->getFont()->getColor()->setRGB('FF0000');
		}

		for ($i = 0; $i < count($tags); $i++) {
			$phpExcelObject->setActiveSheetIndex(0)
				->setCellValue('A' . ($i + 2), $tags[$i]['text'])
				->setCellValue('B' . ($i + 2), $tags[$i]['search'])
				->setCellValue('C' . ($i + 2), $tags[$i]['infoPage'])
				->setCellValue('D' . ($i + 2), $tags[$i]['forCompany'] ? 'Да' : 'Нет')
				->setCellValue('E' . ($i + 2), $tags[$i]['total'])
				->setCellValue('F' . ($i + 2), $tags[$i]['articles'])
				->setCellValue('G' . ($i + 2), $tags[$i]['arts'])
				->setCellValue('H' . ($i + 2), $tags[$i]['publications'])
				->setCellValue('I' . ($i + 2), $tags[$i]['pharmArticles']);
		}

		$phpExcelObject->getActiveSheet()->setTitle('Simple');

		// Set active sheet index to the first sheet, so Excel opens this as the first sheet
		$phpExcelObject->setActiveSheetIndex(0);

		// create the writer
		$writer = $this->get('phpexcel')->createWriter($phpExcelObject, 'Excel5');
		// create the response
		$response = $this->get('phpexcel')->createStreamedResponse($writer);
		// adding headers
		$response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
		$response->headers->set('Content-Disposition', 'attachment;filename=vidal_tags.xls');
		$response->headers->set('Pragma', 'public');
		$response->headers->set('Cache-Control', 'maxage=1');

		return $response;
	}

	/** @Route("/excel-users/{number}", name="excel_users", options={"expose"=true}) */
	public function excelUsersAction($number = null)
	{
		# имя для скачки
		$name = 'Отчет Vidal - ';
		if (!$number) {
			$name .= 'по всем пользователям';
		}
		elseif ($number > 2000) {
			$name .= "за $number год";
		}
		else {
			$name .= 'за ' . $this->getMonthName($number) . ' ' . date('Y') . ' года';
		}
		$name .= '.xlsx';

		if (!$this->get('security.context')->isGranted('ROLE_DOCTOR')) {
			return $this->redirect($this->generateUrl('no_download', array('filename' => $name)),301);
		}

		# путь файла
		if ($this->get('kernel')->getEnvironment() == 'dev') {
			$file = $this->get('kernel')->getRootDir() . DIRECTORY_SEPARATOR . '..'
				. DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR
				. ($number ? "users_{$number}.xlsx" : 'users.xlsx');
		}
		else {
			$file = '/home/twigavid/vidal/download/' . ($number ? "users_{$number}.xlsx" : 'users.xlsx');
		}

		header('X-Sendfile: ' . $file);
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $name . '"');
		exit;
	}

	public function getMonthName($month)
	{
		switch ($month) {
			case 1:
				return 'Январь';
			case 2:
				return 'Февраль';
			case 3:
				return 'Март';
			case 4:
				return 'Апрель';
			case 5:
				return 'Май';
			case 6:
				return 'Июнь';
			case 7:
				return 'Июль';
			case 8:
				return 'Август';
			case 9:
				return 'Сентябрь';
			case 10:
				return 'Октябрь';
			case 11:
				return 'Ноябрь';
			case 12:
				return 'Декабрь';
			default:
				return '';
		}
	}
}