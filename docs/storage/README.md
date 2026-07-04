# Storage

Filesystem abstraction: write code once, switch between local disk,
FTP, AWS S3, or Cloudflare R2 by changing configuration.

## Drivers & configuration

Available drivers: **`local`**, **`ftp`**, **`s3`**, **`r2`**.
Disks are defined in `config/filesystems.php`; the default comes from
`.env`:

```env
STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=my-bucket
```

## Usage — two styles

### Style 1: default disk (static passthrough)

Calls on `Storage` forward to the configured default disk:

```php
use Framework\Core\Storage\Storage;

Storage::put('avatars/user_1.jpg', $bytes);   // write
$bytes = Storage::get('avatars/user_1.jpg');  // read
Storage::exists('invoices/dec.pdf');          // bool
Storage::delete('old_report.csv');
```

### Style 2: explicit disk

Bypass the default per call — e.g. temp files locally, media on S3:

```php
Storage::disk('local')->put('tmp/report.csv', $csv);
Storage::disk('s3')->put('videos/holiday.mp4', $video);
Storage::disk('r2')->put('backups/db.sql', $dump);
```

## Handling uploads

Combine with the Request's `UploadedFile`:

```php
public function upload(Request $request)
{
    if (!$request->hasFile('avatar')) {
        return Response::json(['error' => 'no file'], 422);
    }

    $file = $request->file('avatar');
    Storage::put(
        'avatars/' . uniqid('u_') . '.jpg',
        file_get_contents($file->getPathname())
    );
}
```

## Custom drivers

Implement `StorageDriverInterface` (get/put/exists/delete/…) and
register it:

```php
Storage::setDisk('gcs', new GoogleCloudDriver($config));
Storage::disk('gcs')->put('file.txt', 'hello');
```

## Lifecycle

`Storage::reset()` drops resolved disk instances (all, or one by name)
so the next call re-reads config — called automatically between
requests in long-running workers via `State::resetPerRequest()`.
