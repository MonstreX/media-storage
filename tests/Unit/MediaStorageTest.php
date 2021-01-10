<?php

namespace MonstreX\MediaStorage\Tests\Unit;

use MonstreX\MediaStorage\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use MonstreX\MediaStorage\Facades\Media;
use Illuminate\Database\Eloquent\Model;
use Intervention\Image\Facades\Image;

class Test extends Model {}

class MediaStorageTest extends TestCase
{

    public function testCropImage()
    {
        $stored = Media::add($this->files[0])->create();

        $media_id = $stored[0]->media_id;

        $media = Media::id($media_id);
        $disk = $media->disk();

        $crop0 = $media->path();
        $crop1 = $media->crop(300,300)->path();
        $crop2 = $media->crop(500,500)->path();
        $crop3 = $media->crop(250,0)->path();
        $crop4 = $media->crop(0,350)->path();
        $crop5 = $media->format('webp')->path();

        Storage::disk($disk)->assertExists($crop0);
        Storage::disk($disk)->assertExists($crop1);
        Storage::disk($disk)->assertExists($crop2);
        Storage::disk($disk)->assertExists($crop3);
        Storage::disk($disk)->assertExists($crop4);
        Storage::disk($disk)->assertExists($crop5);

        $image1 = Image::make(Storage::disk($disk)->path($crop1));
        $image2 = Image::make(Storage::disk($disk)->path($crop2));
        $image3 = Image::make(Storage::disk($disk)->path($crop3));
        $image4 = Image::make(Storage::disk($disk)->path($crop4));
        $image5 = Image::make(Storage::disk($disk)->path($crop5));

        $this->assertSame(300, $image1->width());
        $this->assertSame(500, $image2->height());
        $this->assertSame(250, $image3->width());
        $this->assertSame(350, $image4->height());
        $this->assertSame('image/webp', $image5->mime());

        $deleted = $media->delete();

        $this->assertTrue($deleted === true);

        Storage::disk($disk)->assertMissing($crop0);
        Storage::disk($disk)->assertMissing($crop1);
        Storage::disk($disk)->assertMissing($crop2);
        Storage::disk($disk)->assertMissing($crop3);
        Storage::disk($disk)->assertMissing($crop4);
        Storage::disk($disk)->assertMissing($crop5);

    }

    public function testAddGetDeleteOneFileAsUploadFile()
    {
        $stored = Media::add($this->files[0])->create();

        $this->assertTrue($stored instanceof \Illuminate\Support\Collection); // Should return Collection

        $media_id = $stored[0]->media_id;

        $media = Media::id($media_id);

        $disk = $media->disk();
        $path = $media->path();

        Storage::disk($disk)->assertExists($path); // If new file added and exist

        $this->assertFalse(empty($media->id));
        $this->assertFalse(empty($media->media_id));
        $this->assertFalse(empty($media->collection_id));
        $this->assertTrue((int) $media->model_id === 0);
        $this->assertTrue((int) $media->order === 1);
        $this->assertTrue($media->collection_name === null);

        $media->delete();

        $media = Media::id($media_id);

        $this->assertTrue($media === null); // Should be empty (media has been deleted)

        Storage::disk($disk)->assertMissing($path); // Should not exist (deleted with media entry)
    }


    public function testAddDeleteOneFileAsPath()
    {
        $stored = Media::add($this->files[0])->create();

        $disk = $stored[0]->disk();
        $path = $stored[0]->path();

        $media = Media::add($path, $disk)->preserveOriginal()->create();
        $media = Media::id($media[0]->media_id);

        Storage::disk($disk)->assertExists($path); // If new file added and exist
        Storage::disk($media->disk())->assertExists($media->path()); // If new file added and exist

        $media->delete();
        $stored[0]->delete();

        Storage::disk($disk)->assertMissing($path); // If source file has been deleted
        Storage::disk($media->disk())->assertMissing($media->path()); // If new file has been deleted
    }

    public function testAddWithModel()
    {

        $record1 = new Test;
        $record2 = new Test;
        $record1->id = 1;
        $record2->id = 2;

        Media::add($this->files[0])->model($record1)->collection('images')->create();
        Media::add($this->files[1])->model($record1)->collection('images')->create();

        Media::add($this->files[0])->model($record1)->collection('gallery')->create();
        Media::add($this->files[1])->model($record1)->collection('gallery')->create();
        Media::add($this->files[2])->model($record1)->collection('gallery')->create();
        Media::add($this->files[3])->model($record1)->collection('gallery')->create();
        Media::add($this->files[4])->model($record1)->collection('gallery')->create();


        Media::add($this->files[2])->model($record2)->collection('images')->create();
        Media::add($this->files[3])->model($record2)->collection('images')->create();
        Media::add($this->files[4])->model($record2)->collection('images')->create();


        $media1 = Media::model($record1)->collection('images')->get();
        $media2 = Media::model($record2)->collection('images')->get();

        $media3 = Media::model($record1)->collection('gallery')->get();

        $media4 = Media::model($record1)->get();

        $this->assertTrue(count($media1) === 2);
        $this->assertTrue(count($media2) === 3);
        $this->assertTrue(count($media3) === 5);
        $this->assertTrue(count($media4) === 7);

        $deleted2 = Media::model($record2)->collection('images')->delete();
        $deleted4 = Media::model($record1)->delete();

        $this->assertTrue($deleted2 === 3);
        $this->assertTrue($deleted4 === 7);
    }

    public function testAddGetDeleteManyFiles()
    {

        $files1 = [$this->files[1], $this->files[2]];
        $files2 = [$this->files[3], $this->files[4], $this->files[5]];

        $stored1 = Media::add($files1)->collection('2-files')->create();
        $stored2 = Media::add($files2)->collection('3-files')->create();

        $this->assertTrue(count($stored1) === 2);
        $this->assertTrue(count($stored2) === 3);

        // Get by Collection name
        $media1 = Media::collection('2-files')->get();
        $media2 = Media::collection('3-files')->get();

        $this->assertTrue(count($media1) === 2);
        $this->assertTrue(count($media2) === 3);

        // Get by Collection ID
        $media1 = Media::collection($stored1[0]->collection_id)->get();
        $media2 = Media::collection($stored2[0]->collection_id)->get();

        $this->assertTrue(count($media1) === 2);
        $this->assertTrue(count($media2) === 3);

        // Delete Both collections
        $media1 = Media::collection($stored1[0]->collection_id)->delete();
        $media2 = Media::collection($stored2[0]->collection_id)->delete();

        // Check if Deleted success
        $this->assertTrue($media1 === 2);
        $this->assertTrue($media2 === 3);

        // Check if Deleted Files
        Storage::disk($stored1[0]->disk())->assertMissing($stored1[0]->path());
        Storage::disk($stored1[1]->disk())->assertMissing($stored1[1]->path());
        Storage::disk($stored2[0]->disk())->assertMissing($stored2[0]->path());
        Storage::disk($stored2[1]->disk())->assertMissing($stored2[1]->path());
        Storage::disk($stored2[2]->disk())->assertMissing($stored2[2]->path());

        // Check if Deleted Media records
        $media1 = Media::collection($stored1[0]->collection_id)->get();
        $media2 = Media::collection($stored2[0]->collection_id)->get();

        $this->assertTrue(count($media1) === 0);
        $this->assertTrue(count($media2) === 0);

    }

    public function testAddingToExistingCollection()
    {
        $files1 = [$this->files[1], $this->files[2]];
        $files2 = [$this->files[3], $this->files[4], $this->files[5]];

        $stored1 = Media::add($files1)->collection('gallery-of-5-files')->create();
        $stored2 = Media::add($files2)->collection('gallery-of-5-files')->create();

        $media = Media::collection('gallery-of-5-files')->get();

        $this->assertTrue(count($media) === 5);

        $media[0]->delete();
        $media[1]->delete();

        $media = Media::collection('gallery-of-5-files')->delete();

        $this->assertTrue($media === 3);

    }

    public function testProps()
    {
        $stored = Media::add($this->files[0])->collection('props')->props(['title' => 'Main title', 'attrs' => ['width' => 500, 'height' => 700]])->create();

        $this->assertTrue(is_object($stored[0]->props()));
        $this->assertTrue($stored[0]->prop('title') === 'Main title');
        $this->assertTrue($stored[0]->prop('attrs.width') === 500);

        $stored[0]->prop('title','New title');
        $stored[0]->prop('attrs.width', 1500);

        $stored[0]->save();

        $this->assertTrue($stored[0]->prop('title') === 'New title');
        $this->assertTrue($stored[0]->prop('attrs.width') === 1500);

        $media = Media::collection('props')->delete();

        $this->assertTrue($media === 1);
    }


    public function testSaveOrder()
    {
        $files = [$this->files[3], $this->files[4], $this->files[5]];

        $stored = Media::add($files)->collection('ordered-files')->create();

        $media = Media::collection('ordered-files')->get();

        $media[0]->order = 106;
        $media[1]->order = 105;
        $media[2]->order = 104;

        Media::save($media);

        $media2 = Media::collection('ordered-files')->get();

        $this->assertTrue((int) $media2[0]->order() === 104);
        $this->assertTrue((int) $media2[1]->order() === 105);
        $this->assertTrue((int) $media2[2]->order() === 106);

        Media::collection('ordered-files')->delete();
    }


}
