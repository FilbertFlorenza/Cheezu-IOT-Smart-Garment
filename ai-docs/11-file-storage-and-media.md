# 11 — File Storage and Media

The only user-supplied media in the system is the **optional inspection image**. There are no
avatars, documents, exports, or generated reports.

## Disks

Configured in `config/filesystems.php`:

| Disk | Driver | Root / target | Visibility | Used for |
| --- | --- | --- | --- | --- |
| `local` | local | `storage/app/private` | private | Default inspection image storage |
| `public` | local | `storage/app/public` | public | Declared; not used by application code |
| `s3` | s3 | `AWS_BUCKET` | private (bucket policy) | Optional S3-compatible storage |

Selection:

```php
// config/inspection.php
'image_disk' => env('INSPECTION_IMAGE_DISK', env('FILESYSTEM_DISK', 'local')),
```

Resolution order: `INSPECTION_IMAGE_DISK` → `FILESYSTEM_DISK` → `local`.

The `local` disk has `'serve' => true`, but the application does **not** rely on Laravel's local
serving route; images are served by an authorised controller action (see below).

## Upload validation

From `StoreInspectionResultRequest`:

| Rule | Value |
| --- | --- |
| Field | `image` (multipart file) |
| Nullable | Yes |
| MIME types | `jpg`, `jpeg`, `png`, `webp` |
| Max size | `INSPECTION_IMAGE_MAX_KB` (default 5120 KB = 5 MB) |
| Max dimensions | `INSPECTION_IMAGE_MAX_DIMENSION` per axis (default 4096) |

Content type is derived server-side via `UploadedFile::getMimeType()`; the client filename is never
used for storage.

## Write path

`App\Services\ImageStorageService::store(UploadedFile $image, string $inspectionId)`:

1. Determine MIME (fallback `image/jpeg`).
2. Choose extension: `png` → `.png`, `webp` → `.webp`, everything else → `.jpg`.
3. Build directory `inspections/YYYY/MM` from the current time.
4. Store as `inspections/YYYY/MM/<lowercased inspection UUID>.<ext>` using `storeAs`.
5. Throw `RuntimeException` if the write returns `false`.
6. Return `['path' => ..., 'mime' => ..., 'size' => ...]`.

The inspection row stores:

| Column | Value |
| --- | --- |
| `image_path` | Relative disk path |
| `image_mime` | Detected MIME |
| `image_size_bytes` | Byte size |

Deterministic naming (`<uuid>.<ext>`) means re-uploading the same inspection ID would overwrite the
object — but duplicate inspections are rejected before storage, so this is effectively unreachable.

## Delete path

`ImageStorageService::delete(?string $path)` deletes the object if the path is non-empty. Called only
when the inspection transaction fails **after** an image was written, preventing orphans.

There is no user-facing image deletion, replacement, or retention purge.

## Read path

### Web UI

Route: `GET /inspections/{inspection}/image` → `Web\InspectionController@image`.

1. `authorize('view', $inspection)` — administrator/operator only.
2. 404 if `image_path` is null or the object does not exist.
3. **Local disk**: `response()->file(absolutePath, ['Content-Type' => image_mime, 'Cache-Control' =>
   'private, max-age=300'])` — streamed through PHP, never a public URL.
4. **Non-local disk**: generate a 10-minute temporary URL and `redirect()->away($url)`; 404 if the URL
   cannot be generated.

The detail view precomputes `imageUrl = route('inspections.image', $inspection)` when an image exists.

### API

`InspectionResource` includes `image_url` only when `image_path !== null`. The value comes from
`ImageStorageService::temporaryUrl()`, which:

- Returns `null` for empty paths or missing objects.
- Calls the disk's `temporaryUrl($path, now()->addMinutes(10))`.
- Returns `null` if the driver throws.

Because the `local` disk is configured with `'serve' => true`, Laravel exposes a `storage.local`
signed route. Local images therefore also receive a working signed URL, not just S3 objects. The URL
is valid for 10 minutes and anyone holding it can fetch the object until it expires.

## S3 configuration

Environment variables (names only):

| Variable | Purpose |
| --- | --- |
| `AWS_ACCESS_KEY_ID` | Static key (prefer task role in AWS) |
| `AWS_SECRET_ACCESS_KEY` | Static secret |
| `AWS_DEFAULT_REGION` | Bucket region |
| `AWS_BUCKET` | Bucket name |
| `AWS_URL` | Optional CDN/custom URL |
| `AWS_ENDPOINT` | S3-compatible endpoint (MinIO) |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` for MinIO |

The bucket must remain **private**. Temporary URLs are signed by the S3 driver. The Compose MinIO
overlay explicitly sets the bucket anonymous policy to `none`.

## MinIO overlay (local)

`docker-compose.s3.yml` adds:

- `minio` service (`minio/minio`) with console on 9001.
- `minio-init` (`minio/mc`) that creates the bucket if missing and disables anonymous access.

Required variables: `MINIO_ROOT_USER`, `MINIO_ROOT_PASSWORD`, and the S3 block in `.env.docker`.

## Container persistence

`docker-compose.yml` mounts:

| Volume | Container path | Contents |
| --- | --- | --- |
| `app-storage` | `/var/www/html/storage/app` | Private inspection images |
| `app-logs` | `/var/www/html/storage/logs` | Log files |

Without the volume, images would be lost when the container is replaced. In ECS, local storage is
ephemeral, so S3 (`INSPECTION_IMAGE_DISK=s3`) is the intended production configuration.

## Limits and guarantees

- Images are optional; a valid inspection can have no image.
- Detection results are unaffected by image presence.
- No thumbnailing, cropping, watermarking, OCR, or EXIF stripping is implemented.
- `gd` is not installed in the runtime image; no image manipulation is possible there.
- Maximum one image per inspection.
- No virus scanning or content disarm.
- No storage quota or per-device limit.
- Temporary URLs expire after 10 minutes; the signed URL may be cached by clients until then.
- There is no cleanup of images for deleted inspections because inspections are never deleted and
  devices cannot be deleted while inspections exist (`restrictOnDelete`).

## Adding new media safely

1. Decide the disk via config, never hardcode `Storage::disk('local')`.
2. Validate MIME, size, and dimensions in a FormRequest.
3. Generate server-side filenames; never trust `getClientOriginalName()`.
4. Store metadata columns on the owning model.
5. Serve through an authorised controller or a short-lived temporary URL.
6. Define deletion/retention behaviour, including object cleanup on transaction failure.

## Related documents

- Ingestion workflow: [09-business-logic-and-workflows.md](09-business-logic-and-workflows.md)
- Environment variables: [12-configuration-and-environment.md](12-configuration-and-environment.md)
- Deployment storage: [14-deployment-and-infrastructure.md](14-deployment-and-infrastructure.md)
