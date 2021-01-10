<?php

namespace MonstreX\MediaStorage\Tests;

use Illuminate\Http\UploadedFile;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use MonstreX\MediaStorage\MediaStorageServiceProvider;
use MonstreX\MediaStorage\Facades\Media;
use MonstreX\MediaStorage\Services\FileService;

use Intervention\Image\Facades\Image;
use Intervention\Image\ImageServiceProvider;

class TestCase extends OrchestraTestCase
{

    protected $files = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->loadMigrations();

        $this->initTest();

    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        //$app['config']->set('voyager.user.namespace', User::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            MediaStorageServiceProvider::class,
            ImageServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Media' => Media::class,
            'Image' => Image::class,
        ];
    }

    protected function loadMigrations(): void
    {
        // Run Data Base
        $this->loadLaravelMigrations(['--database' => 'testbench']);
        $this->loadMigrationsFrom([
            '--database' => 'testbench',
            '--path' => realpath(__DIR__.'/../migrations'),
        ]);
    }

    private function initTest()
    {
        $fileService = (new FileService);
        $fileService->disk('media');

        $this->files = [
            UploadedFile::fake()->image('avatar.jpg'),
            UploadedFile::fake()->image('avatar-one.jpg'),
            UploadedFile::fake()->image('avatar-two.jpg'),
            UploadedFile::fake()->image('avatar-three.jpg'),
            UploadedFile::fake()->image('avatar-four.jpg'),
            UploadedFile::fake()->image('avatar-five.jpg'),
        ];

        // Make one test record
        //$now = now();
        //\DB::table('media')->insert([
        //    'model_type' => 'App\User',
        //    'model_id' => 2,
        //    'media_id' => 1,
        //    'collection_id' => 1,
        //    'collection_name' => 'gallery',
        //    'conversions' => null,
        //    'disk' => 'public',
        //    'path' => 'media/users/2020/gallery/test.png',
        //    'file_name' => 'test.png',
        //    'mime_type' => 'image/png',
        //    'size' => 7777,
        //    'props' => '{"title": "Title test"}',
        //    'order' => 1,
        //    'created_at' => $now,
        //    'updated_at' => $now,
        //]);

    }

}