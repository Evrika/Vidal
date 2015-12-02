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
			'name' => array('type' => 'string', 'include_in_all' => TRUE),
			'type' => array('type' => 'string', 'include_in_all' => FALSE),
		));

		// Send mapping to type
		$mapping->send();

		# записываем в ElasticSearch документы автодополнения
		$this->save($productNames, 'product', $output, $elasticaType);
		$this->save($moleculeNames, 'molecule', $output, $elasticaType);
		$this->save($companyNames, 'company', $output, $elasticaType);
		$this->save($atcNames, 'atc', $output, $elasticaType);

		$output->writeln("+++ vidal:autocomplete completed!");
	}

	private function save($items, $type, $output, $elasticaType)
	{
		$documents = array();

		for ($i = 0; $i < count($items); $i++) {
			$documents[] = new \Elastica\Document(null, array(
				'name' => $items[$i],
				'type' => $type,
			));

			if ($i && $i % 500 == 0 && count($documents) > 0) {
				$elasticaType->addDocuments($documents);
				$elasticaType->getIndex()->refresh();
				$documents = array();
			}
		}

		if(count($documents) > 0) {
			$elasticaType->addDocuments($documents);
		}
		$elasticaType->getIndex()->refresh();
	}
}