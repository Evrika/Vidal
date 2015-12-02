<?php
namespace Vidal\DrugBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DropNonRusCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this->setName('vidal:drop_non_rus');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		ini_set('memory_limit', -1);
		$output->writeln('--- vidal:drop_non_rus');

		$em = $this->getContainer()->get('doctrine')->getManager('drug');
		$pdo = $em->getConnection();

		$stmt = $pdo->prepare('SET FOREIGN_KEY_CHECKS=0');
		$stmt->execute();

		$stmt = $pdo->prepare("DELETE FROM infopage WHERE CountryEditionCode != 'RUS'");
		$stmt->execute();

		$stmt = $pdo->prepare("DELETE FROM company WHERE CountryEditionCode != 'RUS'");
		$stmt->execute();

		$stmt = $pdo->prepare("DELETE FROM product WHERE CountryEditionCode != 'RUS'");
		$stmt->execute();

		$stmt = $pdo->prepare("DELETE FROM document WHERE YearEdition > 2016 OR (CountryEditionCode != 'RUS' AND ArticleID NOT IN (1,4,5))");
		$stmt->execute();

		$output->writeln("+++ vidal:drop_non_rus completed!");
	}
}