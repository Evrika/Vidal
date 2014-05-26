<?php
namespace Vidal\DrugBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Команда генерации названий для автодополнения поисковой строки
 *
 * @package Vidal\DrugBundle\Command
 */
class AutocompleteCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this->setName('vidal:autocomplete')
			->setDescription('Creates autocomplete in Elastica');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		ini_set('memory_limit', -1);
		$output->writeln('--- vidal:autocomplete started');

		$em = $this->getContainer()->get('doctrine')->getManager('drug');

		$productNames  = $em->getRepository('VidalDrugBundle:Product')->findAutocomplete();
		$moleculeNames = $em->getRepository('VidalDrugBundle:Molecule')->findAutocomplete();
		$companyNames  = $em->getRepository('VidalDrugBundle:Company')->findAutocomplete();
		$atcNames      = $em->getRepository('VidalDrugBundle:ATC')->findAutocomplete();

		$output->writeln('count product : ' . count($productNames));
		$output->writeln('count molecule : ' . count($moleculeNames));
		$output->writeln('count company : ' . count($companyNames));
		$output->writeln('count atc : ' . count($atcNames));
		exit;

		$names = array_unique(array_merge($productNames, $moleculeNames));
		sort($names);

		$elasticaClient = new \Elastica\Client();
		$elasticaIndex  = $elasticaClient->getIndex('website');
		$elasticaType   = $elasticaIndex->getType('autocomplete');

		if ($elasticaType->exists()) {
			$elasticaType->delete();
		}

		// Define mapping
		$mapping = new \Elastica\Type\Mapping();
		$mapping->setType($elasticaType);

		// Set mapping
		$mapping->setProperties(array(
			'id'   => array('type' => 'integer', 'include_in_all' => FALSE),
			'name' => array('type' => 'string', 'include_in_all' => TRUE),
			'type' => array('type' => 'string', 'include_in_all' => FALSE),
		));

		// Send mapping to type
		$mapping->send();

		# записываем на сервер документы автодополнения
		$documents = array();

		for ($i = 0; $i < count($names); $i++) {
			$documents[] = new \Elastica\Document($i + 1, array('name' => $names[$i]));

			if ($i && $i % 500 == 0) {
				$elasticaType->addDocuments($documents);
				$elasticaType->getIndex()->refresh();
				$documents = array();
			}
		}
		$elasticaType->addDocuments($documents);
		$elasticaType->getIndex()->refresh();

		$output->writeln("+++ vidal:autocomplete loaded $i documents!");
	}
}