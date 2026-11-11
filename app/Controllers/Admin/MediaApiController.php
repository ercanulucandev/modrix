<?php
namespace App\Controllers\Admin;

use App\Models\Media;
use App\Resources\MediaResource;
use App\Services\MediaService;
use Core\Upload;
use Core\Validator;
use Throwable;
use ZipArchive;

// Medya kütüphanesi API'si: /admin/api/media  (admin oturumu + X-CSRF-TOKEN)
//
//   GET    /             liste  ?q=&type=image|document&page=&per_page=   -> { data, meta }
//   POST   /             yükle (multipart, alan: files[])                 -> 201 { data: [...], errors: [...] }
//   GET    /{id}         tek kayıt                                         -> { data }
//   PUT    /{id}         başlık / alt metin                                -> { data, message }
//   POST   /{id}/edit    düzenlenmiş görsel (file) + mode=copy|replace     -> { data, mode, message }
//   DELETE /{id}         sil                                               -> { message }
//   POST   /bulk         toplu işlem { action: 'delete', ids: [1, 2] }     -> { done: [id], failed: {id: hata}, message }
//   POST   /download     seçilenleri ZIP olarak indir (form alanı: ids[])  -> dosya
class MediaApiController extends AdminController
{
    public const BULK_LIMIT = 200;

    public function index(): array
    {
        $perPage = min(60, max(6, (int) Request::get('per_page', 24)));
        $page = MediaService::paginate(['q' => Request::get('q'), 'type' => Request::get('type')], $perPage);

        return [
            'data' => MediaResource::collection($page['data']),
            'meta' => array_diff_key($page, ['data' => true]) + ['limits' => [
                'max_size'  => MediaService::maxSize(),
                'types'     => [...MediaService::IMAGE_TYPES, ...MediaService::DOCUMENT_TYPES],
            ]],
        ];
    }

    public function store(): string
    {
        [$saved, $errors] = MediaService::uploadFrom('files', $this->admin()['id']);

        if (!$saved && $errors) {
            return $this->json(['error' => 'Dosyalar yüklenemedi', 'errors' => ['files' => implode(' · ', $errors)], 'messages' => $errors], 422);
        }
        if (!$saved) {
            return $this->json(['error' => 'Dosya seçilmedi', 'errors' => ['files' => 'En az bir dosya seçin.']], 422);
        }

        return $this->json([
            'data'    => MediaResource::collection($saved),
            'errors'  => $errors,
            'message' => count($saved) . ' dosya yüklendi.' . ($errors ? ' ' . count($errors) . ' dosya reddedildi.' : ''),
        ], 201);
    }

    // GET /by-path?path=media/2026/11/abc.webp  (editördeki görselin kaydı)
    public function byPath(): array
    {
        $path = preg_replace('#^/?(media/)?#', '', trim((string) Request::get('path', '')));
        $media = Media::findByPath('media/' . preg_replace('#^media/#', '', $path)) ?? Media::findByPath($path);
        if (!$media) $this->abort(404, 'Görsel medya kütüphanesinde bulunamadı');
        return ['data' => MediaResource::make($media)];
    }

    public function show(string $id): array
    {
        return ['data' => MediaResource::make(MediaService::find((int) $id))];
    }

    public function update(string $id): array
    {
        $data = $this->validate(['title' => 'nullable|max:255', 'alt' => 'nullable|max:255'], ['title' => 'Başlık', 'alt' => 'Alt metin']);
        return ['data' => MediaResource::make(MediaService::update((int) $id, $data)), 'message' => 'Kaydedildi.'];
    }

    // Görsel düzenleyici: düzenlenmiş görsel (alan: file) + mode=copy (yeni kayıt) | replace (aynı adres)
    public function edit(string $id): string
    {
        $mode = Request::post('mode') === 'replace' ? 'replace' : 'copy';
        $media = $mode === 'replace'
            ? MediaService::replaceWithEdited((int) $id, 'file')
            : MediaService::saveEditedCopy((int) $id, 'file', $this->admin()['id']);

        return $this->json([
            'data'    => MediaResource::make($media),
            'mode'    => $mode,
            'message' => $mode === 'replace' ? 'Görsel güncellendi.' : 'Düzenlenmiş kopya kütüphaneye eklendi.',
        ], $mode === 'replace' ? 200 : 201);
    }

    public function destroy(string $id): array
    {
        MediaService::delete((int) $id);
        return ['message' => 'Dosya silindi.'];
    }

    // ---- Toplu işlemler

    public function bulk(): string
    {
        if (Request::post('action') !== 'delete') return $this->json(['error' => 'Geçersiz işlem'], 422);
        $ids = $this->ids(Request::post('ids', []));

        $done = $failed = [];
        foreach ($ids as $id) {
            try {
                MediaService::delete($id);
                $done[] = $id;
            } catch (Throwable $e) {
                $failed[$id] = $e->getMessage();
            }
        }
        Log::info('Medya toplu silindi', ['count' => count($done), 'admin' => $this->admin()['id']]);

        return $this->json([
            'done'    => $done,
            'failed'  => (object) $failed,
            'message' => count($done) . ' dosya silindi.' . ($failed ? ' ' . count($failed) . ' dosya silinemedi.' : ''),
        ]);
    }

    // Seçilen dosyaların orijinalleri tek ZIP'te (aynı adlı dosyalar numaralanır)
    public function download(): never
    {
        if (!class_exists(ZipArchive::class)) $this->abort(500, 'Sunucuda PHP zip eklentisi kapalı.');
        $ids = $this->ids(Request::post('ids', []));

        $tmp = tempnam(sys_get_temp_dir(), 'media');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $used = [];
        foreach ($ids as $id) {
            $media = Media::find($id);
            if (!$media || !is_file($file = Upload::path($media['path']))) continue;

            // Ad: yüklenirken verilen ad, uzantı kayıtlı dosyanınki (WebP'ye çevrildiyse .webp)
            $base = trim((string) preg_replace('/[^\p{L}\p{N}._ -]+/u', '-', pathinfo($media['name'], PATHINFO_FILENAME))) ?: 'dosya';
            $ext  = pathinfo($media['path'], PATHINFO_EXTENSION);
            $name = "$base.$ext";
            for ($n = 2; isset($used[mb_strtolower($name)]); $n++) $name = "$base-$n.$ext";
            $used[mb_strtolower($name)] = true;
            $zip->addFile($file, $name);
        }
        $count = $zip->count();
        $zip->close();
        if (!$count) {
            @unlink($tmp);
            $this->abort(404, 'İndirilecek dosya bulunamadı.');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="medya-' . date('Y-m-d-His') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: private, no-store');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function ids($ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids), fn($id) => $id > 0)));
        if (!$ids) Validator::fail(['ids' => 'En az bir dosya seçin.']);
        if (count($ids) > self::BULK_LIMIT) Validator::fail(['ids' => 'Tek seferde en fazla ' . self::BULK_LIMIT . ' dosya seçilebilir.']);
        return $ids;
    }
}
