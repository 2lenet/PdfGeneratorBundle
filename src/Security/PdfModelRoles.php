<?php

namespace Lle\PdfGeneratorBundle\Security;

/**
 * Roles of the features added to the PDF templates. They are also the roles of the actions of the bundle admin screen:
 * lle:credential:warmup registers them, so they are granted in the rights management.
 */
final class PdfModelRoles
{
    /** Download a template and show its PDF (Crudit role of the show page). */
    public const SHOW = 'ROLE_PDFMODEL_SHOW';

    /** Crudit Report designer: open, read and save a template, library of images and fonts. */
    public const DESIGNER = 'ROLE_PDFMODEL_DESIGNER';

    /** Export of all the templates (table and templates folder) to a zip archive. */
    public const EXPORT = 'ROLE_PDFMODEL_EXPORT';

    /** Import of an archive: replaces all the templates and their files. */
    public const IMPORT = 'ROLE_PDFMODEL_IMPORT';
}
