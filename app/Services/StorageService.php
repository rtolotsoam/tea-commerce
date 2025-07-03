<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StorageService
{
    private string $disk = 'minio';

    /**
     * Uploader un fichier CSV
     */
    public function uploadCsv(UploadedFile $file, string $folder = 'imports'): array
    {
        $fileName = Str::uuid() . '_' . $file->getClientOriginalName();
        $path = Storage::disk($this->disk)->putFileAs(
            $folder . '/' . date('Y/m/d'),
            $file,
            $fileName
        );

        return [
            'path' => $path,
            'url' => Storage::disk($this->disk)->url($path),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Sauvegarder un export CSV
     */
    public function saveCsvExport(string $content, string $filename, string $folder = 'exports'): array
    {
        $path = $folder . '/' . date('Y/m/d') . '/' . $filename;

        Storage::disk($this->disk)->put($path, $content);

        return [
            'path' => $path,
            'url' => Storage::disk($this->disk)->url($path),
            'size' => strlen($content),
        ];
    }

    /**
     * Télécharger un fichier
     */
    public function download(string $path): ?string
    {
        if (!Storage::disk($this->disk)->exists($path)) {
            return null;
        }

        return Storage::disk($this->disk)->get($path);
    }

    /**
     * Obtenir l'URL temporaire d'un fichier
     */
    public function getTemporaryUrl(string $path, int $minutes = 60): ?string
    {
        if (!Storage::disk($this->disk)->exists($path)) {
            return null;
        }

        return Storage::disk($this->disk)->temporaryUrl($path, now()->addMinutes($minutes));
    }

    /**
     * Supprimer un fichier
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Lister les fichiers d'un dossier
     */
    public function listFiles(string $directory): array
    {
        return Storage::disk($this->disk)->files($directory);
    }

}
