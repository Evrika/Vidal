<?php
namespace Vidal\MainBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SitemapGeneratorCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this->setName('vidal:sitemap:generate');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		ini_set('memory_limit', -1);
		$output->writeln('--- vidal:sitemap:generate started');

		$container = $this->getContainer();
		$em        = $container->get('doctrine')->getManager('drug');
		$emDefault = $container->get('doctrine')->getManager();
		$webRoot   = $container->get('kernel')->getRootDir() . "/../web";

		////////////////////////////////////////////
		$urlset  = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" /><!--?xml version="1.0" encoding="UTF-8"?-->');
		$urlset2 = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" /><!--?xml version="1.0" encoding="UTF-8"?-->');
		$urlset3 = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" /><!--?xml version="1.0" encoding="UTF-8"?-->');

		$date    = new \DateTime();
		$lastMod = $date->format('Y-m-d');

		$xmlMain = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>
			<sitemapindex xmlns="http://www.google.com/schemas/sitemap/0.84">
			 <sitemap>
				<loc>http://www.vidal.ru/sitemap1.xml</loc>
				<lastmod>' . $lastMod . '</lastmod>
			 </sitemap>
			 <sitemap>
				<loc>http://www.vidal.ru/sitemap2.xml</loc>
				<lastmod>' . $lastMod . '</lastmod>
			 </sitemap>
			 <sitemap>
				<loc>http://www.vidal.ru/sitemap3.xml</loc>
				<lastmod>' . $lastMod . '</lastmod>
			 </sitemap>
			</sitemapindex>
		');

		$xmlMain->asXML("{$webRoot}/sitemap.xml");

		# главная
		$url = $urlset->addChild('url');
		$url->addChild('loc', 'http://www.vidal.ru/sitemap1.xml');
		$url->addChild('lastmod', $lastMod);
		$url->addChild('changefreq', 'monthly');
		$url->addChild('priority', '1');

		# материалы
		$url = $urlset->addChild('url');
		$url->addChild('loc', 'http://www.vidal.ru/sitemap2.xml');
		$url->addChild('lastmod', $lastMod);
		$url->addChild('changefreq', 'daily');
		$url->addChild('priority', '1');

		# картинка-1
		$url   = $urlset->addChild('url');
		$image = $url->addChild('image:image', null, 'http://www.google.com/schemas/sitemap-image/1.1');
		$image->addChild('image:loc', 'http://www.vidal.ru/bundles/vidalmain/images/header.jpg', 'http://www.google.com/schemas/sitemap-image/1.1');
		$image->addChild('image:caption', 'Видаль', 'http://www.google.com/schemas/sitemap-image/1.1');

		# картинка-2
		$url   = $urlset->addChild('url');
		$image = $url->addChild('image:image', null, 'http://www.google.com/schemas/sitemap-image/1.1');
		$image->addChild('image:loc', 'http://www.vidal.ru/bundles/vidalmain/images/vidal-group.jpg', 'http://www.google.com/schemas/sitemap-image/1.1');
		$image->addChild('image:caption', 'Группа Видаль', 'http://www.google.com/schemas/sitemap-image/1.1');

		# препараты
		$products = $em->createQuery('
					SELECT p.ProductID, p.Name
					FROM VidalDrugBundle:Product p
					WHERE p.MarketStatusID IN (1,2,7)
						AND p.ProductTypeCode IN (\'DRUG\',\'GOME\')
						AND p.inactive = FALSE
				')->getResult();

		foreach ($products as $product) {
			$url = $urlset->addChild('url');
			$loc = "http://www.vidal.ru/drugs/{$product['Name']}__{$product['ProductID']}";
			$url->addChild('loc', $loc);
			$url->addChild('lastmod', $lastMod);
			$url->addChild('changefreq', 'monthly');
			$url->addChild('priority', '0.8');
		}

		# активные вещества
		$molecules = $em->createQuery('
					SELECT m.MoleculeID
					FROM VidalDrugBundle:Molecule m
					JOIN m.documents d
					WHERE m.MoleculeID NOT IN (1144,2203)
				')->getResult();

		foreach ($molecules as $molecule) {
			$url = $urlset->addChild('url');
			$loc = "http://www.vidal.ru/drugs/molecule/{$molecule['MoleculeID']}";
			$url->addChild('loc', $loc);
			$url->addChild('lastmod', $lastMod);
			$url->addChild('changefreq', 'monthly');
			$url->addChild('priority', '0.8');
		}

		# статьи энциклопедии
		$articles = $em->createQuery('
					SELECT a
					FROM VidalDrugBundle:Article a
					JOIN a.rubrique r
					WHERE a.enabled = TRUE
				')->getResult();

		foreach ($articles as $article) {
			if ($article->getRubrique() && $article->getRubrique()->getEnabled() && $article->getEnabled()) {
				$url      = $urlset2->addChild('url');
				$rubrique = $article->getRubrique()->getRubrique();
				$link     = $article->getLink();
				$loc      = "http://www.vidal.ru/encyclopedia/{$rubrique}/{$link}";
				$url->addChild('loc', $loc);
				$url->addChild('lastmod', $lastMod);
				$url->addChild('changefreq', 'daily');
				$url->addChild('priority', '0.8');
			}
		}

		# новости
		$publications = $em->createQuery('
					SELECT p.id, p.enabled
					FROM VidalDrugBundle:Publication p
					WHERE p.enabled = TRUE
				')->getResult();

		foreach ($publications as $publication) {
			if ($publication['enabled']) {
				$url = $urlset2->addChild('url');
				$loc = "http://www.vidal.ru/novosti/{$publication['id']}";
				$url->addChild('loc', $loc);
				$url->addChild('lastmod', $lastMod);
				$url->addChild('changefreq', 'daily');
				$url->addChild('priority', '0.8');
			}
		}

		# компании
		$companies = $em->createQuery('
			SELECT c.CompanyID
			FROM VidalDrugBundle:Company c
		')->getResult();

		foreach ($companies as $company) {
			$url = $urlset->addChild('url');
			$loc = "http://www.vidal.ru/drugs/firm/{$company['CompanyID']}";
			$url->addChild('loc', $loc);
			$url->addChild('lastmod', $lastMod);
			$url->addChild('changefreq', 'monthly');
			$url->addChild('priority', '0.8');
		}

		# представительства
		$infoPages = $em->createQuery('
			SELECT i.InfoPageID
			FROM VidalDrugBundle:InfoPage i
			WHERE i.countProducts > 0
		')->getResult();

		foreach ($infoPages as $infoPage) {
			$url = $urlset->addChild('url');
			$loc = "http://www.vidal.ru/drugs/company/{$infoPage['InfoPageID']}";
			$url->addChild('loc', $loc);
			$url->addChild('lastmod', $lastMod);
			$url->addChild('changefreq', 'monthly');
			$url->addChild('priority', '0.8');
		}

		# о компании
		$abouts = $emDefault->createQuery('
					SELECT a.url
					FROM VidalMainBundle:About a
					WHERE a.enabled = 1
				')->getResult();

		foreach ($abouts as $about) {
			$url = $urlset->addChild('url');
			$loc = "http://www.vidal.ru/about/{$about['url']}";
			$url->addChild('loc', $loc);
			$url->addChild('lastmod', $lastMod);
			$url->addChild('changefreq', 'weekly');
			$url->addChild('priority', '0.9');
		}

		# наши услуги
		$abouts = $emDefault->createQuery('
					SELECT a.url
					FROM VidalMainBundle:AboutService a
					WHERE a.enabled = 1
				')->getResult();

		foreach ($abouts as $about) {
			$url = $urlset->addChild('url');
			$loc = "http://www.vidal.ru/services/{$about['url']}";
			$url->addChild('loc', $loc);
			$url->addChild('lastmod', $lastMod);
			$url->addChild('changefreq', 'weekly');
			$url->addChild('priority', '0.9');
		}

		# школа гастрита
		$url = $urlset3->addChild('url');
		$loc = "http://www.vidal.ru/shkola-gastrita";
		$url->addChild('loc', $loc);
		$url->addChild('lastmod', $lastMod);
		$url->addChild('changefreq', 'daily');
		$url->addChild('priority', '1');

		$locs = array('online-test', 'besplatnaya-konsultaciya-gastroenterologa', 'video', 'blizhajshie-polikliniki');
		foreach ($locs as $loc) {
			$url = $urlset3->addChild('url');
			$url->addChild('loc', "http://www.vidal.ru/shkola-gastrita/$loc");
			$url->addChild('lastmod', $lastMod);
			$url->addChild('changefreq', 'weekly');
			$url->addChild('priority', '0.9');
		}

		$categories = $emDefault->getRepository('VidalMainBundle:ShkolaCategory')->findAll();
		foreach ($categories as $category) {
			$categoryUrl = $category->getUrl();
			$url = $urlset3->addChild('url');
			$url->addChild('loc', "http://www.vidal.ru/shkola-gastrita/$categoryUrl");
			$url->addChild('lastmod', $lastMod);
			$url->addChild('changefreq', 'weekly');
			$url->addChild('priority', '0.9');

			foreach ($category->getArticles() as $article) {
				if (!$article->getCategoryPage()) {
					$url = $urlset3->addChild('url');
					$url->addChild('loc', "http://www.vidal.ru/shkola-gastrita/$categoryUrl/{$article->getUrl()}");
					$url->addChild('lastmod', $lastMod);
					$url->addChild('changefreq', 'weekly');
					$url->addChild('priority', '0.9');
				}
			}
		}

		# запись в файл
		$urlset->asXML("{$webRoot}/sitemap1.xml");
		$urlset2->asXML("{$webRoot}/sitemap2.xml");
		$urlset3->asXML("{$webRoot}/sitemap3.xml");

		$dom = new DOMDocument();
		$dom->loadXML("{$webRoot}/sitemap1.xml");
		$dom->formatOutput = true;
		$formattedXML = $dom->saveXML();

		$fp = fopen("{$webRoot}/sitemap1.xml",'w+');
		fwrite($fp, $formattedXML);
		fclose($fp);

		///////////////////////////////////////////

		$output->writeln('+++ vidal:sitemap:generate completed');
	}
}