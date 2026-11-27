<?php

namespace Lle\PdfGeneratorBundle\Tests\Version;

use Doctrine\ORM\EntityManagerInterface;
use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Lle\PdfGeneratorBundle\Entity\PdfModelVersion;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Lle\PdfGeneratorBundle\Repository\PdfModelVersionRepository;
use Lle\PdfGeneratorBundle\Version\VersionRecorder;
use Lle\PdfGeneratorBundle\Version\VersionStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/** Templates folder in a temporary directory, and an entity manager that collects the persisted versions. */
abstract class VersionTestCase extends TestCase
{
    protected const USER = 'alice@example.com';

    protected string $dir;

    /** @var list<PdfModelVersion> */
    protected array $persisted = [];

    /** @var array<int, int> next version number by template id */
    protected array $nextNumbers = [];

    /** @var list<int> ids of the templates that already have versions */
    protected array $withVersions = [];

    protected int $flushes = 0;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/version-test-' . uniqid() . '/';
        mkdir($this->dir);
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

    protected function storage(): VersionStorage
    {
        $generator = $this->createMock(PdfGenerator::class);
        $generator->method('getPath')->willReturn($this->dir);

        return new VersionStorage($generator);
    }

    protected function recorder(?string $user = self::USER): VersionRecorder
    {
        $tokenStorage = new TokenStorage();
        if ($user !== null) {
            $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser($user, null), 'main'));
        }

        return new VersionRecorder($this->storage(), $tokenStorage);
    }

    protected function entityManager(): EntityManagerInterface&MockObject
    {
        $repository = $this->createMock(PdfModelVersionRepository::class);
        $repository->method('nextNumber')->willReturnCallback(fn (int $modelId): int => $this->nextNumbers[$modelId] ?? 1);
        $repository->method('hasVersions')->willReturnCallback(fn (int $modelId): bool => in_array($modelId, $this->withVersions, true));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(PdfModelVersion::class)->willReturn($repository);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->assertInstanceOf(PdfModelVersion::class, $entity);
            $this->persisted[] = $entity;
        });
        $em->method('flush')->willReturnCallback(function (): void {
            $this->flushes++;
        });

        return $em;
    }

    protected function model(int $id, string $code = 'BL'): PdfModel
    {
        return (new PdfModel())->setId($id)->setCode($code);
    }

    /** A template file in the templates folder, as Vich leaves it after the upload. */
    protected function templateFile(string $name, string $content): string
    {
        file_put_contents($this->dir . $name, $content);

        return $this->dir . $name;
    }
}
