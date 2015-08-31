<?php
namespace Vidal\DrugBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Команда редактирования Product.Composition
 *
 * @package Vidal\DrugBundle\Command
 */
class ProductCompositionCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this->setName('vidal:product_composition')
			->setDescription('Edits composition of products');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		ini_set('memory_limit', -1);
		$output->writeln('--- vidal:product_composition started');

		$em = $this->getContainer()->get('doctrine')->getManager('drug');

		# у продуктов с документом типа 2 надо проставить их описания
		/*$products = $em->createQuery('
			SELECT p.ProductID, d.CompiledComposition composition
			FROM VidalDrugBundle:Product p
			JOIN p.document d
			WHERE d.ArticleID = 2
		')->getResult();

		$query = $em->createQuery('
			UPDATE VidalDrugBundle:Product p
			SET p.Composition = :product_composition
			WHERE p = :product_id
		');

		foreach ($products as $product) {
			$query->setParameters(array(
				'product_composition' => $product['composition'],
				'product_id' => $product['ProductID'],
			))->execute();
		}*/
		
		# заменяем специальные символы в описаниях
		$products = $em->createQuery('
			SELECT p.ProductID, p.Composition
			FROM VidalDrugBundle:Product p
			WHERE p.Composition LIKE \'%&loz;%\' OR
				p.Composition LIKE \'%[PRING]%\'
		')->getResult();

		$query = $em->createQuery('
			UPDATE VidalDrugBundle:Product p
			SET p.Composition = :product_composition
			WHERE p = :product_id
		');

		for ($i = 0; $i < count($products); $i++) {
			$patterns     = array('/\[PRING\]/i', '/&loz;/i');
			$replacements = array('<i class"pring">Вспомогательные вещества</i>:', '');
			$composition  = preg_replace($patterns, $replacements, $products[$i]['Composition']);

			$query->setParameters(array(
				'product_composition' => $composition,
				'product_id'          => $products[$i]['ProductID'],
			))->execute();
		}

		$output->writeln('+++ vidal:product_composition completed!');
	}
}