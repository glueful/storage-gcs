<?php

declare(strict_types=1);

namespace Glueful\Extensions\StorageGcs;

use Glueful\Storage\Contracts\NativeSignedUrlProviderInterface;
use Glueful\Storage\Contracts\StorageDriverFactoryInterface;
use Glueful\Storage\Contracts\StorageHealthCheckInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;

class GcsStorageDriverFactory implements
    StorageDriverFactoryInterface,
    NativeSignedUrlProviderInterface,
    StorageHealthCheckInterface
{
    public function driver(): string
    {
        return 'gcs';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config): FilesystemOperator
    {
        if (!$this->available($config)) {
            throw new \InvalidArgumentException(
                'GCS adapter dependencies not available. Install league/flysystem-google-cloud-storage.'
            );
        }

        $bucketName = (string) ($config['bucket'] ?? '');
        if ($bucketName === '') {
            throw new \InvalidArgumentException("Missing required GCS config: 'bucket'");
        }

        $client = $this->createClient($config);
        $prefix = (string) ($config['prefix'] ?? '');
        $adapterClass = 'League\\Flysystem\\GoogleCloudStorage\\GoogleCloudStorageAdapter';
        $bucket = $client->bucket($bucketName);
        $adapter = new $adapterClass($bucket, $prefix);
        assert($adapter instanceof FilesystemAdapter);

        return new Filesystem($adapter);
    }

    protected function adapterPresent(): bool
    {
        return class_exists('League\\Flysystem\\GoogleCloudStorage\\GoogleCloudStorageAdapter')
            && class_exists('Google\\Cloud\\Storage\\StorageClient');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function available(array $config): bool
    {
        return $this->adapterPresent();
    }

    /**
     * @param array<string, mixed> $config
     * @return array{supports_atomic_move: bool, supports_native_signed_urls: bool, cloud: bool}
     */
    public function features(array $config): array
    {
        return [
            'supports_atomic_move' => false,
            'supports_native_signed_urls' => true,
            'cloud' => true,
        ];
    }

    /**
     * @param array<string, mixed> $diskConfig
     */
    public function temporaryUrl(string $path, int $ttl, array $diskConfig): ?string
    {
        $bucketName = (string) ($diskConfig['bucket'] ?? '');
        if (!$this->adapterPresent() || $bucketName === '') {
            return null;
        }

        try {
            $client = $this->createClient($diskConfig);
            $seconds = $this->signedUrlTtl($ttl, $diskConfig);
            $prefix = (string) ($diskConfig['prefix'] ?? '');
            $objectName = $prefix !== ''
                ? rtrim($prefix, '/') . '/' . ltrim($path, '/')
                : $path;

            $object = $client->bucket($bucketName)->object($objectName);

            return (string) $object->signedUrl(time() + $seconds, ['version' => 'v4']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function signedUrlTtl(int $ttl, array $config): int
    {
        $seconds = $ttl > 0 ? $ttl : (int) ($config['signed_ttl'] ?? 3600);
        $max = (int) ($config['max_signed_ttl'] ?? 86400);

        return max(1, min($seconds, max(1, $max)));
    }

    /**
     * @param array<string, mixed> $diskConfig
     * @return array{ok: bool, message: string, details?: array<string, mixed>}
     */
    public function check(string $disk, array $diskConfig): array
    {
        if (!$this->available($diskConfig)) {
            return [
                'ok' => false,
                'message' => "Disk '{$disk}': GCS adapter dependencies not available.",
            ];
        }

        $bucket = (string) ($diskConfig['bucket'] ?? '');
        if ($bucket === '') {
            return ['ok' => false, 'message' => "Disk '{$disk}': missing 'bucket' config."];
        }

        try {
            $fs = $this->create($diskConfig);
            foreach ($fs->listContents('', false) as $_) {
                break;
            }

            return [
                'ok' => true,
                'message' => "Disk '{$disk}': reachable.",
                'details' => ['driver' => 'gcs', 'bucket' => $bucket],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => "Disk '{$disk}': probe failed -- "
                    . $this->summarizeProviderError($e, $diskConfig),
            ];
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createClient(array $config): object
    {
        $clientConfig = [];
        if (isset($config['key_file']) && $config['key_file'] !== '') {
            $clientConfig['keyFilePath'] = (string) $config['key_file'];
        }
        if (isset($config['project_id']) && $config['project_id'] !== '') {
            $clientConfig['projectId'] = (string) $config['project_id'];
        }

        $clientClass = 'Google\\Cloud\\Storage\\StorageClient';

        return new $clientClass($clientConfig);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function summarizeProviderError(\Throwable $e, array $config = []): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return $e::class;
        }

        foreach (['key_file', 'key', 'secret'] as $key) {
            if (isset($config[$key]) && is_scalar($config[$key]) && (string) $config[$key] !== '') {
                $message = str_replace((string) $config[$key], '[redacted]', $message);
            }
        }

        $maxLength = 140;
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3) . '...';
    }
}
