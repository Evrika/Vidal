<?php
namespace Vidal\MainBundle\Admin;

use Sonata\AdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Form\FormMapper;

class BannerAdmin extends Admin
{
	protected $datagridValues;

	public function __construct($code, $class, $baseControllerName)
	{
		parent::__construct($code, $class, $baseControllerName);

		if (!$this->hasRequest()) {
			$this->datagridValues = array(
				'_page'       => 1,
				'_per_page'   => 25,
				'_sort_order' => 'DESC', // sort direction
				'_sort_by'    => 'created' // field name
			);
		}
	}

	protected function configureShowField(ShowMapper $showMapper)
	{
		$showMapper
			->add('id')
			->add('link', null, array('label' => 'Ссылка'))
			->add('group', null, array('label' => 'Баннерное место'))
			->add('clicks', null, array('label' => 'Переходов'))
			->add('displayed', null, array('label' => 'Показов'))
			->add('expires', null, array('label' => 'Осталось показов'))

			->add('presence', null, array('label' => 'Приоритет появления'))
			->add('enabled', null, array('label' => 'Активен'))
			->add('starts', null, array('label' => 'Дата начала'))
			->add('ends', null, array('label' => 'Дата окончания'))
			->add('created', null, array('label' => 'Дата создания', 'widget' => 'single_text', 'format' => 'd.m.Y в H:i'))
			->add('updated', null, array('label' => 'Дата обновления', 'widget' => 'single_text', 'format' => 'd.m.Y в H:i'));
	}

	protected function configureFormFields(FormMapper $formMapper)
	{
		$formMapper
			->add('banner', 'iphp_file', array('label' => 'Баннер'))
			->add('link', null, array('label' => 'Ссылка', 'required' => true))
			->add('group', null, array('label' => 'Баннерное место', 'required' => true))
			->add('expires', null, array('label' => 'Осталось показов', 'help' => 'Оставьте пустым, чтоб не учитывать'))
            ->add('limitDay', null, array('label' => 'Лимит показов в день'))
            ->add('clickDay', null, array('label' => 'Осталось показов в день'))
			->add('presence', null, array('label' => 'Приоритет', 'help' => 'Как часто появляется: 1-100%. Оставьте пустым, если без приоритета'))
			->add('enabled', null, array('label' => 'Активен', 'required' => false))
			->add('starts', null, array('label' => 'Дата начала'))
			->add('ends', null, array('label' => 'Дата окончания', 'help' => 'Оставьте пустым, чтоб не учитывать'))
			->add('reference', null, array('label' => 'Заменяется баннером', 'required' => false, 'help' => 'Будет заменяться этим баннером'));
	}

	protected function configureDatagridFilters(DatagridMapper $datagridMapper)
	{
		$datagridMapper
			->add('id')
			->add('link', null, array('label' => 'Ссылка'))
			->add('group', null, array('label' => 'Баннерное место'))
			->add('clicks', null, array('label' => 'Переходов'))
			->add('displayed', null, array('label' => 'Показов'))
			->add('expires', null, array('label' => 'Осталось показов'))
			->add('presence', null, array('label' => 'Приоритет появления'))
			->add('enabled', null, array('label' => 'Активен'))
			->add('starts', null, array('label' => 'Дата начала'))
			->add('ends', null, array('label' => 'Дата окончания'));
	}

	protected function configureListFields(ListMapper $listMapper)
	{
		$listMapper
			->add('id')
			->add('link', null, array('label' => 'Ссылка'))
			->add('group', null, array('label' => 'Баннерное место'))
			->add('clicks', null, array('label' => 'Переходов'))
			->add('displayed', null, array('label' => 'Показов'))
			->add('expires', null, array('label' => 'Осталось показов'))

			->add('presence', null, array('label' => 'Приоритет %'))
			->add('starts', null, array('label' => 'Дата начала', 'widget' => 'single_text', 'format' => 'd.m.Y в H:i'))
			->add('ends', null, array('label' => 'Дата окончания', 'widget' => 'single_text', 'format' => 'd.m.Y в H:i'))
			->add('reference', null, array('label' => 'Заменяется'))
			->add('enabled', null, array('label' => 'Активен', 'template' => 'VidalDrugBundle:Sonata:swap_enabled_main.html.twig'))
			->add('_action', 'actions', array(
				'label'   => 'Действия',
				'actions' => array(
					'show'   => array(),
					'edit'   => array(),
					'delete' => array(),
				)
			));
	}
}