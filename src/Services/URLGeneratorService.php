<?php

namespace MonstreX\MediaStorage\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
Use Str;

class URLGeneratorService
{

    protected ?FileService $fileService;

    protected string $rootPath = '';

    protected string $yearMonth = '';

    protected string $prefix = '';

    public function __construct()
    {
        $this->fileService = app(FileService::class);
        $this->yearMonth = date('Y').'/'.date('m');
        $this->rootPath = config('media-storage.storage.root', 'media');
    }

    public function handle(array $files, string $disk = 'public', Model $model = null, string $collectionName = null, string $transLang = '', bool $replaceFile = false)
    {

        if ($model) {
            $this->prefix = $model->getTable() . '/';
        }

        $targetPath = $this->rootPath . '/' . $this->prefix . $this->yearMonth . (!empty($collectionName)? '/' . $collectionName : '') ;
        foreach ($files as $key => $file) {

            // Get Original File Info
            $fileName = $file['sourceFile']->getClientOriginalName();
            $fileName = empty($transLang)? $fileName : strtr($fileName, config('media-storage.transliterations.'.$transLang));
            $fileInfo = pathinfo($fileName);

            if (!$replaceFile) {
                $fileCopy = 1;
                while ($this->fileService->exists($targetPath . '/' . $fileName)) {
                    $fileCopy++;
                    $fileName = $fileInfo['filename'] . '-' . $fileCopy . '.' . $fileInfo['extension'];
                }
            }

            $files[$key]['targetDisk'] = $disk;
            $files[$key]['targetPath'] = $targetPath;
            $files[$key]['targetFullPath'] = $targetPath . '/' . $fileName;
            $files[$key]['targetFileName'] = $fileName;
        }

        return $files;
    }


}
