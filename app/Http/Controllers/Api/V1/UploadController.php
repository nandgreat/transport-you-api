<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function upload(UploadImageRequest $request)
    {
        $type = $request->input('type');
        $file = $request->file('image');

        $path = $file->store($type, 'public');
        $url  = Storage::disk('public')->url($path);

        return $this->created([
            'url'  => $url,
            'path' => $path,
            'type' => $type,
        ], 'Image uploaded successfully.');
    }
}
