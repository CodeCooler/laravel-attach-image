<?php
namespace Salopot\Attach;

use Illuminate\Database\Eloquent\Model;
use Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileAttach
{


    private $baseDir;
    private $baseUrl;
    private $disk;
    protected $owner;
    protected $attributeName;
    protected $filesystemDisk;
    protected $dirLevel;

    function __construct(Model $owner, $attributeName)
    {
        $this->owner = $owner;
        $this->attributeName = $attributeName;
        $this->filesystemDisk = 'attach';
        $this->setBaseDir('data/'.strtolower(class_basename($owner)).'/'.strtolower($this->attributeName));
        $this->dirLevel = 1;
    }

    public function setFilesystemDisk($value) {
        $this->filesystemDisk = $value;
        $this->disk = null;
        $this->baseUrl = null;
    }

    public function getFilesystemDisk() {
        return $this->filesystemDisk;
    }

    public function getBaseUrl() {
        if ($this->baseUrl === null) {
            $this->setBaseUrl(config("filesystems.disks.{$this->filesystemDisk}.baseUrl"));
        }
        return $this->baseUrl;
    }

    public function setBaseUrl($value) {
        $this->baseUrl = rtrim($value, '/') . '/';
    }
    
    public function getBaseDir() {
        return $this->baseDir;
    }
    
    public function setBaseDir($value) {
        $this->baseDir = rtrim($value, '/') . '/';
    }

    public function setDirLevel($value) {
        $level = intval($value);
        if ($level < 1 && $level > 6 ) throw new \OutOfRangeException('DirLevel must be in range from 1 to 6');
        $this->dirLevel = $level;
    }

    /**
     * @return \League\Flysystem\FilesystemInterface
     */
    protected function getDisk() {
        return $this->disk ? : $this->disk = Storage::disk($this->filesystemDisk);
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

    public function hasData() {
        return $this->attached() && $this->getDisk()->has($this->genPath());
    }

    protected function genAttribute($extension) {
        $this->setAttribute(uniqid() . '.' . strtolower($extension));
    }

    protected function genRelativeDir(){
        $attribute = $this->getAttribute();
        $dir = $this->getBaseDir();
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
        return $this->getBaseUrl().$this->genRelativePath();
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