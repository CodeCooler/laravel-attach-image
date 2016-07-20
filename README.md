Laravel attach image
===========================
Provides functionality for attach files to model and manipulate if file is image

## Features

- Use laravel [Filesystem / Cloud Storage](https://laravel.com/docs/filesystem) for store attached data. Now support: local, ftp, Amazon S3, Rackspace, DropBox ...
- Any image manipulation supported by [Intervention Image](http://image.intervention.io/getting_started/installation)
- Possibility process attached image formats on attach, on get content, on the fly (processed format not store)
- Return image as data url
- Automatic delete attached files when delete model

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Require this package with composer using the following command:

```
composer require salopot/laravel-attach-image "dev-master"
```

or add

```
"salopot/laravel-attach-image": "dev-master"
```

to the require section of your `composer.json` file.

After updating composer, configure [image processor](http://image.intervention.io/getting_started/installation#laravel) of it not used before

Configure app/filesystems.php add item "attach" to "disks" section

Local sample:
 ```
 'attach' => [
    'driver' => 'local',
    'root'   => base_path('public'),
    'baseUrl' => asset(''),
 ]
 ```
 Amazon S3 sample:
 ```
 'attach' => [
     'driver' => 's3',
     'key'    => 'your-key',
     'secret' => 'your-secret',
     'region' => 'your-region', //'eu-central-1' for Frankfurt
     'bucket' => 'your-bucket', //'testattach'
     'baseUrl' => 'https://s3.eu-central-1.amazonaws.com/testattach/'
  ]
 ```


Usage
-----

Use string fields in DB to store attached data relative path:
```
Schema::create('books', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name');
    $table->string('sample');
    $table->string('cover');
    $table->timestamps();
});
```

Extend eloquent model:

```
use Illuminate\Database\Eloquent\Model;
use App\Helpers\FileAttach;
use App\Helpers\ImageAttach;

class Book extends Model
{
    protected $fillable = ['name', 'cover', 'sample'];

    protected $_sample;
    public function getSampleAttribute() {
        return $this->_sample ? : $this->_sample = new FileAttach($this, 'sample');
    }

    public function setSampleAttribute($value) {
        $this->getSampleAttribute()->attachFile($value);
    }

    protected $_cover;
    public function getCoverAttribute() {
        return $this->_cover ? : $this->_cover = new ImageAttach($this, 'cover', [
            'list' => [
                'on' => ImageAttach::PT_ATTACH,
                'process' => function($image, $imageAttach) {
                    $width = 100;
                    $height = 100;
                    return $image
                        ->resize($width, $height, function($constraint) {
                            $constraint->aspectRatio();
                        })
                        //->resizeCanvas($width, $height);
                        ->greyscale();
                },
            ],
            'thumb' => [
                'on' => ImageAttach::PT_ATTACH,
                'process' => function($image, $imageAttach) {
                    return $image
                        ->widen(500)
                        ->text('Sample text', (int)($image->width() / 2), (int)($image->height() / 2), function($font) {
                            $font->align('center');
                            $font->valign('center');
                            $font->color('#ff0000');
                        });
                },
            ],
        ]);
    }

    public function setCoverAttribute($value) {
        $this->getCoverAttribute()->attachFile($value);
    }

    public static function boot() {
        parent::boot();

        static::deleting(function($model) {
            /*
            $model->sample->clearData();
            $model->cover->clearData();
            */
            foreach($model->getMutatedAttributes() as $attribute) {
                if ($model->{$attribute} instanceof FileAttach) {
                    $model->{$attribute}->clearData();
                }
            }
            return true;
        });
    }
```

And now you can use model basic fill methods to attach uploaded files:
```
class BookController extends Controller
{
...
    public function update(Request $request, $id)
    {
        $model = Book::findOrFail($id);
        //clear attach data functionality
        if ($request->has('clear_attach')) {
            if ($model->{$request->clear_attach} instanceof FileAttach) {
                $model->{$request->clear_attach}->clear();
                $model->save();
                return redirect()->to($this->getRedirectUrl())->withInput($request->input());
            }
        }
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'sample' => 'mimes:pdf',
            'cover' => 'mimes:jpeg,png,bmp',
        ]);
        $model->fill($request->all())->save(); //store uploaded file to model
        return redirect()->route('book.show', ['id' => $id]);
    }

    public function destroy($id)
    {
        $model = Book::findOrFail($id);
        $model->delete();
        return redirect()->route('book.index');
    }
```

Simple view example:
```
{!! Form::open(['route' => 'book.store', 'files'=>true]) !!}
<form method="POST" action="book" enctype="multipart/form-data">
{{ csrf_field() }}

<div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
    {!! Form::label('name', 'Name', ['class' => 'control-label']) !!}
    {!! Form::text('name', null, ['class' => 'form-control']) !!}
    @if ($errors->has('name'))
        <span class="help-block"><strong>{{ $errors->first('name') }}</strong></span>
    @endif
</div>
<div class="form-group{{ $errors->has('sample') ? ' has-error' : '' }}">
    {!! Form::label('sample', 'Sample', ['class' => 'control-label']) !!}
    @if(isset($model) && $model->sample->attached())
        <div style="vertical-align: middle">
            <a href="{{$model->sample->getUrl()}}"><span class="glyphicon glyphicon-paperclip" aria-hidden="true"></span></a>
            <button name="clear_attach" value="sample" class="btn btn-danger confirm" type="submit">
                Delete
            </button>
        </div>
    @else
        {!! Form::file('sample', ['accept'=>'.pdf']) !!}
        @if ($errors->has('sample'))
            <span class="help-block"><strong>{{ $errors->first('sample') }}</strong></span>
        @endif
    @endif
</div>
<div class="form-group{{ $errors->has('cover') ? ' has-error' : '' }}">
    {!! Form::label('cover', 'Cover', ['class' => 'control-label']) !!}
    @if(isset($model) && $model->cover->attached())
        <div style="vertical-align: middle">
            <img src="{{ $model->cover->getUrl('thumb') }}"/>
            <button name="clear_attach" value="cover" class="btn btn-danger confirm" type="submit">
                Delete
            </button>
        </div>
    @else
        {!! Form::file('cover', ['accept'=>'.png,.bmp,.jpg,.jpeg']) !!}
        @if ($errors->has('cover'))
            <span class="help-block"><strong>{{ $errors->first('cover') }}</strong></span>
        @endif
    @endif
</div>

<input class="btn btn-primary" type="submit" value="Save">
</form>
```