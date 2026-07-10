<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use App\Services\UploadService;

class UploadController extends Controller
{
    public function __construct(protected UploadService $uploadService) {}

    public function upload(UploadImageRequest $request)
    {
        $file    = $request->file('image');
        $type    = $request->input('type');
        $modelId = $request->input('model_id') ? (int) $request->input('model_id') : null;

        // Replace flow: delete the old file and update the model in one step
        $result = $modelId !== null
            ? $this->uploadService->replace($file, $type, $modelId)
            : $this->uploadService->store($file, $type);

        return $this->created($result, 'Image uploaded successfully.');
    }
}
