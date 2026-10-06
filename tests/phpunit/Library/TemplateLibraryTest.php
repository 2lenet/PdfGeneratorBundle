<?php

namespace Lle\PdfGeneratorBundle\Tests\Library;

use Lle\PdfGeneratorBundle\Exception\LibraryFileExistsException;
use Lle\PdfGeneratorBundle\Library\TemplateLibrary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplateLibraryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/library-test-' . uniqid() . '/';
        mkdir($this->dir);
        file_put_contents($this->dir . 'invoice.template.json', '{}');
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    public function testStore(): void
    {
        $library = new TemplateLibrary();
        $png = $this->dir . 'upload';
        // PNG 1×1 transparent
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        $this->assertSame('assets/cafe-logo.png', $library->store($this->dir, $png, 'Café Logo.PNG'));
        $this->assertFileEquals($png, $this->dir . 'assets/cafe-logo.png');
        // same content: file reused; other content under the same name: refused, unless overwriting
        $this->assertSame('assets/cafe-logo.png', $library->store($this->dir, $png, 'cafe-logo.png'));
        $old = $this->dir . 'assets/cafe-logo.png';
        file_put_contents($old, 'other', FILE_APPEND);
        try {
            $library->store($this->dir, $png, 'cafe-logo.png');
            $this->fail('existing file overwritten without confirmation');
        } catch (LibraryFileExistsException $e) {
            $this->assertSame('assets/cafe-logo.png', $e->uri);
        }
        $this->assertSame('assets/cafe-logo.png', $library->store($this->dir, $png, 'cafe-logo.png', true));
        $this->assertFileEquals($png, $old);

        $font = $this->dir . 'font';
        file_put_contents($font, "\x00\x01\x00\x00" . str_repeat("\x00", 12));
        $this->assertSame('fonts/inter-bold.ttf', $library->store($this->dir, $font, 'Inter-Bold.ttf'));

        $svg = $this->dir . 'svg';
        file_put_contents($svg, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->assertSame('assets/stamp.svg', $library->store($this->dir, $svg, 'stamp.svg'));

        // templates, binary and uploaded files (without extension) are not part of the library
        $files = $library->list($this->dir);
        $this->assertSame(
            ['assets/cafe-logo.png', 'assets/stamp.svg', 'fonts/inter-bold.ttf'],
            array_column($files, 'uri'),
        );
        $this->assertSame(['image', 'image', 'font'], array_column($files, 'kind'));
        $this->assertSame([1, 1], [$files[0]['widthPx'], $files[0]['heightPx']]);
        $this->assertArrayNotHasKey('widthPx', $files[1]);
        $this->assertSame([], $library->list($this->dir . 'absent'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function refusedFiles(): iterable
    {
        yield 'extension' => ['<?php echo 1;', 'script.php'];
        yield 'unreadable image' => ['not an image', 'logo.png'];
        yield 'unreadable font' => ['not a font', 'inter.ttf'];
        yield 'unreadable svg' => ['<html></html>', 'stamp.svg'];
    }

    /**
     * @dataProvider refusedFiles
     */
    #[DataProvider('refusedFiles')]
    public function testStoreRefusesBadFiles(string $content, string $name): void
    {
        $file = $this->dir . 'upload';
        file_put_contents($file, $content);

        $this->expectException(\InvalidArgumentException::class);
        (new TemplateLibrary())->store($this->dir, $file, $name);
    }
}
