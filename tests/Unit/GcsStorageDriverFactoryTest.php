<?php

declare(strict_types=1);

namespace Glueful\Extensions\StorageGcs\Tests\Unit;

use Glueful\Extensions\StorageGcs\GcsStorageDriverFactory;
use Glueful\Storage\Contracts\StorageDriverFactoryInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;

final class GcsStorageDriverFactoryTest extends TestCase
{
    public function testDriverNameIsGcs(): void
    {
        $factory = new GcsStorageDriverFactory();

        self::assertSame('gcs', $factory->driver());
        self::assertInstanceOf(StorageDriverFactoryInterface::class, $factory);
    }

    public function testAvailableTrueWhenAdapterAndClientPresent(): void
    {
        self::assertTrue((new GcsStorageDriverFactory())->available([]));
    }

    public function testAvailableFalseWhenAdapterNotLoadable(): void
    {
        $factory = new class extends GcsStorageDriverFactory {
            protected function adapterPresent(): bool
            {
                return false;
            }
        };

        self::assertFalse($factory->available([]));
    }

    public function testCreateBuildsFilesystem(): void
    {
        $fs = (new GcsStorageDriverFactory())->create([
            'bucket' => 'test-bucket',
            'project_id' => 'demo-project',
        ]);

        self::assertInstanceOf(FilesystemOperator::class, $fs);
    }

    public function testCreateThrowsWhenBucketMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new GcsStorageDriverFactory())->create(['project_id' => 'x']);
    }

    public function testCreateUnavailableMessageNamesMissingDependencies(): void
    {
        $factory = new class extends GcsStorageDriverFactory {
            protected function adapterPresent(): bool
            {
                return false;
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('GCS adapter dependencies not available');
        $this->expectExceptionMessage('league/flysystem-google-cloud-storage');
        $factory->create(['bucket' => 'test-bucket']);
    }

    public function testFeaturesDeclareCloudNonAtomicNativeUrls(): void
    {
        $features = (new GcsStorageDriverFactory())->features([]);

        self::assertFalse($features['supports_atomic_move']);
        self::assertTrue($features['cloud']);
        self::assertTrue($features['supports_native_signed_urls']);
    }
}
