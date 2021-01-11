<?php

namespace MonstreX\MediaStorage\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
Use Str;

class URLGeneratorService
{

    protected ?FileService $fileService;

    public function __construct()
    {
        $this->fileService = app(FileService::class);
    }

    public function handle(array $files, array $params): array
    {

        $targetPath = config('media-storage.storage.root', 'media') . '/' .
                        ($params['model']? $prefix = $params['model']->getTable() . '/' : '') .
                        date('Y') . '/'. date('m') .
                        (!empty($params['collectionName'])? '/' . $params['collectionName'] : '') ;

        foreach ($files as $key => $file) {

            // Get Original File Info
            $fileName = $file['sourceFile']->getClientOriginalName();
            $fileName = empty($params['transLang'])? $fileName : strtr($fileName, config('media-storage.transliterations.'.$params['transLang']));
            $fileInfo = pathinfo($fileName);

            if (!$params['replaceFile']) {
                $fileCopy = 1;
                while ($this->fileService->exists($targetPath . '/' . $fileName)) {
                    $fileCopy++;
                    $fileName = $fileInfo['filename'] . '-' . $fileCopy . '.' . $fileInfo['extension'];
                }
            }

            $files[$key]['targetDisk'] = $params['disk'];
            $files[$key]['targetPath'] = $targetPath;
            $files[$key]['targetFullPath'] = $targetPath . '/' . $fileName;
            $files[$key]['targetFileName'] = $fileName;
        }

        return $files;
    }


}
