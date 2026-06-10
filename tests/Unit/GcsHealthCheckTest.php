<?php

declare(strict_types=1);

namespace Glueful\Extensions\StorageGcs\Tests\Unit;

use Glueful\Extensions\StorageGcs\GcsStorageDriverFactory;
use Glueful\Storage\Contracts\StorageHealthCheckInterface;
use PHPUnit\Framework\TestCase;

final class GcsHealthCheckTest extends TestCase
{
    public function testCheckFailsCleanlyWhenBucketMissing(): void
    {
        $result = (new GcsStorageDriverFactory())->check('media', ['project_id' => 'p']);

        self::assertFalse($result['ok']);
        self::assertStringContainsString("missing 'bucket'", $result['message']);
    }

    public function testCheckNeverLeaksKeyFilePathSecrets(): void
    {
        $result = (new GcsStorageDriverFactory())->check('media', [
            'bucket' => 'b',
            'project_id' => 'p',
            'key_file' => '/secret/path/SUPERSECRET.json',
        ]);

        self::assertFalse($result['ok']);
        self::assertStringNotContainsString('SUPERSECRET', $result['message']);
    }

    public function testCheckTruncatesProviderFailureMessage(): void
    {
        $factory = new class extends GcsStorageDriverFactory {
            public function create(array $config): \League\Flysystem\FilesystemOperator
            {
                throw new \RuntimeException(str_repeat('x', 300));
            }
        };

        $result = $factory->check('media', [
            'bucket' => 'b',
            'project_id' => 'p',
        ]);

        self::assertFalse($result['ok']);
        self::assertLessThanOrEqual(180, strlen($result['message']));
        self::assertStringEndsWith('...', $result['message']);
    }

    public function testImplementsHealthCheck(): void
    {
        self::assertInstanceOf(StorageHealthCheckInterface::class, new GcsStorageDriverFactory());
    }
}
