<?php

namespace MonstreX\MediaStorage\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
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

    /*
     * Delete media entry and related media file
     */
    public function delete(Media $entry)
    {
        $entry->delete();
    }

    /*
     * Save media entry
     */
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
        if ($model) {
            $findParam = ['model_type' => get_class($model)];
            $findParam['model_id'] = $model->id;
            $media_query = $media_query->where($findParam);
        }

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
    public function create(array $params): Collection
    {
        $modelParams = $this->getModelParams($params);

        $collectionParams = $this->getCollectionParams($params, $modelParams['where']);

        $createdMediaEntries = $this->addMediaEntries($params, $modelParams, $collectionParams);

        return collect($createdMediaEntries);
    }

    private function getModelParams(array $params): array
    {
        $where = [];
        $model_type = '';
        $model_id = 0;

        if ($params['model']) {
            $model_type = get_class($params['model']);
            $model_id = $params['model']->id;
            $where['model_type'] = $model_type;
            $where['model_id'] = $model_id;
        }

        return [
          'where' => $where,
          'model_type' => $model_type,
          'model_id' => $model_id,
        ];
    }

    private function getCollectionParams(array $params, array $where): array
    {
        $order = 1;

        $collection_max = Media::max('collection_id');

        $collection_id = $collection_max? $collection_max + 1 : 1;
        $collection_name = $params['collectionName'];

        // If given collection ID Exist in Media - need to get saved collection Name and Order
        if ($params['collectionId']) {
            $collection_id = $params['collectionId'];
            $where['collection_id'] = $params['collectionId'];
            $order = Media::where($where)->max('order') + 1;
            if ($media = Media::where($where)->first()) {
                $collection_name = $media->collection_name;
            }
            // If given collection Name Exist in Media - need to get saved collection ID and Order
        } elseif ($params['collectionName']) {
            $where['collection_name'] = $params['collectionName'];
            $order = Media::where($where)->max('order') + 1;
            if ($media = Media::where($where)->first()) {
                $collection_id = $media->collection_id;
            }
        } elseif ($params['model']) {
            $order = Media::where($where)->max('order') + 1;
        }

        return [
            'collection_id' => $collection_id,
            'collection_name' => $collection_name,
            'order' => $order,
        ];
    }

    private function addMediaEntries(array $params, array $modelParams, array $collectionParams): array
    {

        $createdMediaEntries = [];

        foreach ($params['files'] as $key => $file) {

            try {

                $media = new Media;

                $media->model_type = $modelParams['model_type'];
                $media->model_id = $modelParams['model_id'];

                $media_max = Media::max('media_id');
                $media->media_id = $media_max? $media_max + 1 : 1;
                $media->collection_id = $collectionParams['collection_id'];
                $media->collection_name = $collectionParams['collection_name'];

                $media->disk = $file['targetDisk'];
                $media->path = $file['targetFullPath'];
                $media->file_name = $file['targetFileName'];
                $media->mime_type = $file['sourceFile']->getMimeType();
                $media->size = $file['sourceFile']->getSize();

                $media->conversions = null;

                $media->props = json_encode($params['props']);
                $media->order = $collectionParams['order'];

                if (!$this->fileService->exists($file['targetPath'])) {
                    $this->fileService->makeDirectory($file['targetPath']);
                }

                $file['sourceFile']->storeAs($file['targetPath'], $file['targetFileName'], $file['targetDisk']);

                if ($file['sourceType'] === 'local' && !$params['preserveOriginal']) {
                    $this->fileService->deleteFile($file['sourceFile']->path());
                }

                $media->save();

                $createdMediaEntries[] = $media;
                $collectionParams['order']++;

            } catch (Exception $exception) {
                throw new \Exception("Error creating media Entry: " . $exception->getMessage());
            }
        }

        return $createdMediaEntries;

    }

}
