# Laravel Media Storage

This package allow you to manage different media files in your project. The package was inspired by Spatie Medialibrary package. But this package also can handle files without binding to certain model's records. So you can use Spatie Medialibary way to store files or you can use independed media collection don't belong any models at all. Also the package can manipulate some image conversions.


## Installing
>composer require monstrex/media-storage

Publish config:
``` bash
$ php artisan vendor:publish --provider="MonstreX\MediaStorage\MediaStorageServiceProvider" --tag="config"
```

>You may use your own way to generate target file names using custom generator in config file:  
>'url_generator' => MonstreX\MediaStorage\Services\URLGeneratorService::class, 

Make migrations:
``` bash
$ php artisan vendor:publish --provider="MonstreX\MediaStorage\MediaStorageServiceProvider" --tag="migrations"
$ php artisan migrate
```

## Adding
```php
$user1 = User::find(1);
// Add file from request and bind the file to model using collection name (string)
$collection = Media::add(request()->file('myfile'))->model($user1)->collection('new-gallery')->create();
// Add file from path and disk
$collection = Media::add('images/1.jpg', 'local')->collection('local-file')->create();
// Add file using Collection ID (int)
$collection = Media::model($model)->add($files)->collection(5403)->create();
// Add file with custom properties
$collection = Media::add($file)->props(['title' => 'Novus news','alt' => 'Image #2'])->collection('images')->create();
// Add files using noname collection. You can get collection ID from returned collection - collection_id field. 
$collection = Media::add($files)->create();
// Will save original source file as is
$collection = Media::add('images/1.jpg', 'local')->preserveOriginal()->create();
// Will replace target file if exist. 
$collection = Media::add($file)->replaceFile()->create();
// Will replace target file name using language table from config file
$collection = Media::add($file)->transliterate('ru')->collection('cyrillic-gallery')->create();
```
 
## Retrieving
```php
// Get path by record ID
$path = Media::find(1)->path(); 
// Get path by MEDIA ID
$path = Media::id(3)->path();   
// Get relative URL
$url = Media::id(3)->url();
// Get full URL
$url = Media::id(3)->fullUrl();
// Generate conversion if not exist and return URL     
$url = Media::id(3)->crop(250,300)->url(); 
// Get collection by collection ID 
$collection = Media::collection(5403)->get();
// Get collection by collection Name
$collection = Media::model($user1)->collection('new-gallery')->get();
// Get All media entries
$collection = Media::all();
// Get all properties
Media::id(3)->props();
// Get certain propery
Media::id(3)->prop('title');
Media::id(3)->prop('setting.width');
```

## Modify 
```php
// Set properties
Media::id(3)->props(['title' => 'New title'])->save();
// Set one property
Media::id(3)->prop('settings.width',500)->save();
// Set order
$collection = Media::collection('new-gallery')->get();
$collection[0]->order(1);
$collection[1]->order(2);
$collection[2]->order(3); 
Media::save($collection);
```

## Removing
```php
// Delete one media entry
Media::id(3)->delete();
// Delete whole collection
Media::collection(5403)->delete();
// Delete collection binded to model
Media::model($user1)->collection('new-gallery')->delete()
// Delete All media entries
Media::deleteAll();
```

