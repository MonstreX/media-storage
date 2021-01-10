<?php

namespace MonstreX\MediaStorage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function width(int $width = 0)
    {
        $this->image['width'] = $width;
        return $this;
    }

    public function height(int $height = 0)
    {
        $this->image['height'] = $height;
        return $this;
    }

    public function crop(int $width = 0, int $height = 0)
    {
        $this->image['width'] = $width;
        $this->image['height'] = $height;
        return $this;
    }

    public function format(string $format = '')
    {
        $this->image['format'] = $format;
        return $this;
    }

    public function quality(int $quality = 75)
    {
        $this->image['quality'] = $quality;
        return $this;
    }

    public function url()
    {
        return $this->fileService->url($this->path());
    }

    /*
     * Get path to media file. If parameters passed will generate or return conversions.
     *
     * $media->width(300)->height(400)->format('webp')->url();
     * $media->crop(300,0)->format('webp')->url();
     * path(['format' => 'webp'])
     *
     */    public function path()
    {
        // Case #1 - get original file path, no need conversions
        if (!$this->image['width'] && !$this->image['height'] && !$this->image['format']) {
            return $this->path;
        }

        //"dirname" => "media/2021/01"
        //"basename" => "avatar-19.jpg"
        //"extension" => "jpg"
        //"filename" => "avatar-19"
        $path_info = pathinfo($this->path);

        $conversionPartName = '-conv' .
            ($this->image['width']? '-w' . $this->image['width'] : '') .
            ($this->image['height']? '-h' . $this->image['height'] : '') .
            '.' .
            ($this->image['format']? $this->image['format'] : $path_info['extension']);

        $conversionPath = $path_info['dirname'] . '/' . $path_info['filename'] . $conversionPartName;

        // Case #2 - get conversion file path if file already been converted
        if ($this->fileService->disk($this->disk)->exists($conversionPath)) {
            return $conversionPath;
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

            $image->encode($this->image['format']?? $path_info['extension'], $this->image['quality']);

            $this->fileService->disk($this->disk)->put($conversionPath, (string) $image, $this->disk);

        } catch (\Exception $e) {
            return $e->getMessage();
        }

        // Save new conversion to conversions list in media entry (in order the conversion file can be deleted with media entry later)
        $conversions = json_decode($this->conversions)?? [];

        if (!in_array($conversionPartName, $conversions)) {
            $conversions[] = $conversionPartName;
            $this->update(['conversions' => json_encode($conversions)]);
        }

        $this->resetConversions();

        return $conversionPath;
    }



    public function disk()
    {
        return $this->disk;
    }

    public function fileName()
    {
        return $this->file_name;
    }

    public function mime()
    {
        return $this->mime_type;
    }

    public function size()
    {
        return $this->size;
    }

    public function order()
    {
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

    private function resetConversions()
    {
        $this->image['width'] = 0;
        $this->image['height'] = 0;
        $this->image['format'] = '';
        $this->image['quality'] = 75;
    }

}
