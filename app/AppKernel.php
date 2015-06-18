<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
			new FOS\JsRoutingBundle\FOSJsRoutingBundle(),
            new Vidal\DrugBundle\VidalDrugBundle(),
			new Knp\Bundle\PaginatorBundle\KnpPaginatorBundle(),
            new Knp\Bundle\MenuBundle\KnpMenuBundle(),
            new Sonata\CoreBundle\SonataCoreBundle(),
            new Sonata\BlockBundle\SonataBlockBundle(),
            new Sonata\jQueryBundle\SonatajQueryBundle(),
            new Sonata\DoctrineORMAdminBundle\SonataDoctrineORMAdminBundle(),
            new Sonata\AdminBundle\SonataAdminBundle(),
            new Vidal\MainBundle\VidalMainBundle(),
			new Lsw\SecureControllerBundle\LswSecureControllerBundle(),
			new Iphp\FileStoreBundle\IphpFileStoreBundle(),
            new Vidal\VeterinarBundle\VidalVeterinarBundle(),
            new Gregwar\CaptchaBundle\GregwarCaptchaBundle(),
			new Ornicar\ApcBundle\OrnicarApcBundle(),
			new Liuggio\ExcelBundle\LiuggioExcelBundle(),
			new SunCat\MobileDetectBundle\MobileDetectBundle(),
        );

        if (in_array($this->getEnvironment(), array('dev', 'test'))) {
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }
}
