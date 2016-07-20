<?php
namespace Salopot\Attach;

use Illuminate\Database\Eloquent\Model;
use Image;

class ImageAttach extends FileAttach
{
    //Process types
    //call getUrl/getPath/getContent/render
    const PT_GET = 1;
    //after attaching img file
    const PT_ATTACH = 2;
    //get content on the fly
    const PT_DEMAND = 3;

    const UF_LINK = 'link';
    const UF_BASE64 = 'base64';

    protected $types = []; //['on'=>PT_.., 'process' => function(Image $image, FileAttach) {}]

    public function __construct(Model $owner, $attributeName, $types)
    {
        parent::__construct($owner, $attributeName);
        foreach ($types as $type => &$props) {
            if (!isset($props['on'])) {
                $props['on'] = self::PT_GET;
            }
        }
        $this->types = $types;
    }

    public function getTypes() {
        return array_keys($this->types);
    }

    protected function genRelativePath($type=null) {
        if ($type === null) return parent::genRelativePath();
        $attribute = $this->getAttribute();
        $pathParts = pathinfo($attribute);
        return $this->genRelativeDir() . $pathParts['filename'].'/'.$pathParts['filename'].'_'.strtolower($type).'.'.$pathParts['extension'];
    }

    protected function genPath($type=null) {
        return $this->genRelativePath($type);
    }

    protected function genUrl($type=null) {
        return $this->baseUrl.$this->genRelativePath($type);
    }

    public function hasData($type=null) {
        return $this->attached() && $this->getDisk()->has($this->genPath($type));
    }

    protected function checkImage($type) {
        if ($type !== null) {
            if (!isset($this->types[$type])) throw new \Exception('Not support type: "'.$type.'"');
            if ($this->types[$type]['on']==self::PT_DEMAND || ($this->types[$type]['on']!=self::PT_ATTACH && !$this->hasData($type))) {
                return $this->processImage($this->getContent(), $type);
            }
        }
    }

    protected function processImage($content, $type) {
        ini_set('memory_limit', '256M');
        $img = Image::make($content);
        $img = call_user_func($this->types[$type]['process'], $img, $this);
        $content = $img->stream()->__toString();
        if ($this->types[$type]['on'] != self::PT_DEMAND) {
            $this->getDisk()->put($this->genPath($type), $img->stream()->__toString(), 'public');
        }
        return $content;
    }

    public function getContent($type=null) {
        if ($this->attached()) {
            if(($content = $this->checkImage($type)) !== null) return $content;
            return $this->getDisk()->get($this->genPath($type));
        }
    }

    public function getPath($type=null) {
        if ($this->attached()) {
            $this->checkImage($type);
            return $this->genPath();
        }
    }

    public function getUrl($type=null, $format=self::UF_LINK) {
        if ($this->attached()) {
            if ($format == self::UF_LINK) {
                $this->checkImage($type);
                return $this->genUrl($type);
            } elseif($format == self::UF_BASE64) {
                $content = $this->getContent($type);
                $mimeType = image_type_to_mime_type(getimagesizefromstring($content)[2]);
                return 'data:' . $mimeType. ';base64,' . base64_encode($content);
            }
        }
    }

    public function attachContent($content, $extension=null) {
        if(($imageInfo = @getimagesizefromstring($content))!==false) {
            $extension = $extension!==null ? $extension : image_type_to_extension($imageInfo[2], false);
            parent::attachContent($content, $extension);
            foreach ($this->types as $type => $props) {
                if ($props['on'] == self::PT_ATTACH) {
                    $this->processImage($content, $type);
                }
            }
        }
    }


}