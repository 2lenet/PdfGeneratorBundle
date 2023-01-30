<?php

namespace Lle\PdfGeneratorBundle\DependencyInjection;

use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('lle_pdf_generator');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $nodeBuilder = $rootNode->children();

        $nodeBuilder->scalarNode('default_generator')->defaultValue('word_to_pdf')->end();
        $nodeBuilder->scalarNode('path')->defaultValue('data/pdfmodel')->end();
        $nodeBuilder->scalarNode('class')->defaultValue(PdfModel::class)->end();
        $nodeBuilder->arrayNode('data_models')->scalarPrototype()->end();

        return $treeBuilder;
    }
}
