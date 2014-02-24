<?php
namespace Vidal\MainBundle\Service;

class TwigExtension extends \Twig_Extension
{
	public function getName()
	{
		return 'learning_twig_extension';
	}

	/**
	 * Return the functions registered as twig extensions
	 *
	 * @return array
	 */
	public function getFunctions()
	{
		return array(
			'is_file'         => new \Twig_Function_Method($this, 'is_file'),
			'dateFromMinutes' => new \Twig_Function_Method($this, 'dateFromMinutes'),
			'evrikaImg'       => new \Twig_Function_Method($this, 'evrikaImg'),
			'formatDate'      => new \Twig_Function_Method($this, 'formatDate'),
		);
	}

	/**
	 * Дополнительные фильтры
	 */
	public function getFilters()
	{
		return array(
			new \Twig_SimpleFilter('dateRu', array($this, 'dateRu')),
			new \Twig_SimpleFilter('shortcut', array($this, 'shortcut')),
			new \Twig_SimpleFilter('granted', array($this, 'granted')),
			new \Twig_SimpleFilter('dateCreated', array($this, 'dateCreated'))
		);
	}

	/**
	 * Вытаскивает и преобразует URL картинки из новостей EVRIKA
	 */
	public function evrikaImg($file)
	{
		if ($file == null) {
			return null;
		}
		elseif ($file == 'mynews') {
			return $file;
		}
		$array = unserialize($file);
		return 'http://evrika.ru' . $array['path'];
	}

	/**
	 * Проверить из твига наличие файла (слеши из начала и конца убираются)
	 *
	 * @param string $filename
	 * @return bool
	 */
	public function is_file($filename)
	{
		return file_exists(trim($filename, '/'));
	}

	public function dateFromMinutes($min)
	{
		$inputSeconds = $min * 60;

		$secondsInAMinute = 60;
		$secondsInAnHour  = 60 * $secondsInAMinute;
		$secondsInADay    = 24 * $secondsInAnHour;

		// extract days
		$days = floor($inputSeconds / $secondsInADay);

		// extract hours
		$hourSeconds = $inputSeconds % $secondsInADay;
		$hours       = floor($hourSeconds / $secondsInAnHour);

		// extract minutes
		$minuteSeconds = $hourSeconds % $secondsInAnHour;
		$minutes       = floor($minuteSeconds / $secondsInAMinute);

		// extract the remaining seconds
		$remainingSeconds = $minuteSeconds % $secondsInAMinute;
		$seconds          = ceil($remainingSeconds);

		return (int) $days . 'д ' . (int) $hours . 'ч ' . (int) $minutes . 'м';
	}

	public function formatDate($date, $showYear = true)
	{
		if (!$date) {
			return '';
		}

		$months = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

		return $date->format('d') . ' ' . $months[intval($date->format('m'))] . ' ' . $date->format('Y');
	}

	public function dateRu($date, $fullYear = false)
	{
		if (!$date) {
			return '';
		}

		$months  = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
		$dateStr = $date->format('d') . '&nbsp;' . $months[intval($date->format('m'))];

		if ($fullYear === true) {
			$dateStr .= '&nbsp;года';
		}
		elseif ($fullYear !== null) {
			$dateStr .= '&nbsp;' . $date->format('Y');
		}

		return $dateStr;
	}

	public function dateCreated($date)
	{
		if (!$date) {
			return '';
		}

		$months = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

		return $date->format('d') . ' ' . $months[intval($date->format('m'))] . ' в ' . $date->format('H:i');
	}

	public function shortcut($str, $max)
	{
		return mb_strlen($str, 'UTF-8') > $max
			? mb_substr($str, 0, $max, 'UTF-8') . '...'
			: $str;
	}

	public function granted(\Learning\MainBundle\Entity\User $user, $role)
	{
		return in_array($role, $user->getRoles());
	}
}