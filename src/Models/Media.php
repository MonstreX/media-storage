<?php

namespace MonstreX\MediaStorage\Models;

use Illuminate\Database\Eloquent\Model;
use MonstreX\MediaStorage\Services\FileService;
use Intervention\Image\Facades\Image;
use Storage;
use Arr;
use Str;

class Media extends Model
{
    protected $table = 'media';

    protected $guarded = [];

    protected ?FileService $fileService;

    protected $image = ['width' => 0, 'height' => 0, 'format' => '', 'quality' => 75];

    public function __construct()
    {
        parent::__construct();
        $this->fileService = app(FileService::class);
    }

    /*
     * Set Width for conversion
     */
    public function width(int $width = 0): Model
    {
        $this->image['width'] = $width;
        return $this;
    }

    /*
     * Set Height for conversion
     */
    public function height(int $height = 0): Model
    {
        $this->image['height'] = $height;
        return $this;
    }

    /*
     * Set width and height for crop conversion. One of parameters can be equal to 0
     */
    public function crop(int $width = 0, int $height = 0): Model
    {
        $this->image['width'] = $width;
        $this->image['height'] = $height;
        return $this;
    }

    /*
     * Set new format for conversion
     */
    public function format(string $format = ''): Model
    {
        $this->image['format'] = $format;
        return $this;
    }

    /*
     * Set media file quality
     */
    public function quality(int $quality = 75): Model
    {
        $this->image['quality'] = $quality;
        return $this;
    }

    /*
     * Check if media file exist
     */
    public function exist(): bool
    {
        $path = $this->path;

        if ($this->image['width'] || $this->image['height'] || $this->image['format']) {
            $conversionData = $this->getConversionData($this->path);
            $path = $conversionData['path'];
        }

        $this->resetConversions();

        return $this->fileService->disk($this->disk)->exists($path);
    }

    /*
     * Get Relative URL
     */
    public function url(): string
    {
        return $this->fileService->url($this->path());
    }

    /*
     * Get Full URL
     */
    public function fullUrl(): string
    {
        return url($this->fileService->url($this->path()));
    }

    /*
     * Get Path to media file. If parameters passed will generate or return conversions.
     * $media->width(300)->height(400)->format('webp')->url();
     * $media->crop(300,0)->format('webp')->url();
     */
    public function path(): string
    {
        // Case #1 - get original file path, no need conversions
        if (!$this->image['width'] && !$this->image['height'] && !$this->image['format']) {
            return $this->path;
        }

        $conversionData = $this->getConversionData($this->path);

        // Case #2 - get conversion file path if file already been converted
        if ($this->fileService->disk($this->disk)->exists($conversionData['path'])) {
            return $conversionData['path'];
        }

        // Case #3 - create NEW conversion and save it
        try {
            // Take master media file
            $image = Image::make($this->fileService->path($this->path, $this->disk));

            if ($this->image['width'] && $this->image['height']) {
                $image->fit($this->image['width'], $this->image['height']);
            } elseif($this->image['width'] || $this->image['height']) {

                $width = $this->image['width'] !== 0? $this->image['width'] : null;
                $height = $this->image['height'] !==0? $this->image['height'] : null;

                $image->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                });
            }

            $image->encode($this->image['format']?? $conversionData['extension'], $this->image['quality']);

            $this->fileService->disk($this->disk)->put($conversionData['path'], (string) $image, $this->disk);

            // Save new conversion to conversions list in media entry (in order the conversion file can be deleted with a media entry later)
            $conversions = json_decode($this->conversions)?? [];

            if (!in_array($conversionData['partName'], $conversions)) {
                $conversions[] = $conversionData['partName'];
                $this->update(['conversions' => json_encode($conversions)]);
            }

        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $this->resetConversions();

        return $conversionData['path'];
    }

    /*
     * Get conversion file name and path
     */
    private function getConversionData(string $path): array
    {
        $path_info = pathinfo($path);

        $conversionPartName = '-conv' .
            ($this->image['width']? '-w' . $this->image['width'] : '') .
            ($this->image['height']? '-h' . $this->image['height'] : '') .
            '.' .
            ($this->image['format']? $this->image['format'] : $path_info['extension']);

        return [
            'partName' => $conversionPartName,
            'path' => $path_info['dirname'] . '/' . $path_info['filename'] . $conversionPartName,
            'extension' => $path_info['extension'],
        ];
    }

    /*
     * Get disk name
     */
    public function disk(): string
    {
        return $this->disk;
    }

    /*
     * Get file name and extension
     */
    public function fileName(): string
    {
        return $this->file_name;
    }

    /*
     * Get mime type
     */
    public function mime(): string
    {
        return $this->mime_type;
    }

    /*
     * Get file size
     */
    public function size(): int
    {
        return $this->size;
    }

    /*
     * Get or Set Order field
     */
    public function order(int $order = null)
    {
        if ($order) {
            $this->order = $order;
            return $this;
        }
        return $this->order;
    }

    /*
     * Get or Set Properties
     */
    public function props(array $props = null)
    {
        if (!$props) {
            $props = json_decode($this->props);
            if ($props) {
                return $props;
            }
            return null;
        } else {
            $this->props = json_encode($props);
            return $this;
        }
    }

    /*
     * Get or Set ONE property, use DOT notation $media->prop('attrs.height',100);
     */
    public function prop(string $key, $prop_value = null)
    {
        $array = json_decode($this->props,true);

        if (!$prop_value) {
            return Arr::get($array, $key);
        } else {
            Arr::set($array, $key, $prop_value);
            $this->props = json_encode($array);
            return $this;
        }
    }

    /*
     * Remove related media files
     */
    public static function boot()
    {
        parent::boot();
        static::deleting(function($model)
        {
            $fileService = app(FileService::class);
            if ($fileService->disk($model->disk)->exists($model->path)) {
                $fileService->disk($model->disk)->delete($model->path);
            }

            // Delete generated files if present for this media entry
            $conversions = json_decode($model->conversions)?? [];
            foreach ($conversions as $conversion) {
                $path_info = pathinfo($model->path);
                $conversionPath = $path_info['dirname'] . '/' . $path_info['filename'] . $conversion;

                if ($fileService->disk($model->disk)->exists($conversionPath)) {
                    $fileService->disk($model->disk)->delete($conversionPath);
                }
            }

        });
    }

    private function resetConversions(): void
    {
        $this->image['width'] = 0;
        $this->image['height'] = 0;
        $this->image['format'] = '';
        $this->image['quality'] = 75;
    }

}
