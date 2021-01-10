<?php

namespace MonstreX\MediaStorage\Services;

use Exception;
use MonstreX\MediaStorage\Models\Media;
use Illuminate\Support\Collection;

class MediaService
{
    protected ?FileService $fileService;

    public function __construct()
    {
        $this->fileService = app(FileService::class);
    }

    /*
     * Get Media by record ID
     */
    public function getByID(int $id)
    {
        return Media::find($id);
    }

    /*
     * Get Media by Media ID
     */
    public function getByMediaID(int $id)
    {
        return Media::where('media_id', $id)->first();
    }

    /*
     * Get Media by Field Name and Value
     */
    public function getByField(string $field, $value)
    {
        return Media::where($field, $value)->get();
    }


    public function delete(Media $entry)
    {
        $entry->delete();
    }

    public function save(Media $entry)
    {
        $entry->save();
    }


    /*
     * Get certain Media Entries
     */
    public function getMedia($model = null, $collectionId = null, $collectionName = null): Collection
    {
        $media_query = Media::where('id', '>', 0);
        // If Model used
        if ($model) {
            $findParam = ['model_type' => get_class($model)];
            $findParam['model_id'] = $model->id;
            $media_query = $media_query->where($findParam);
        }

        // If Collection Name Or ID used
        if ($collectionId || $collectionName) {
            $media_query = $media_query->where($collectionId? ['collection_id' => $collectionId] : ['collection_name' => $collectionName]);
        }

        return $media_query->orderBy('order')->get();
    }

    /*
     * Get All Media Entries
     */
    public function getMediaAll(): Collection
    {
        return Media::all();
    }

    /*
     * Create Media Entry(es)
     */
    public function create($model, $collectionId, $collectionName, $files, $props, $preserveOriginal): Collection
    {
        $result_media = [];

        // Default params
        $order = 1;

        $collection_max = Media::max('collection_id');

        $collection_id = $collection_max? $collection_max + 1 : 1;
        $collection_name = $collectionName;

        // If we have a model in param
        $where = [];
        $model_type = '';
        $model_id = 0;
        if ($model) {
            $model_type = get_class($model);
            $model_id = $model->id;
            $where['model_type'] = $model_type;
            $where['model_id'] = $model_id;
        }

        // If given collection ID Exist in Media - need to get saved collection Name and Order
        if ($collectionId) {
            $collection_id = $collectionId;
            $where['collection_id'] = $collectionId;
            $order = Media::where($where)->max('order') + 1;
            if ($media = Media::where($where)->first()) {
                $collection_name = $media->collection_name;
            }
        // If given collection Name Exist in Media - need to get saved collection ID and Order
        } elseif ($collectionName) {
            $where['collection_name'] = $collectionName;
            $order = Media::where($where)->max('order') + 1;
            if ($media = Media::where($where)->first()) {
                $collection_id = $media->collection_id;
            }
        } elseif ($model) {
            $order = Media::where($where)->max('order') + 1;
        }

        // Save files data to Media table and Copy/Move them to Destination
        foreach ($files as $key => $file) {

            $media = new Media;

            $media->model_type = $model_type;
            $media->model_id = $model_id;

            $media_max = Media::max('media_id');
            $media->media_id = $media_max? $media_max + 1 : 1;
            $media->collection_id = $collection_id;
            $media->collection_name = $collection_name;

            $media->disk = $file['targetDisk'];
            $media->path = $file['targetFullPath'];
            $media->file_name = $file['targetFileName'];
            $media->mime_type = $file['sourceFile']->getMimeType();
            $media->size = $file['sourceFile']->getSize();

            $media->conversions = null;

            $media->props = json_encode($props);
            $media->order = $order;

            try {
                // Create Folder if not exists
                if (!$this->fileService->exists($file['targetPath'])) {
                    $this->fileService->makeDirectory($file['targetPath']);
                }

                $file['sourceFile']->storeAs($file['targetPath'], $file['targetFileName'], $file['targetDisk']);

                if ($file['sourceType'] === 'local' && !$preserveOriginal) {
                    $this->fileService->deleteFile($file['sourceFile']->path());
                }

                $media->save();

                $result_media[] = $media;
                $order++;

            } catch (Exception $exception) {
                throw new \Exception("Error creating media Entry: " . $exception->getMessage());
            }

        }

        return collect($result_media);
    }

}
