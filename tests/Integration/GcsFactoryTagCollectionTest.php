<?php

declare(strict_types=1);

namespace Glueful\Extensions\StorageGcs\Tests\Integration;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Container\Container;
use Glueful\Container\Definition\ValueDefinition;
use Glueful\Container\Providers\StorageProvider;
use Glueful\Container\Providers\TagCollector;
use Glueful\Extensions\StorageGcs\GcsStorageDriverFactory;
use Glueful\Extensions\StorageGcs\StorageGcsServiceProvider;
use Glueful\Storage\Contracts\StorageDriverRegistryInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;

final class GcsFactoryTagCollectionTest extends TestCase
{
    public function testServicesDslPinsTheDriverFactoryTag(): void
    {
        $services = StorageGcsServiceProvider::services();

        self::assertSame(
            ['storage.driver_factory'],
            $services[GcsStorageDriverFactory::class]['tags']
        );
    }

    public function testComposerManifestDeclaresGluefulExtensionProvider(): void
    {
        $json = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

        self::assertSame('glueful-extension', $json['type'] ?? null);
        self::assertSame(
            StorageGcsServiceProvider::class,
            $json['extra']['glueful']['provider'] ?? null
        );
    }

    public function testGcsFactoryIsCollectedIntoRegistryAndResolvesDisk(): void
    {
        $base = sys_get_temp_dir() . '/glueful-pack-' . uniqid('', true);
        mkdir($base . '/config', 0777, true);

        $provider = new StorageProvider(new TagCollector(), ApplicationContext::forTesting($base));
        $defs = $provider->defs();

        $factory = new GcsStorageDriverFactory();
        $defs['storage.driver_factory'] = new ValueDefinition('storage.driver_factory', [$factory]);

        $registry = (new Container($defs))->get(StorageDriverRegistryInterface::class);

        self::assertTrue($registry->has('gcs'));
        self::assertSame($factory, $registry->get('gcs'));

        $fs = $registry->get('gcs')->create(['bucket' => 'b', 'project_id' => 'p']);
        self::assertInstanceOf(FilesystemOperator::class, $fs);
    }
}
