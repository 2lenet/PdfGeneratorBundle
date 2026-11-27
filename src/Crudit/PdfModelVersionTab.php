<?php

namespace Lle\PdfGeneratorBundle\Crudit;

use Lle\CruditBundle\Brick\ControllerBrick\ControllerConfig;
use Lle\PdfGeneratorBundle\Controller\PdfModelVersionController;

/**
 * Tab of the show page of a template listing the history of its files, for the screen of the bundle and for a
 * screen of the project (lle_pdf_generator.screens.enabled: false):
 *
 *     public function getTabs(): array
 *     {
 *         return [PdfModelVersionTab::LABEL => PdfModelVersionTab::new()];
 *     }
 */
final class PdfModelVersionTab
{
    /** Translation key of the tab, in the translation domain of the screen. */
    public const LABEL = 'tab.pdfmodel_versions';

    public static function new(): ControllerConfig
    {
        return ControllerConfig::new(['controller' => PdfModelVersionController::class . '::history']);
    }
}
