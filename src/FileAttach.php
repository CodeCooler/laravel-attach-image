<?php
namespace Salopot\Attach;

use Illuminate\Database\Eloquent\Model;
use Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileAttach
{

    protected $owner;
    protected $attributeName;
    public $dirLevel = 1;
    public $baseDir;
    public $baseUrl;
    public $filesystemDisk;

    function __construct(Model $owner, $attributeName)
    {
        $this->owner = $owner;
        $this->attributeName = $attributeName;
        $this->baseDir = 'data/'.strtolower(class_basename($owner)).'/'.strtolower($this->attributeName).'/';

        $this->filesystemDisk = 'attach';
        $this->baseUrl = config("filesystems.disks.{$this->filesystemDisk}.baseUrl");
    }

    public function getAttribute() {
        $attributes = $this->owner->getAttributes();
        return isset($attributes[$this->attributeName]) ? $attributes[$this->attributeName] : null;
    }

    protected function setAttribute($value) {
        $attributes = $this->owner->getAttributes();
        $attributes[$this->attributeName] = $value;
        $this->owner->setRawAttributes($attributes, false);
    }

    public function getOwner() {
        return $this->owner;
    }

    public function attached() {
        $value = $this->getAttribute();
        return !empty($value);
    }

    /**
     * @return \League\Flysystem\FilesystemInterface
     */
    protected function getDisk() {
        return Storage::disk($this->filesystemDisk);
    }

    public function hasData() {
        return $this->attached() && $this->getDisk()->has($this->genPath());
    }

    protected function genAttribute($extension) {
        $this->setAttribute(uniqid() . '.' . strtolower($extension));
    }

    protected function genRelativeDir(){
        $attribute = $this->getAttribute();
        $dir = $this->baseDir;
        for ($i = 0; $i < $this->dirLevel; $i++) {
            $dir .= substr($attribute, $i * 2, 2) . '/';
        }
        return $dir;
    }

    protected function genRelativePath() {
        $attribute = $this->getAttribute();
        $pathParts = pathinfo($attribute);
        return $this->genRelativeDir() . $pathParts['filename'] . '/' . $this->getAttribute();
    }

    protected function genPath() {
        return $this->genRelativePath();
    }

    protected function genUrl() {
        return $this->baseUrl.$this->genRelativePath();
    }

    public function getContent() {
        if ($this->attached()) {
            return $this->getDisk()->get($this->genPath());
        }
    }
    
    public function getUrl() {
        if ($this->attached()) {
            return $this->genUrl();
        }
    }

    public function getPath() {
        if ($this->attached()) {
            return $this->genPath();
        }
    }

    public function attachFile(\SplFileInfo $file) {
        $content = $file->openFile('r')->fread($file->getSize());
        $extension = $file instanceof UploadedFile ? $file->getClientOriginalExtension() : $file->getExtension();
        $this->attachContent($content, $extension);
    }

    public function attachContent($content, $extension) {
        $this->clear();
        $this->genAttribute($extension);
        $this->getDisk()->put($this->genPath(), $content, 'public');
    }

    public function clearData()
    {
        if ($this->hasData()) {
            $this->getDisk()->deleteDir(dirname($this->genPath()));
        }
    }

    public function clear() {
        $this->clearData();
        $this->setAttribute('');
    }

}