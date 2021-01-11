<?php

namespace MonstreX\MediaStorage\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;
use finfo;
use Storage;

class FileService
{

    protected ?Filesystem $filesystem = null;
    protected string $disk;

    public function __construct()
    {
        $this->filesystem = app(Filesystem::class);
        $this->disk = config('media-storage.storage.disk', 'public');
    }

    public function disk(string $disk = 'public'): object
    {
        $this->disk = $disk;
        return $this;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function url($path): string
    {
        return Storage::url($path);
    }

    public function path($path, $disk): string
    {
        return Storage::disk($disk)->path($path);
    }

    public function exists($filePath): bool
    {
        return Storage::disk($this->disk)->exists($filePath);
    }

    public function copy($fileFrom, string $fileTo)
    {
        return Storage::disk($this->disk)->copy($fileFrom, $fileTo);
    }

    public function put($filePath, $file, string $disk)
    {
        return Storage::disk($this->disk)->put($filePath, $file, $disk);
    }

    public function move($fileFrom, string $fileTo)
    {
        return Storage::disk($this->disk)->move($fileFrom, $fileTo);
    }

    public function makeDirectory($path)
    {
        return Storage::disk($this->disk)->makeDirectory($path);

    }

    public function delete($filePath): bool
    {
        return Storage::disk($this->disk)->delete($filePath);
    }

    public function deleteFile($filePath): bool
    {
        return File::delete($filePath);
    }

    public function moveFile($sourceFile, $targetPath, $targetFileName)
    {
        $media_file = new File($sourceFile);
        return $media_file->move($targetPath, $targetFileName);
    }

    public function getFileSource($file, string $disk = 'public')
    {

        $media_file = null;

        if (is_string($file)) {

            $fileInfo = pathinfo($file);
            $file_path = Storage::disk($disk)->path($file);
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            if (Storage::disk($disk)->exists($file)) {
                $media_file = new UploadedFile(
                    $file_path,
                    $fileInfo['basename'],
                    $finfo->file($file_path),
                    filesize($file_path),
                    0,
                    false
                );
            }

        } elseif ($file instanceof UploadedFile) {
            $media_file = $file;
        }

        return [
            'sourceType' => is_string($file)? 'local': null,
            'sourceFile' => $media_file,
            'targetDisk' => '',
            'targetPath' => '',
            'targetFullPath' => '',
            'targetFileName' => '',
        ];
    }

}
