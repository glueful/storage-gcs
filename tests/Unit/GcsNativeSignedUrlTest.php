<?php

declare(strict_types=1);

namespace Glueful\Extensions\StorageGcs\Tests\Unit;

use Glueful\Extensions\StorageGcs\GcsStorageDriverFactory;
use Glueful\Storage\Contracts\NativeSignedUrlProviderInterface;
use PHPUnit\Framework\TestCase;

final class GcsNativeSignedUrlTest extends TestCase
{
    public function testTemporaryUrlReturnsNullWhenBucketMissing(): void
    {
        self::assertNull((new GcsStorageDriverFactory())->temporaryUrl('x', 600, ['project_id' => 'p']));
    }

    public function testImplementsNativeSignedUrlProvider(): void
    {
        self::assertInstanceOf(NativeSignedUrlProviderInterface::class, new GcsStorageDriverFactory());
    }

    public function testTemporaryUrlReturnsNullWhenSigningFails(): void
    {
        $factory = new class extends GcsStorageDriverFactory {
            protected function createClient(array $config): object
            {
                throw new \RuntimeException('signing unavailable');
            }
        };

        $url = $factory->temporaryUrl('uploads/file.jpg', 600, [
            'bucket' => 'b',
            'project_id' => 'p',
        ]);

        self::assertNull($url);
    }

    public function testTemporaryUrlSignsPrefixJoinedObjectNameOffline(): void
    {
        $keyFile = $this->createServiceAccountKeyFile();

        $url = (new GcsStorageDriverFactory())->temporaryUrl('uploads/file.jpg', 600, [
            'bucket' => 'media-bucket',
            'project_id' => 'offline-project',
            'key_file' => $keyFile,
            'prefix' => 'tenant-a',
        ]);

        self::assertIsString($url);
        self::assertStringContainsString('X-Goog-Algorithm=GOOG4-RSA-SHA256', $url);
        self::assertStringContainsString('tenant-a/uploads/file.jpg', rawurldecode($url));
    }

    public function testTemporaryUrlClampsTtlToConfiguredMaximum(): void
    {
        $keyFile = $this->createServiceAccountKeyFile();

        $url = (new GcsStorageDriverFactory())->temporaryUrl('uploads/file.jpg', 999999, [
            'bucket' => 'media-bucket',
            'project_id' => 'offline-project',
            'key_file' => $keyFile,
            'max_signed_ttl' => 900,
        ]);

        self::assertIsString($url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertSame('900', $query['X-Goog-Expires'] ?? null);
    }

    public function testTemporaryUrlClampsConfiguredDefaultTtlToMaximum(): void
    {
        $keyFile = $this->createServiceAccountKeyFile();

        $url = (new GcsStorageDriverFactory())->temporaryUrl('uploads/file.jpg', 0, [
            'bucket' => 'media-bucket',
            'project_id' => 'offline-project',
            'key_file' => $keyFile,
            'signed_ttl' => 999999,
            'max_signed_ttl' => 900,
        ]);

        self::assertIsString($url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertSame('900', $query['X-Goog-Expires'] ?? null);
    }

    private function createServiceAccountKeyFile(): string
    {
        if (!function_exists('openssl_pkey_new') || !function_exists('openssl_pkey_export')) {
            self::markTestSkipped('OpenSSL extension is required to generate an offline test key.');
        }

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($key);

        $privateKey = '';
        self::assertTrue(openssl_pkey_export($key, $privateKey));

        $path = tempnam(sys_get_temp_dir(), 'gcs-sa-');
        self::assertIsString($path);

        file_put_contents($path, json_encode([
            'type' => 'service_account',
            'project_id' => 'offline-project',
            'private_key_id' => str_repeat('a', 40),
            'private_key' => $privateKey,
            'client_email' => 'storage-test@offline-project.iam.gserviceaccount.com',
            'client_id' => '1234567890',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => '',
        ], JSON_THROW_ON_ERROR));

        return $path;
    }
}
