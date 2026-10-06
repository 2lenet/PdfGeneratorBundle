<?php

namespace Lle\PdfGeneratorBundle\Tests\Controller;

use Lle\PdfGeneratorBundle\Controller\CruditDesignerController;
use PHPUnit\Framework\TestCase;

class CruditDesignerControllerTest extends TestCase
{
    public function testEtagChangesWithTheContent(): void
    {
        $etag = CruditDesignerController::etag('{"a":1}');

        self::assertMatchesRegularExpression('/^"[0-9a-f]{32}"$/', $etag);
        self::assertSame($etag, CruditDesignerController::etag('{"a":1}'));
        self::assertNotSame($etag, CruditDesignerController::etag('{"a":2}'));
    }

    public function testIfMatch(): void
    {
        $content = '{"a":1}';
        $etag = CruditDesignerController::etag($content);

        // no header: no check (designer without ETag support)
        self::assertTrue(CruditDesignerController::matches(null, $content));
        self::assertTrue(CruditDesignerController::matches('*', $content));
        self::assertTrue(CruditDesignerController::matches($etag, $content));
        self::assertTrue(CruditDesignerController::matches('"other", ' . $etag, $content));
        // saved elsewhere since it was read
        self::assertFalse(CruditDesignerController::matches($etag, '{"a":2}'));
        self::assertFalse(CruditDesignerController::matches('"other"', $content));
    }

    public function testIfMatchIgnoresWeakPrefixAndCompressionSuffix(): void
    {
        $content = '{"a":1}';
        $etag = CruditDesignerController::etag($content);
        $suffixed = substr($etag, 0, -1) . '-gzip"';

        self::assertTrue(CruditDesignerController::matches('W/' . $etag, $content));
        self::assertTrue(CruditDesignerController::matches($suffixed, $content));
        self::assertTrue(CruditDesignerController::matches(substr($etag, 0, -1) . '-br"', $content));
        self::assertFalse(CruditDesignerController::matches(substr($etag, 0, -2) . '-gzip"', $content));
    }
}
