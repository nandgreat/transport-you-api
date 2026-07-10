<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadService
{
    /**
     * Allowed model types and the column that stores their image URL.
     */
    private const MODEL_MAP = [
        'vehicles'  => [\App\Models\Vehicle::class,  'image_url'],
        'profiles'  => [\App\Models\User::class,     'image_url'],
        'tenants'   => [\App\Models\Tenant::class,   'logo_url'],
        'routes'    => [\App\Models\Route::class,    'image_url'],
        'locations' => [\App\Models\Location::class, 'image_url'],
    ];

    /**
     * Store the file and optionally update the related model's image column.
     *
     * @param  UploadedFile  $file
     * @param  string        $type      Folder / model context (vehicles, profiles, …)
     * @param  int|null      $modelId   When provided, the model record is updated in place
     * @return array{url: string, path: string, type: string}
     */
    public function store(UploadedFile $file, string $type, ?int $modelId = null): array
    {
        $path = $file->store(
            $type . '/' . now()->format('Y/m'),
            'public'
        );

        $url = Storage::disk('public')->url($path);

        if ($modelId !== null) {
            $this->updateModel($type, $modelId, $url);
        }

        return compact('url', 'path', 'type');
    }

    /**
     * Replace an existing upload: delete the old file, store the new one,
     * and update the model record.
     *
     * @param  UploadedFile  $file
     * @param  string        $type
     * @param  int           $modelId
     * @return array{url: string, path: string, type: string}
     */
    public function replace(UploadedFile $file, string $type, int $modelId): array
    {
        $this->deleteOldFile($type, $modelId);
        return $this->store($file, $type, $modelId);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function updateModel(string $type, int $modelId, string $url): void
    {
        if (!isset(self::MODEL_MAP[$type])) {
            return;
        }

        [$modelClass, $column] = self::MODEL_MAP[$type];

        $model = $modelClass::find($modelId);
        if ($model && in_array($column, $model->getFillable())) {
            $model->update([$column => $url]);
        }
    }

    private function deleteOldFile(string $type, int $modelId): void
    {
        if (!isset(self::MODEL_MAP[$type])) {
            return;
        }

        [$modelClass, $column] = self::MODEL_MAP[$type];

        $model = $modelClass::find($modelId);
        if (!$model) {
            return;
        }

        $existingUrl = $model->{$column} ?? null;
        if (!$existingUrl) {
            return;
        }

        // Convert the public URL back to a storage-relative path and delete it
        $storagePath = str_replace(Storage::disk('public')->url(''), '', $existingUrl);
        if (Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }
    }
}
