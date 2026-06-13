# Glueful Storage GCS

Google Cloud Storage driver for Glueful.

## Install

```bash
composer require glueful/storage-gcs
php glueful extensions:enable storage-gcs
```

The package auto-registers as a Glueful extension through
`extra.glueful.provider`. After install, any disk with `driver => gcs` is
resolved by `GcsStorageDriverFactory`.

## Configuration

Add a disk under `config/storage.php`:

```php
'gcs' => [
    'driver' => 'gcs',
    'bucket' => env('GCS_BUCKET'),
    'project_id' => env('GCS_PROJECT_ID'),
    'key_file' => env('GCS_KEY_FILE'), // path to service-account JSON
    'prefix' => env('GCS_PREFIX', ''),
    'signed_ttl' => (int) env('GCS_SIGNED_URL_TTL', 3600),
    'max_signed_ttl' => (int) env('GCS_MAX_SIGNED_URL_TTL', 86400),
],
```

Environment variables:

```dotenv
GCS_BUCKET=app-bucket
GCS_PROJECT_ID=my-google-cloud-project
GCS_KEY_FILE=/absolute/path/to/service-account.json
GCS_PREFIX=
GCS_SIGNED_URL_TTL=3600
GCS_MAX_SIGNED_URL_TTL=86400
```

`key_file` is optional for filesystem construction when your runtime already
has Application Default Credentials. Native V4 signed URLs need credentials
capable of local signing, so service-account JSON is the normal production
configuration.

## Native URLs

The framework always supports app-signed blob URLs through `/blobs/{uuid}`.
Direct provider URLs are opt-in and visibility-scoped:

```php
// config/uploads.php
'native_urls' => [
    'disks' => [
        'gcs' => [
            'enabled' => true,
            'public' => true,
            'private' => false,
            'private_ttl' => 300,
        ],
    ],
    'max_private_ttl' => 900,
],
```

Private native URLs are bearer tokens from Google Cloud Storage. Keep them
short-lived and prefer the app-signed URL when application-side authorization
or revocation matters.
Direct provider URL TTLs are clamped by the disk's `max_signed_ttl` value, which
defaults to 86400 seconds.

## Diagnostics

Run a read-only check:

```bash
php glueful storage:test gcs
```

Run a write/read/delete smoke test only when you want to verify write
permissions:

```bash
php glueful storage:test gcs --write
```

## Development

```bash
composer test
composer run analyze
composer run phpcs
```
