<?php

namespace Lle\PdfGeneratorBundle\DependencyInjection;

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

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('lle.pdf.default_generator', $config['default_generator']);
        $container->setParameter('lle.pdf.path', $config['path']);
        $container->setParameter('lle.pdf.class', $config['class']);
        $container->setParameter('lle.pdf.unoserver', $config['unoserver']);
        $container->setParameter('lle.pdf.data_model', $config['data_models']);
    }
}
