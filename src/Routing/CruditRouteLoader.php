<?php

namespace Lle\PdfGeneratorBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RouteCollection;

/**
 * Optional routes of the bundle (type lle_pdf_generator_crudit, imported by the routes.yaml of the bundle): those of the
 * templates admin screen, only when the screen is enabled (crudit-bundle installed), and those of the Crudit Report
 * designer, only when crudit_report is enabled (lle_pdf_generator.crudit.enabled).
 */
class CruditRouteLoader extends Loader
{
    public const TYPE = 'lle_pdf_generator_crudit';

    private bool $loaded = false;

    public function __construct(
        #[Autowire(param: 'lle.pdf.screens.enabled')]
        private bool $enabled,
        #[Autowire(param: 'lle.pdf.crudit.enabled')]
        private bool $designer = false,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('The ' . self::TYPE . ' routes are already loaded');
        }
        $this->loaded = true;

        $routes = new RouteCollection();
        if ($this->enabled) {
            /** @var RouteCollection $screen */
            $screen = $this->import(__DIR__ . '/../Controller/Crudit/', 'attribute');
            $routes->addCollection($screen);
        }
        if ($this->designer) {
            /** @var RouteCollection $designer */
            $designer = $this->import(__DIR__ . '/../Controller/CruditDesignerController.php', 'attribute');
            $routes->addCollection($designer);
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::TYPE;
    }
}
