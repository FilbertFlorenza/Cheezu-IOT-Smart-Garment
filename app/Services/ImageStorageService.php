<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageStorageService
{
    public function diskName(): string
    {
        return config('inspection.image_disk');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /**
     * Store an uploaded inspection image using a server-generated name.
     *
     * @return array{path: string, mime: string, size: int}
     */
    public function store(UploadedFile $image, string $inspectionId): array
    {
        $mime = $image->getMimeType() ?? 'image/jpeg';
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $directory = 'inspections/'.now()->format('Y/m');
        $path = $image->storeAs($directory, Str::lower($inspectionId).'.'.$extension, $this->diskName());

        if ($path === false) {
            throw new RuntimeException('The inspection image could not be stored.');
        }

        return [
            'path' => $path,
            'mime' => $mime,
            'size' => $image->getSize(),
        ];
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            $this->disk()->delete($path);
        }
    }

    /**
     * Short-lived URL suitable for private local or S3 storage.
     */
    public function temporaryUrl(?string $path): ?string
    {
        if ($path === null || $path === '' || ! $this->disk()->exists($path)) {
            return null;
        }

        try {
            return $this->disk()->temporaryUrl($path, now()->addMinutes(10));
        } catch (\Throwable) {
            return null;
        }
    }

    public function isLocal(): bool
    {
        return config("filesystems.disks.{$this->diskName()}.driver") === 'local';
    }

    public function absolutePath(string $path): string
    {
        return $this->disk()->path($path);
    }
}
