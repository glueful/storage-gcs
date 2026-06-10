<?php

declare(strict_types=1);

namespace Glueful\Extensions\StorageGcs;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Extensions\ServiceProvider;

final class StorageGcsServiceProvider extends ServiceProvider
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function services(): array
    {
        return [
            GcsStorageDriverFactory::class => [
                'class' => GcsStorageDriverFactory::class,
                'shared' => true,
                'tags' => ['storage.driver_factory'],
            ],
        ];
    }

    public function register(ApplicationContext $context): void
    {
        $this->mergeConfig('storage-gcs', require __DIR__ . '/../config/storage-gcs.php');
    }

    public function boot(ApplicationContext $context): void
    {
    }
}
