<?php

namespace Lle\PdfGeneratorBundle\DependencyInjection;

use Lle\PdfGeneratorBundle\DataModel\CruditDataModelInterface;
use Lle\PdfGeneratorBundle\Generator\CruditReportGenerator;
use Lle\PdfGeneratorBundle\Generator\PdfGeneratorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class LlePdfGeneratorExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $container->registerForAutoconfiguration(PdfGeneratorInterface::class)->addTag('lle.pdf.generator');
        $container->registerForAutoconfiguration(CruditDataModelInterface::class)->addTag(CruditDataModelInterface::TAG);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('lle.pdf.default_generator', $config['default_generator']);
        $container->setParameter('lle.pdf.path', $config['path']);
        $container->setParameter('lle.pdf.class', $config['class']);
        $container->setParameter('lle.pdf.unoserver', $config['unoserver']);
        $container->setParameter('lle.pdf.crudit.enabled', $config['crudit']['enabled']);
        $container->setParameter('lle.pdf.crudit.bin', $config['crudit']['bin']);
        $container->setParameter('lle.pdf.crudit.typst', $config['crudit']['typst']);
        $container->setParameter('lle.pdf.crudit.allowed_hosts', $config['crudit']['allowed_hosts']);
        $container->setParameter('lle.pdf.crudit.timeout', $config['crudit']['timeout']);
        $container->setParameter('lle.pdf.crudit.designer_url', $config['crudit']['designer_url']);

        // crudit_report disabled: the generator stays a service (designer controller, form) but is not a template type
        if (!$config['crudit']['enabled']) {
            $container->getDefinition(CruditReportGenerator::class)->setAutoconfigured(false)->clearTag('lle.pdf.generator');
        }

        // templates admin screen: only when crudit-bundle is installed (optional dependency)
        $screens = $config['screens']['enabled'] && self::hasCrudit($container);
        $container->setParameter('lle.pdf.screens.enabled', $screens);
        if ($screens) {
            $loader->load('services_crudit.yaml');
        }
    }

    public static function hasCrudit(ContainerBuilder $container): bool
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];

        return isset($bundles['LleCruditBundle']);
    }
}
