<?php

namespace App\Http\Controllers;

use App\Models\TemporaryUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function image(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'entity' => ['required', 'string', 'in:avatars'],
            'width' => 'nullable|integer|min:1|max:1920',
            'height' => 'nullable|integer|min:1|max:1920',
        ]);

        $extension = 'webp';

        $path = $request->file('image')
            ->convert($request->width, $request->height, $extension)
            ->storeAs(
                $request->entity,
                implode('.', [Str::uuid7(), $extension]),
                ['disk' => 'public']
            );

        TemporaryUpload::create([
            'path' => $path,
        ]);

        return response()->json([
            'ok' => true,
            'path' => $path,
        ]);
    }

    public function document(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,xlsx,xls,jpg,jpeg,png', 'max:10240'],
            'entity' => ['required', 'string', 'in:bkkd-lampiran,bkkd-verifikasi'],
        ]);

        $file = $request->file('file');
        $extension = $file->extension() ?: $file->guessExtension() ?: 'bin';
        $path = $file->storeAs(
            'temporary/'.$request->entity,
            implode('.', [Str::uuid7(), $extension]),
            ['disk' => 'local']
        );

        TemporaryUpload::create([
            'path' => $path,
        ]);

        return response()->json([
            'ok' => true,
            'path' => $path,
            'nama_asli' => $file->getClientOriginalName(),
            'ukuran_byte' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
    }
}
