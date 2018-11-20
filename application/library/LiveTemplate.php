<?php

class Element {
    public $id;
    protected function __construct()
    {
    }

    protected function loadElement($data)
    {
        $data->copyOptional(["id"], $this);
    }

    public function type()
    {
        return "element";
    }

    public static function findIn($array, $id)
    {
        foreach ($array as $element) {
            if ($element->id === $id) {
                return $element;
            }
        }
        return NULL;
    }
}

class Box extends Element {
    public $x;
    public $y;
    public $height;
    public $width;

    public function __construct($x = NULL, $y = NULL, $width = NULL, $height = NULL)
    {
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
    }

    protected function loadBox($data)
    {
        $this->loadElement($data);
        $data->copyOptional(["x", "y", "height", "width"], $this);
    }

    public function type()
    {
        return "box";
    }

    public function updateX($x)
    {
        $copy = clone $this;
        $copy->x = $x;
        return $copy;
    }

    public function updateY($y)
    {
        $copy = clone $this;
        $copy->y = $y;
        return $copy;
    }

    public function updateWidth($width)
    {
        $copy = clone $this;
        $copy->width = $width;
        return $copy;
    }

    public function updateHeight($height)
    {
        $copy = clone $this;
        $copy->height = $height;
        return $copy;
    }

    public function create($x, $y, $width, $height)
    {
        $copy = clone $this;
        $copy->x = $x;
        $copy->y = $y;
        $copy->width = $width;
        $copy->height = $height;
        return $copy;
    }
}

class TextBox extends Box {
    public $font;
    public $fontSize;
    public $color;
    public $fontWeight;

    public function __construct($data)
    {
        $this->loadBox($data);
        $data->copyOptional(["font", "color"], $this);
        $this->fontSize = $data->optional("font-size");
        $this->fontWeight = $data->optional("font-weight");
    }

    public function type()
    {
        return "textbox";
    }
}

class Rect extends Box {
    public $round;
    public $animation;
    public $scale;
    public $rotation;
    public $filter;

    public function __construct($data)
    {
        $this->loadBox($data);
        $data->copyOptional(["round", "animation", "scale", "rotation", "filter"], $this);
    }

    public function type()
    {
        return "rect";
    }
}

class Band extends Box {
    public $textboxes;
    public $rects;

    public function __construct($data)
    {
        $this->loadBox($data);
        $this->textboxes = array_map(function($textbox) { return new TextBox(Accessor::wrap($textbox)); }, $data->optional("textbox", []));
        $this->rects = array_map(function($rect) { return new Rect(Accessor::wrap($rect)); }, $data->optional("rect", []));
    }
    public function type()
    {
        return "band";
    }

    public function find($id)
    {
        if ($id === $this->id) {
            return $this;
        }
        $result = Element::findIn($this->textboxes, $id);
        if (Predicates::isNull($result)) {
            $result = Element::findIn($this->rects, $id);
        }
        return $result;
    }

    public static function findIn($array, $id)
    {
        foreach ($array as $band) {
            $result = $band->find($id);
            if (Predicates::isNotNull($result)) {
                return $result;
            }
        }
        return NULL;
    }
}

class LiveTemplate {
    public $version;
    public $width;
    public $bands;
    public $garage;

    public function __construct($file)
    {
        $template = Accessor::wrap(json_decode(file_get_contents($file)));
        $this->width = $template->requiredInt("width");
        $this->version = $template->optionalInt("version", 1);
        $bandLoader = function($band) { return new Band(Accessor::wrap($band)); };
        $this->bands = array_map($bandLoader, $template->required("band"));
        $garage = $template->optional("garage");
        if (Predicates::isNotNull($garage)) {
            $this->garage = new stdClass();
            $this->garage->bands = array_map($bandLoader, Accessor::wrap($garage)->optional("band", []));
        }
    }

    public function find($id)
    {
        $result = Band::findIn($this->bands, $id);
        if (Predicates::isNull($result) && Predicates::isNotNull($this->garage)) {
            $result = Band::findIn($this->garage->bands, $id);
        }
        return $result;
    }
}

?>
