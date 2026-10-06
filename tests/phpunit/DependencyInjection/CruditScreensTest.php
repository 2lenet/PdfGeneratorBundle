<?php

namespace Lle\PdfGeneratorBundle\Tests\DependencyInjection;

use Lle\CruditBundle\LleCruditBundle;
use Lle\PdfGeneratorBundle\Crudit\Config\PdfModelCrudConfig;
use Lle\PdfGeneratorBundle\Controller\Crudit\PdfModelController;
use Lle\PdfGeneratorBundle\DependencyInjection\LlePdfGeneratorExtension;
use Lle\PdfGeneratorBundle\Form\Crudit\PdfModelType;
use Lle\PdfGeneratorBundle\Generator\CruditReportGenerator;
use Lle\PdfGeneratorBundle\Routing\CruditRouteLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** The Crudit templates screen is loaded only if crudit-bundle is installed and the screen is not disabled. */
class CruditScreensTest extends TestCase
{
    /**
     * @param array<string, class-string> $bundles
     * @param array<string, mixed> $config
     */
    private function container(array $bundles, array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        (new LlePdfGeneratorExtension())->load([$config], $container);

        return $container;
    }

    public function testWithoutCruditNothingIsLoaded(): void
    {
        $container = $this->container([]);

        $this->assertFalse($container->getParameter('lle.pdf.screens.enabled'));
        $this->assertFalse($container->hasDefinition(PdfModelCrudConfig::class));
        $this->assertFalse($container->hasDefinition(PdfModelController::class));
        $this->assertFalse($container->hasDefinition(PdfModelType::class));
        $this->assertTrue($container->hasDefinition(CruditRouteLoader::class));
        $this->assertCount(0, (new CruditRouteLoader(false))->load('.', CruditRouteLoader::TYPE));
    }

    public function testScreensCanBeDisabled(): void
    {
        $container = $this->container(['LleCruditBundle' => 'Lle\CruditBundle\LleCruditBundle'], ['screens' => ['enabled' => false]]);

        $this->assertFalse($container->getParameter('lle.pdf.screens.enabled'));
        $this->assertFalse($container->hasDefinition(PdfModelCrudConfig::class));
    }

    public function testWithCruditTheScreenIsLoaded(): void
    {
        if (!class_exists(LleCruditBundle::class)) {
            $this->markTestSkipped('crudit-bundle not installed');
        }

        $container = $this->container(['LleCruditBundle' => LleCruditBundle::class]);

        $this->assertTrue($container->getParameter('lle.pdf.screens.enabled'));
        $this->assertTrue($container->hasDefinition(PdfModelCrudConfig::class));
        $this->assertTrue($container->hasDefinition(PdfModelType::class));
        $this->assertTrue($container->getDefinition(PdfModelController::class)->hasTag('controller.service_arguments'));
    }

    public function testCruditReportIsDisabledByDefault(): void
    {
        $container = $this->container([]);

        $this->assertFalse($container->getParameter('lle.pdf.crudit.enabled'));
        $generator = $container->getDefinition(CruditReportGenerator::class);
        $this->assertFalse($generator->hasTag('lle.pdf.generator'));
        $this->assertFalse($generator->isAutoconfigured());
        $this->assertCount(0, (new CruditRouteLoader(false, false))->load('.', CruditRouteLoader::TYPE));
    }

    public function testCruditReportCanBeEnabled(): void
    {
        $container = $this->container([], ['crudit' => ['enabled' => true]]);

        $this->assertTrue($container->getParameter('lle.pdf.crudit.enabled'));
        $this->assertTrue($container->getDefinition(CruditReportGenerator::class)->hasTag('lle.pdf.generator'));
    }
}
