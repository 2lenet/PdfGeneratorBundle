<?php

namespace Lle\PdfGeneratorBundle\Tests\Transfer;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Lle\PdfGeneratorBundle\Transfer\ModelTransfer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModelTransferTest extends TestCase
{
    private string $dir;

    private EntityManager $em;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/transfer-test-' . uniqid();
        mkdir($this->dir . '/source/assets', 0o777, true);
        mkdir($this->dir . '/target', 0o777, true);

        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 3) . '/src/Entity'], true);
        // ORM ≥ 3.5 only
        // @phpstan-ignore function.alreadyNarrowedType
        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($this->em))->createSchema([$this->em->getClassMetadata(PdfModel::class)]);
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

    private function transfer(string $path): ModelTransfer
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('getClassName')->willReturn(PdfModel::class);
        $generator = $this->createStub(PdfGenerator::class);
        $generator->method('getRepository')->willReturn($repository);
        $generator->method('getPath')->willReturn($this->dir . '/' . $path . '/');

        return new ModelTransfer($this->em, $generator);
    }

    /** @param array<string, mixed> $row */
    private function insert(array $row): void
    {
        $this->em->getConnection()->insert('lle_pdf_model', $row);
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        return $this->em->getConnection()->fetchAllAssociative('SELECT id, code, path, libelle, type FROM lle_pdf_model ORDER BY id');
    }

    public function testExportThenImportReplacesEverything(): void
    {
        $this->insert(['id' => 3, 'code' => 'BL', 'path' => 'bl.docx', 'libelle' => "Summer's note", 'type' => null]);
        $this->insert(['id' => 7, 'code' => 'FACT', 'path' => 'f.template.json', 'libelle' => 'Invoice', 'type' => 'crudit_report']);
        file_put_contents($this->dir . '/source/bl.docx', 'docx');
        file_put_contents($this->dir . '/source/f.template.json', '{}');
        file_put_contents($this->dir . '/source/assets/logo.png', 'png');
        file_put_contents($this->dir . '/source/.cache', 'hidden');
        mkdir($this->dir . '/source/generated');
        file_put_contents($this->dir . '/source/generated/invoice.pdf', 'not a template');

        $archive = $this->transfer('source')->export();
        $zip = new \ZipArchive();
        $zip->open($archive);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $sql = (string)$zip->getFromName(ModelTransfer::SQL_FILE);
        $zip->close();

        sort($names);
        $this->assertSame(['pdfmodel.json', 'pdfmodel.sql', 'pdfmodel/assets/logo.png', 'pdfmodel/bl.docx', 'pdfmodel/f.template.json'], $names);
        $this->assertMatchesRegularExpression('/^DELETE FROM .lle_pdf_model.;$/m', $sql);
        $this->assertStringContainsString("'Summer''s note'", $sql);

        // the target has other templates: they are replaced with their files and the library; the files that are
        // not of a template are kept
        $this->em->getConnection()->executeStatement('DELETE FROM lle_pdf_model');
        $this->insert(['id' => 1, 'code' => 'OTHER', 'path' => 'other.docx', 'libelle' => 'Other']);
        file_put_contents($this->dir . '/target/other.docx', 'other');
        file_put_contents($this->dir . '/target/.gitkeep', '');
        mkdir($this->dir . '/target/assets');
        file_put_contents($this->dir . '/target/assets/old.png', 'old');
        mkdir($this->dir . '/target/generated');
        file_put_contents($this->dir . '/target/generated/kept.pdf', 'kept');

        $this->assertSame(['models' => 2, 'files' => 3, 'ignored' => []], $this->transfer('target')->import($archive));
        unlink($archive);

        $this->assertSame([
            ['id' => 3, 'code' => 'BL', 'path' => 'bl.docx', 'libelle' => "Summer's note", 'type' => null],
            ['id' => 7, 'code' => 'FACT', 'path' => 'f.template.json', 'libelle' => 'Invoice', 'type' => 'crudit_report'],
        ], $this->rows());
        $this->assertFileDoesNotExist($this->dir . '/target/other.docx');
        $this->assertFileExists($this->dir . '/target/.gitkeep');
        $this->assertSame('png', file_get_contents($this->dir . '/target/assets/logo.png'));
        $this->assertFileDoesNotExist($this->dir . '/target/assets/old.png');
        $this->assertSame('kept', file_get_contents($this->dir . '/target/generated/kept.pdf'));
        $this->assertSame(['.gitkeep', 'assets', 'bl.docx', 'f.template.json', 'generated'], array_values(array_diff(scandir($this->dir . '/target'), ['.', '..'])));
    }

    /** A write that fails in the database leaves the rows and the files as they were. */
    public function testFailedWriteChangesNothing(): void
    {
        $this->insert(['id' => 1, 'code' => 'KEPT', 'path' => 'kept.docx', 'libelle' => 'Kept']);
        file_put_contents($this->dir . '/target/kept.docx', 'kept');

        $archive = $this->dir . '/archive.zip';
        $zip = new \ZipArchive();
        $zip->open($archive, \ZipArchive::CREATE);
        // path is NOT NULL: the second insert fails
        $zip->addFromString('pdfmodel.json', (string)json_encode(['format' => 1, 'rows' => [
            ['id' => 2, 'code' => 'NEW', 'path' => 'new.docx', 'libelle' => 'New'],
            ['id' => 3, 'code' => 'BAD', 'path' => null, 'libelle' => 'Bad'],
        ]]));
        $zip->addFromString('pdfmodel/new.docx', 'new');
        $zip->close();

        try {
            $this->transfer('target')->import($archive);
            $this->fail('import succeeded');
        } catch (\Exception) {
        }

        $this->assertSame('KEPT', $this->rows()[0]['code']);
        $this->assertSame(['kept.docx'], array_values(array_diff(scandir($this->dir . '/target'), ['.', '..'])));
    }

    /** Archive of another version of the bundle: a column missing from the table is ignored, not refused. */
    public function testUnknownColumnIsIgnored(): void
    {
        $archive = $this->dir . '/archive.zip';
        $zip = new \ZipArchive();
        $zip->open($archive, \ZipArchive::CREATE);
        $zip->addFromString('pdfmodel.json', (string)json_encode(['format' => 1, 'rows' => [
            ['id' => 4, 'code' => 'BL', 'path' => 'bl.docx', 'libelle' => 'BL', 'check_file' => 1],
        ]]));
        $zip->close();

        $this->assertSame(['models' => 1, 'files' => 0, 'ignored' => ['check_file']], $this->transfer('target')->import($archive));
        $this->assertSame('BL', $this->rows()[0]['code']);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function refusedArchives(): iterable
    {
        $data = json_encode(['format' => 1, 'rows' => [['id' => 1, 'code' => 'X', 'path' => 'x.docx', 'libelle' => 'X']]]);

        yield 'no data' => [['pdfmodel/x.docx' => 'x'], 'pdfmodel.json missing'];
        yield 'unreadable value' => [['pdfmodel.json' => json_encode(['format' => 1, 'rows' => [['id' => 1, 'code' => ['x']]]])], 'unreadable value'];
        yield 'forbidden path' => [['pdfmodel.json' => $data, 'pdfmodel/../../evil.php' => 'x'], 'forbidden path'];
        yield 'hidden element' => [['pdfmodel.json' => $data, 'pdfmodel/.htaccess' => 'x'], 'forbidden path'];
        yield 'file of no template' => [['pdfmodel.json' => $data, 'pdfmodel/evil.php' => 'x'], 'not a file of a template'];
    }

    /**
     * @param array<string, string> $entries
     *
     * @dataProvider refusedArchives
     */
    #[DataProvider('refusedArchives')]
    public function testRefusedArchiveChangesNothing(array $entries, string $message): void
    {
        $this->insert(['id' => 1, 'code' => 'KEPT', 'path' => 'kept.docx', 'libelle' => 'Kept']);
        file_put_contents($this->dir . '/target/kept.docx', 'kept');

        $archive = $this->dir . '/archive.zip';
        $zip = new \ZipArchive();
        $zip->open($archive, \ZipArchive::CREATE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        try {
            $this->transfer('target')->import($archive);
            $this->fail('archive accepted');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }

        $this->assertSame('KEPT', $this->rows()[0]['code']);
        $this->assertSame(['kept.docx'], array_values(array_diff(scandir($this->dir . '/target'), ['.', '..'])));
    }
}
