<?php

namespace Lle\PdfGeneratorBundle\DependencyInjection;

use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('lle_pdf_generator');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('default_generator')
                    ->defaultValue('word_to_pdf')
                ->end()
                ->scalarNode('path')
                    ->defaultValue('data/pdfmodel')
                ->end()
                ->scalarNode('class')
                    ->defaultValue(PdfModel::class)
                ->end()
                ->scalarNode('unoserver')
                    ->defaultValue('http://unoserver/convert')
                ->end()
                ->arrayNode('screens')
                    ->info('Admin screen of the PDF templates, added when 2lenet/crudit-bundle is installed (the menu entry stays in the project)')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('crudit')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('crudit_report templates: true once crudit, Typst and the designer are installed in the project')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('bin')
                            ->defaultValue('crudit')
                        ->end()
                        ->scalarNode('typst')
                            ->defaultValue('typst')
                        ->end()
                        ->arrayNode('allowed_hosts')
                            ->scalarPrototype()->end()
                        ->end()
                        ->integerNode('timeout')
                            ->defaultValue(90)
                        ->end()
                        ->scalarNode('designer_url')
                            ->defaultValue('/crudit-designer/index.html')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
