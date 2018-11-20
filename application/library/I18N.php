<?php

use Yaf\Application;
use Yaf\Config\Ini;

class I18NResourceBundle
{
    private $items = array();
    private function __construct($items)
    {
        foreach ($items as $key => $value) {
            $this->items[$key] = $value;
        }
    }

    public function lookup($key, $default = NULL)
    {
        if (isset($this->items[$key])) {
            return $this->items[$key];
        } else {
            return Predicates::isNull($default) ? $key : $default;
        }
    }

    public static function load($resource)
    {
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' .
            DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . $resource;
        $config = new Ini($path);
        return new I18NResourceBundle($config);
    }
};

class I18N
{
    private $default;

    private static function getLocale()
    {
        $locale = @$_SERVER['HTTP_ACCEPT_LANGUAGE'];
        if (!$locale || $locale == "*") {
            $locale = 'zh';
        } else {
            $locale = explode(',', $locale)[0];
        }
        $separator = strstr($locale, '-', true);
        if ($separator) {
            $locale = $separator;
        }
        return $locale;
    }

    private function load($resource, $locale)
    {
        $resource = I18NResourceBundle::load($resource . '.' . $locale);
        $this->$locale = $resource;
        if (Predicates::isNull($this->default) && $resource) {
            $this->default = $resource;
        }
    }

    public function __construct($resource)
    {
        $this->load($resource, self::getLocale());
    }

    public function lookupLocale($locale, $key, $default = NULL)
    {
        if (isset($this->$locale)) {
            $bundle = $this->$locale;
        } else {
            $bundle = $this->default;
        }
        return $bundle->lookup($key, $default);
    }

    public function lookup($key, $default = NULL, $locale = NULL)
    {
      if (!$locale) {
          $locale = self::getLocale();
      }

      return $this->lookupLocale($locale, $key, $default);
    }

    public function __call($name, $arguments)
    {
        $length = strlen($name);
        if ($length == 0) {
            $name = 'UNKNOWN';
        } else if ($length == 1) {
            $name = strtoupper($name);
        } else {
            $start = 0;
            $result = '';
            for ($idx = 1; $idx < $length ; ++$idx) {
              $c = $name[$idx];
              if (ctype_upper($c) || ctype_digit($c)) {
                $result = $result . strtoupper(substr($name, $start, $idx - $start)) . '_';
                $start = $idx;
              }
            }
            $result = $result . strtoupper(substr($name, $start));
            $name = $result;
        }

        return $this->lookup($name);
    }
};

?>
