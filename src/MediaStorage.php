<?php
namespace MonstreX\MediaStorage;

use Exception;
use MonstreX\MediaStorage\Models\Media;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use MonstreX\MediaStorage\Services\MediaService;
use MonstreX\MediaStorage\Services\FileService;
use MonstreX\MediaStorage\Services\URLGeneratorService;

class MediaStorage
{
    protected ?FileService $fileService;

    protected ?URLGeneratorService $generator;

    protected ?MediaService $mediaService;

    protected array $files = [];

    protected ?Model $model = null;

    protected ?Collection $collection = null;

    protected array $props = [];

    protected ?string $collectionName = null;

    protected ?int $collectionId = null;

    protected bool $preserveOriginal = false;

    protected string $transLang = '';

    protected bool $replaceFile = false;

    public function __construct()
    {
        $this->generator = app(config('media-storage.url_generator'));
        $this->mediaService = app(MediaService::class);
        $this->fileService = app(FileService::class);
        $this->fileService->disk(config('media-storage.storage.disk', 'public'));
    }

    /*
     * Add media file source. uploadedFile (or array of uploadedFile entries) or Path string.
     */
    public function add($file, string $disk = 'public'): MediaStorage
    {

        if ($file instanceof UploadedFile || is_string($file)) {
            $this->files[] = $this->fileService->getFileSource($file, $disk);
        }

        if (is_array($file) && $file[0] instanceof UploadedFile) {
            foreach ($file as $fileItem) {
                $this->files[] = $this->fileService->getFileSource($fileItem);
            }
        }

        return $this;
    }

    /*
     * Use to bind to certain model record.
     */
    public function model(Model $model): MediaStorage
    {
        $this->model = $model;
        return $this;
    }

    /*
     * Use specified disk
     */
    public function disk(string $disk = 'local'): MediaStorage
    {
        $this->fileService->disk($disk);

        return $this;
    }

    /*
     * Add properties as array (can be nested)
     */
    public function props(array $props): MediaStorage
    {
        $this->props = $props;
        return $this;
    }

    /*
     * Transliterate file names before saving files
     */
    public function transliterate(string $lang = ''): MediaStorage
    {
        $this->transLang = $lang;
        return $this;
    }

    /*
     * Preserve original files
     */
    public function preserveOriginal(): MediaStorage
    {
        $this->preserveOriginal = true;
        return $this;
    }

    /*
     * Replace target file if exist
     */
    public function replaceFile(): MediaStorage
    {
        $this->replaceFile = true;
        return $this;
    }

    /*
     * Get Media by record ID
     */
    public function find(int $id)
    {
        return $this->mediaService->getByID($id);
    }

    /*
     * Get Media by Media ID
     */
    public function id(int $id)
    {
        return $this->mediaService->getByMediaID($id);
    }

    /*
     * Find collection by Collection ID or Collection Name (using model if present in the instance)
     */
    public function collection($param = null): MediaStorage
    {

        if (is_int($param)) {
            $this->collectionId = $param;
        } elseif (is_string($param)) {
            $this->collectionName = $param;
        }

        return $this;
    }

    /*
     * Retrieve media entries using current storage state
     */
    public function get(): Collection
    {
        $result = $this->mediaService->getMedia(
            $this->model,
            $this->collectionId,
            $this->collectionName
        );

        $this->initMedia();

        return $result;
    }

    /*
     * Retrieve ALL media entries
     */
    public function all(): Collection
    {
        $result = $this->mediaService->getMediaAll();

        $this->initMedia();

        return $result;
    }

    /*
     * Remove one or more media entries (and files)
     */
    public function delete(): int
    {
        $result = $this->removeMediaEntries($this->get());

        $this->initMedia();

        return $result;
    }

    /*
     * Remove ALL media entries (and files)
     */
    public function deleteAll(): int
    {
        $result = $this->removeMediaEntries($this->all());

        $this->initMedia();

        return $result;
    }

    private function removeMediaEntries($collection): int
    {
        if ($collection && count($collection) > 0) {
            foreach ($collection as $media) {
                $this->mediaService->delete($media);
            }
            return count($collection);
        }
        return 0;
    }

    /*
     * Save given collection of media entries to DB
     */
    public function save(Collection $collection): void
    {
        foreach ($collection as $media) {
            $this->mediaService->save($media);
        }

        $this->initMedia();
    }

    /*
     * Create media entries and Save files
     */
    public function create(): Collection
    {
        if (count($this->files) === 0) {
            return collect([]);
        }

        $files = $this->generator->handle(
            [
                'files' => $this->files,
                'disk' => $this->fileService->getDisk(),
                'model' => $this->model,
                'collectionName' => $this->collectionName,
                'transLang' => $this->transLang,
                'replaceFile' => $this->replaceFile
            ]
        );

        $result = $this->mediaService->create(
            [
            'model' => $this->model,
            'collectionId' => $this->collectionId,
            'collectionName' => $this->collectionName,
            'files' => $files,
            'props' => $this->props,
            'preserveOriginal' => $this->preserveOriginal,
            ]
        );

        $this->initMedia();

        return $result;
    }

    /*
     * Reinitializing media class
     */
    private function initMedia(): void
    {
        $this->files = [];
        $this->model = null;
        $this->collection = null;
        $this->props = [];
        $this->collectionName = null;
        $this->collectionId = null;
        $this->preserveOriginal = false;
        $this->transLang = '';
        $this->replaceFile = false;
    }

}