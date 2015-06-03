<?php
namespace Vidal\MainBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExcelUsersCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this->setName('vidal:excel_users')
			->addArgument('numbers', InputArgument::IS_ARRAY, 'Number of year or month');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		ini_set('memory_limit', -1);
		ini_set('max_execution_time', 0);

		$em             = $this->getContainer()->get('doctrine')->getManager();
		$phpExcelObject = $this->getContainer()->get('phpexcel')->createPHPExcelObject();
		$numbers        = $input->getArgument('numbers');
		$number         = empty($numbers) ? null : intval($numbers[0]);
		$users          = $em->getRepository('VidalMainBundle:User')->forExcel($number);

		$phpExcelObject->getProperties()->setCreator('Vidal.ru')
			->setLastModifiedBy('Vidal.ru')
			->setTitle('Зарегистрированные пользователи Видаля')
			->setSubject('Зарегистрированные пользователи Видаля');

		$phpExcelObject->getDefaultStyle()
			->getAlignment()
			->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);

		$phpExcelObject->setActiveSheetIndex(0);
		$worksheet = $phpExcelObject->getActiveSheet();

		$specialties = array();
		$regions     = array();
		$cities      = array();
		$profs       = array();

		for ($i = 0; $i < count($users); $i++) {
			# заполняем массив по специальности
			$key = $users[$i]['specialty'];
			if (!empty($key)) {
				$specialties[$key] = isset($specialties[$key]) ? $specialties[$key] + 1 : 1;

				if (in_array($key, array('Клиническая фармакология', 'Провизор', 'Фармацевтика'))) {
					$k         = 'специалисты в области фармации (Клиническая фармакология / Провизор / Фармацевтика)';
					$profs[$k] = isset($profs[$k]) ? $profs[$k] + 1 : 1;
				}
				elseif (in_array($key, array('Средний медицинский персонал', 'Фельдшерское дело'))) {
					$k         = 'средний медицинский персонал (Средний медицинский персонал / Фельдшерское дело)';
					$profs[$k] = isset($profs[$k]) ? $profs[$k] + 1 : 1;
				}
				elseif (in_array($key, array('Администрация ЛПУ', 'Медико-социальная экспертиза', 'Организация здравоохранения и общественное здоровье', 'Фарм.индустрия'))) {
					$k         = 'административный персонал (Администрация ЛПУ / Медико-социальная экспертиза / Организация здравоохранения и общественное здоровье / Фарм.индустрия)';
					$profs[$k] = isset($profs[$k]) ? $profs[$k] + 1 : 1;
				}
				elseif (in_array($key, array('Студент ВУЗа', 'Студент ССУЗа'))) {
					$k         = 'студенты';
					$profs[$k] = isset($profs[$k]) ? $profs[$k] + 1 : 1;
				}
				elseif (in_array($key, array('Айти-специалист в медицине', 'Информационные технологии', 'Химия', 'Биология', 'Ветеринария'))) {
					$k         = 'прочие (Айти-специалист в медицине / Информационные технологии / Химия / Биология / Ветеринария)';
					$profs[$k] = isset($profs[$k]) ? $profs[$k] + 1 : 1;
				}
				else {
					$k         = 'врач';
					$profs[$k] = isset($profs[$k]) ? $profs[$k] + 1 : 1;
				}
			}

			# заполняем массив по региону
			$key = $users[$i]['region'];
			if (!empty($key)) {
				$regions[$key] = isset($regions[$key]) ? $regions[$key] + 1 : 1;
			}

			$key = $users[$i]['city'];
			if (!empty($key)) {
				$cities[$key] = isset($cities[$key]) ? $cities[$key] + 1 : 1;
			}
		}

		# заполняем первую страницу
		$this->firstColumn($worksheet, 'Все пользователи');
		$this->populateWorksheet($worksheet, $users);

		# заполняем вторую страницу со статистикой
		$newsheet = $phpExcelObject->createSheet(NULL, 1);
		$phpExcelObject->setActiveSheetIndex(1);

		arsort($specialties);
		arsort($regions);
		arsort($cities);
		arsort($profs);

		$newsheet
			->setTitle('Сводная статистика')
			->setCellValue('A1', 'Профессия')
			->setCellValue('B1', '')
			->setCellValue('C1', 'Специальность')
			->setCellValue('D1', '')
			->setCellValue('E1', 'Регион')
			->setCellValue('F1', '')
			->setCellValue('G1', 'Город')
			->setCellValue('H1', '');

		$alphabet = explode(' ', 'A B C D E F G H');

		foreach ($alphabet as $letter) {
			$newsheet->getColumnDimension($letter)->setWidth('30');
			$newsheet->getStyle($letter . '1')->applyFromArray(array(
				'fill' => array(
					'type'  => \PHPExcel_Style_Fill::FILL_SOLID,
					'color' => array('rgb' => 'FF0000')
				),
				'font' => array(
					'bold'  => true,
					'color' => array('rgb' => 'FFFFFF'),
					'size'  => 13,
					'name'  => 'Verdana',
				)
			));
		}

		$i = 2;
		foreach ($profs as $prof => $qty) {
			$newsheet->setCellValue("A{$i}", $prof)->setCellValue("B{$i}", $qty);
			$i++;
		}

		$i = 2;
		foreach ($specialties as $specialty => $qty) {
			$newsheet->setCellValue("C{$i}", $specialty)->setCellValue("D{$i}", $qty);
			$i++;
		}

		$i = 2;
		foreach ($regions as $region => $qty) {
			$newsheet->setCellValue("E{$i}", $region)->setCellValue("F{$i}", $qty);
			$i++;
		}

		$i = 2;
		foreach ($cities as $city => $qty) {
			$newsheet->setCellValue("G{$i}", $city)->setCellValue("H{$i}", $qty);
			$i++;
		}

		###################################################################################################
		$phpExcelObject->setActiveSheetIndex(0);

		$file = $this->getContainer()->get('kernel')->getRootDir() . DIRECTORY_SEPARATOR . '..'
			. DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR
			. ($number ? "users_{$number}.xlsx" : 'users.xlsx');

		$writer = $this->getContainer()->get('phpexcel')->createWriter($phpExcelObject, 'Excel2007');
		$writer->save($file);

		$output->writeln('+++ vidal:excel_users completed!');
	}

	private function firstColumn($worksheet, $title)
	{
		$worksheet
			->setTitle($title)
			->setCellValue('A1', 'Специальность')
			->setCellValue('B1', 'Город')
			->setCellValue('C1', 'Регион')
			->setCellValue('D1', 'Зарегистр.')
			->setCellValue('E1', 'Почтовый адрес')
			->setCellValue('F1', 'ФИО');

		$alphabet = explode(' ', 'A B C D E F');

		foreach ($alphabet as $letter) {
			$worksheet->getColumnDimension($letter)->setWidth('30');
			$worksheet->getStyle($letter . '1')->applyFromArray(array(
				'fill' => array(
					'type'  => \PHPExcel_Style_Fill::FILL_SOLID,
					'color' => array('rgb' => 'FF0000')
				),
				'font' => array(
					'bold'  => true,
					'color' => array('rgb' => 'FFFFFF'),
					'size'  => 13,
					'name'  => 'Verdana',
				)
			));
		}
	}

	private function populateWorksheet($worksheet, $users)
	{
		for ($i = 0; $i < count($users); $i++) {
			$index = $i + 2;
			$name  = $users[$i]['lastName'] . ' ' . $users[$i]['firstName'];
			if (!empty($users[$i]['surName'])) {
				$name .= ' ' . $users[$i]['surName'];
			}

			$worksheet
				->setCellValue("A{$index}", $users[$i]['specialty'])
				->setCellValue("B{$index}", $users[$i]['city'])
				->setCellValue("C{$index}", $users[$i]['region'])
				->setCellValue("D{$index}", $users[$i]['registered'])
				->setCellValue("E{$index}", $users[$i]['username'])
				->setCellValue("F{$index}", $name);
		}
	}
}