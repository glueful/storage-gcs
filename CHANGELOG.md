# Changelog

All notable changes to the Glueful GCS Storage Driver extension will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Clamp native GCS signed URL TTLs with `max_signed_ttl` so direct provider URLs cannot be minted with unbounded lifetimes.

## [1.0.0] - 2026-06-10 -- Initial Storage Provider Pack Release

### Added

- **Google Cloud Storage driver pack** for Glueful Framework 1.54.0's storage driver registry.
- `GcsStorageDriverFactory` implementing `StorageDriverFactoryInterface`, `NativeSignedUrlProviderInterface`, and `StorageHealthCheckInterface`.
- Google Cloud Storage support through `league/flysystem-google-cloud-storage` and direct `google/cloud-storage` runtime dependency.
- Native V4 signed URL generation with service-account credentials and prefix-joined object keys.
- Read-only health checks for `php glueful storage:test`.
- Extension service provider metadata and `storage.driver_factory` tag registration.
- Install documentation including `php glueful extensions:enable storage-gcs`.
- PHPUnit, PHPCS, and PHPStan level 6 project gates.

### Notes

- Requires Glueful Framework 1.54.0 or newer.
- `key_file` is optional for filesystem construction when Application Default Credentials are available, but native signed URLs require credentials capable of local signing.
