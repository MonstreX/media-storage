# Laravel Media Storage

This package allow you to manage different media files in your project. The package was inspired by Spatie Medialibrary package. But this package also can handle files without binding to certain model's records. So you can use Spatie Medialibary way to store files or you can use independed media collection don't belong any models at all. Also the package can manipulate some image conversions.


```php
$user1 = User::find(1);
$collection = Media::add(request()->file('myfile'))->model($user1)->collection('new-gallery')->create();
$collection = Media::add('images/1.jpg', 'local')->collection('local-file')->create();
$collection = Media::model($model)->add($files)->collection(5403)->create();
$collection = Media::add($file)->props($props)->collection('images')->create();

$path = Media::find(1)->path(); // get path by record ID
$path = Media::id(3)->path();   // get path by MEDIA ID
$url = Media::id(3)->url();     // get URL
$url = Media::id(3)->crop(250,300)->url(); 
$collection = Media::collection(5403)->get();
$collection = Media::model($user1)->collection('new-gallery')->get();
```

Do be continued...



