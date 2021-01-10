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

    /*
     * throw new Exception(__('media-storage::exception.error'));
     *
     * Media model fields:
     *
     * id - Record ID
     * uuid - Unique Element ID (needs for identify when an Element can be moved to another Record with different ID)
     * group_id - Unique Group ID (inside group - not unique)
     * group_name - Group name
     * group_order - Order num inside a group
     * owner_model - Model class
     * owner_id - Record id of an owner model
     * ----- File params
     * original_name - Original file name
     * stored_disk - Disk where file has stored
     * stored_path - Path of file
     * stored_name - File name with Ext
     * ext - Extension
     * mime_type - File type in MIME
     * size - File size
     * properties - Custom properties
     *
     */

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
    public function add($file, string $disk = 'public')
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
    public function model(Model $model)
    {
        $this->model = $model;
        return $this;
    }

    /*
     * Use specified disk
     */
    public function disk(string $disk = 'local')
    {
        $this->fileService->disk($disk);

        return $this;
    }

    /*
     * Add properties as array (can be nested)
     */
    public function props(array $props)
    {
        $this->props = $props;
        return $this;
    }

    /*
     * Transliterate file names before saving files
     */
    public function transliterate(string $lang = '')
    {
        $this->transLang = $lang;
        return $this;
    }

    /*
     * Preserve original files
     */
    public function preserveOriginal()
    {
        $this->preserveOriginal = true;
        return $this;
    }

    /*
     * Replace target file if exist
     */
    public function replaceFile()
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
    public function collection($param = null)
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
    public function all()
    {
        $result = $this->mediaService->getMediaAll();

        $this->initMedia();

        return $result;
    }

    /*
     * Remove one or more media entries (and files)
     */
    public function delete():int
    {
        $result = $this->removeMediaEntries($this->get());

        $this->initMedia();

        return $result;
    }

    /*
     * Remove ALL media entries (and files)
     */
    public function deleteAll():int
    {
        $result = $this->removeMediaEntries($this->all());

        $this->initMedia();

        return $result;
    }

    private function removeMediaEntries($collection):int
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
            $this->files,
            $this->fileService->getDisk(),
            $this->model,
            $this->collectionName,
            $this->transLang,
            $this->replaceFile
        );

        $result = $this->mediaService->create(
            $this->model,
            $this->collectionId,
            $this->collectionName,
            $files,
            $this->props,
            $this->preserveOriginal
        );

        $this->initMedia();

        return $result;
    }

    /*
     * Reinitializing media class
     */
    private function initMedia()
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