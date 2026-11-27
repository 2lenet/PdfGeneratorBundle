<?php

namespace Lle\PdfGeneratorBundle\Tests\Version;

use PHPUnit\Framework\Attributes\DataProvider;

class VersionStorageTest extends VersionTestCase
{
    public function testStoreCopiesTheFileInTheHiddenVersionsFolder(): void
    {
        $storage = $this->storage();
        $file = $this->templateFile('abc.docx', 'content');

        $name = $storage->store($file);

        $this->assertStringEndsWith('.docx', $name);
        $this->assertSame($this->dir . '.versions/' . $name, $storage->path($name));
        $this->assertFileEquals($file, $storage->path($name));
        $this->assertNotSame($name, $storage->store($file), 'each upload has its own copy');
    }

    /** Created by a command (root), the folder stays writable by the web server, as the templates folder. */
    public function testVersionsFolderTakesThePermissionsOfTheTemplatesFolder(): void
    {
        chmod($this->dir, 0o777);
        $storage = $this->storage();
        $file = $this->templateFile('abc.docx', 'content');
        $umask = umask(0o022);

        try {
            $storage->store($file);
        } finally {
            umask($umask);
        }

        $this->assertSame(0o777, fileperms($this->dir . '.versions') & 0o777);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'parent' => ['../abc.docx'];
        yield 'subfolder' => ['sub/abc.docx'];
        yield 'hidden' => ['.htaccess'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidNames')]
    public function testPathRefusesANameOutsideTheVersionsFolder(string $name): void
    {
        $storage = $this->storage();

        $this->expectException(\InvalidArgumentException::class);

        $storage->path($name);
    }
}
