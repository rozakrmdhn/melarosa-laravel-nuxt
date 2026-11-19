<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InfrastrukturSegmen;
use App\Models\InfrastrukturSegmenFoto;
use App\Models\User;
use App\Services\JadwalAnggaranService;
use App\Services\SegmenFotoSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class InfrastrukturSegmenFotoController extends Controller
{
    public function __construct(
        private readonly SegmenFotoSecurityService $securityService,
        private readonly JadwalAnggaranService $jadwalService,
    ) {
    }

    public function index(Request $request, string $id): JsonResponse
    {
        $segmen = InfrastrukturSegmen::find($id);
        if (!$segmen) {
            return response()->json(['ok' => false, 'message' => 'Segmen infrastruktur tidak ditemukan.'], 404);
        }

        Gate::authorize('view', $segmen);

        if (!$this->canViewTerritory($request->user(), $segmen)) {
            return response()->json(['ok' => false, 'message' => 'Anda tidak memiliki akses melihat dokumentasi wilayah ini.'], 403);
        }

        $fotos = $segmen->fotos()->with('uploader:uuid,name')->get();

        return response()->json([
            'ok'   => true,
            'data' => $fotos,
            'meta' => [
                'max_fotos'       => InfrastrukturSegmenFoto::MAX_PER_SEGMEN,
                'ideal_min_fotos' => InfrastrukturSegmenFoto::IDEAL_MIN_PER_SEGMEN,
                'total'           => $fotos->count(),
            ],
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $segmen = InfrastrukturSegmen::find($id);
        if (!$segmen) {
            return response()->json(['ok' => false, 'message' => 'Segmen infrastruktur tidak ditemukan.'], 404);
        }

        Gate::authorize('update', $segmen);

        $user = $request->user();
        $scheduleCheck = $this->jadwalService->checkAccessForSegmen($user, $segmen);
        if (!$scheduleCheck['allowed']) {
            return response()->json([
                'ok'      => false,
                'message' => $scheduleCheck['message'],
            ], 403);
        }

        $validated = $request->validate([
            'foto' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
            'tahap_label' => [
                'nullable',
                'string',
                Rule::in(InfrastrukturSegmenFoto::TAHAP_LABELS),
            ],
            'keterangan' => ['nullable', 'string', 'max:200'],
        ], [
            'foto.required'  => 'File foto wajib dipilih.',
            'foto.mimes'     => 'Hanya file gambar JPG, JPEG, PNG, atau WebP yang diizinkan.',
            'foto.mimetypes' => 'Tipe MIME file tidak diizinkan (hanya JPG, PNG, WebP).',
            'foto.max'       => 'Ukuran file foto maksimal 5 MB.',
        ]);

        // Sanitasi & decode-reencode ketat sebelum menyimpan ke private storage
        $sanitized = $this->securityService->sanitizeAndReencode($request->file('foto'));
        $caption = $this->securityService->sanitizeCaption($validated['keterangan'] ?? null);
        $tahap = $validated['tahap_label'] ?? null;

        $disk = 'local';
        $relativePath = "infrastruktur-segmen/{$segmen->id}/" . Str::uuid7() . '.webp';

        try {
            $foto = DB::transaction(function () use ($segmen, $user, $sanitized, $caption, $tahap, $disk, $relativePath) {
                // Lock segmen agar batas 4 foto aman terhadap upload paralel
                InfrastrukturSegmen::where('id', $segmen->id)->lockForUpdate()->first();

                $existingCount = InfrastrukturSegmenFoto::where('segmen_id', $segmen->id)->count();
                if ($existingCount >= InfrastrukturSegmenFoto::MAX_PER_SEGMEN) {
                    abort(422, 'Maksimal ' . InfrastrukturSegmenFoto::MAX_PER_SEGMEN . ' foto dokumentasi lapangan per segmen.');
                }

                $nextUrutan = (int) InfrastrukturSegmenFoto::where('segmen_id', $segmen->id)->max('urutan') + 1;
                $resolvedTahap = $tahap ?: (InfrastrukturSegmenFoto::TAHAP_LABELS[$existingCount] ?? 'Detail Kondisi');

                Storage::disk($disk)->put($relativePath, $sanitized['binary']);

                $created = InfrastrukturSegmenFoto::create([
                    'segmen_id'   => $segmen->id,
                    'urutan'      => $nextUrutan,
                    'tahap_label' => $resolvedTahap,
                    'keterangan'  => $caption,
                    'disk'        => $disk,
                    'path'        => $relativePath,
                    'mime_type'   => $sanitized['mime'],
                    'ukuran_byte' => $sanitized['size'],
                    'lebar_px'    => $sanitized['width'],
                    'tinggi_px'   => $sanitized['height'],
                    'sha256'      => $sanitized['sha256'],
                    'uploaded_by' => $user?->uuid,
                ]);

                $this->syncPrimaryFotoUrl($segmen);

                return $created;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($relativePath);
            throw $e;
        }

        $foto->load('uploader:uuid,name');
        $fotos = $segmen->fotos()->with('uploader:uuid,name')->get();

        return response()->json([
            'ok'      => true,
            'message' => 'Foto dokumentasi berhasil diunggah dan disanitasi.',
            'data'    => $foto,
            'fotos'   => $fotos,
        ], 201);
    }

    public function destroy(Request $request, string $id, string $fotoId): JsonResponse
    {
        $segmen = InfrastrukturSegmen::find($id);
        if (!$segmen) {
            return response()->json(['ok' => false, 'message' => 'Segmen infrastruktur tidak ditemukan.'], 404);
        }

        Gate::authorize('update', $segmen);

        $scheduleCheck = $this->jadwalService->checkAccessForSegmen($request->user(), $segmen);
        if (!$scheduleCheck['allowed']) {
            return response()->json([
                'ok'      => false,
                'message' => $scheduleCheck['message'],
            ], 403);
        }

        $foto = InfrastrukturSegmenFoto::where('segmen_id', $segmen->id)
            ->where('id', $fotoId)
            ->first();

        if (!$foto) {
            return response()->json(['ok' => false, 'message' => 'Foto dokumentasi tidak ditemukan.'], 404);
        }

        $disk = $foto->disk ?: 'local';
        $path = $foto->path;

        $foto->delete();
        if ($path && $this->isSafeStoredPath($segmen->id, $path)) {
            Storage::disk($disk)->delete($path);
        }

        $this->syncPrimaryFotoUrl($segmen);
        $fotos = $segmen->fotos()->with('uploader:uuid,name')->get();

        return response()->json([
            'ok'      => true,
            'message' => 'Foto dokumentasi dihapus.',
            'fotos'   => $fotos,
        ]);
    }

    public function file(Request $request, string $id, string $fotoId): Response
    {
        $segmen = InfrastrukturSegmen::find($id);
        if (!$segmen) {
            abort(404, 'Segmen tidak ditemukan.');
        }

        Gate::authorize('view', $segmen);

        if (!$this->canViewTerritory($request->user(), $segmen)) {
            abort(403, 'Akses ditolak.');
        }

        $foto = InfrastrukturSegmenFoto::where('segmen_id', $segmen->id)
            ->where('id', $fotoId)
            ->first();

        if (!$foto || !$this->isSafeStoredPath($segmen->id, (string) $foto->path)) {
            abort(404, 'Foto tidak ditemukan.');
        }

        $disk = Storage::disk($foto->disk ?: 'local');
        if (!$disk->exists($foto->path)) {
            abort(404, 'Berkas foto tidak tersedia di penyimpanan privat.');
        }

        $bytes = $disk->get($foto->path);

        return response($bytes, 200, [
            'Content-Type'            => 'image/webp',
            'Content-Disposition'     => 'inline; filename="' . $foto->id . '.webp"',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control'           => 'private, max-age=3600',
        ]);
    }

    private function canViewTerritory(?User $user, InfrastrukturSegmen $segmen): bool
    {
        if (!$user || $user->isAdminOrBappeda()) {
            return true;
        }
        if ($user->id_desa) {
            return (int) $segmen->id_desa === (int) $user->id_desa;
        }
        if ($user->id_kecamatan) {
            return (int) $segmen->id_kecamatan === (int) $user->id_kecamatan;
        }

        return true;
    }

    private function isSafeStoredPath(string $segmenId, string $path): bool
    {
        return !str_contains($path, '..')
            && str_starts_with($path, "infrastruktur-segmen/{$segmenId}/")
            && str_ends_with($path, '.webp');
    }

    private function syncPrimaryFotoUrl(InfrastrukturSegmen $segmen): void
    {
        $first = InfrastrukturSegmenFoto::where('segmen_id', $segmen->id)
            ->orderBy('urutan')
            ->orderBy('created_at')
            ->first();

        $segmen->foto_url = $first?->file_endpoint;
        $segmen->saveQuietly();
    }
}
