<?php
use Yaf\Dispatcher;
use Yaf\Controller_Abstract;
use yii\db\Query;

class ContentController extends Controller_Abstract
{
    const EMPTY_ARRAY = [];
    const MIN_SCALE = 0.4;
    private static function createImage($content)
    {
    
       $meta = getimagesizefromstring($content); 
       $instance = [];
       switch ($meta["mime"]) {
           case 'image/jpg':
               /* FALL THROUGH */
           case 'image/jpeg':
               $instance["extension"] = ".jpg";
               $instance["save"] = "imagejpeg";
               break;
           case 'image/gif':
               $instance["extension"] = ".gif";
               $instance["save"] = "imagegif";
               break;
           case 'image/png':
               $instance["extension"] = ".png";
               $instance["save"] = "imagepng";
               break;
           case 'image/bmp':
               $instance["extension"] = ".bmp";
               $instance["save"] = "imagebmp";
               break;
           default:
               throw new InvalidArgumentException("Invalid file type");
       }
       $instance["load"] = "imagecreatefromstring";
       $instance["mime"] = $meta["mime"];
       $instance[0] = $meta[0];
       $instance[1] = $meta[1];
       $instance["content"] = $content;
       return $instance;
    }

    private static function computeCanvasWithLimit($srcWidth, $srcHeight, $minSize)
    {
        $srcHWScale = $srcHeight / $srcWidth;
        $srcWHScale = $srcWidth / $srcHeight;
        if ($srcHWScale < 1.0) {
            if ($srcHWScale < self::MIN_SCALE) {
                $srcHWScale = self::MIN_SCALE;
            }
            $height = $minSize;
            $width = intval(round($height * $srcWHScale));
        } else {
            if ($srcWHScale < self::MIN_SCALE) {
                $srcWHScale = self::MIN_SCALE;
            }
            $width = $minSize;
            $height = intval(round($width * $srcHWScale));
        }
        return self::computeCanvas($srcWidth, $srcHeight, $width, $height);
    }

    private static function computeCanvas($srcWidth, $srcHeight, $width, $height)
    {
        $srcScale = $srcHeight / $srcWidth;
        $scale = $height / $width;
        if ($srcScale >= $scale) { 
            $newWidth = $srcWidth;
            $newHeight = intval(round($scale * $newWidth));
            $newX = 0;
            $newY = intval(round(($srcHeight - $newHeight) / 2));
        } else {
            $newHeight = $srcHeight;
            $newWidth = intval(round($newHeight / $scale));
            $newX = intval(round(($srcWidth - $newWidth) / 2));
            $newY = 0;
        }
        return [$newWidth, $newHeight, $newX, $newY, $width, $height];
    }

    private static function canvas($imageInstance, $width, $height)
    {
        $srcWidth = $imageInstance[0];
        $srcHeight = $imageInstance[1];
        if ($height === -1) {
            return self::computeCanvasWithLimit($srcWidth, $srcHeight, $width);
        } else {
            return self::computeCanvas($srcWidth, $srcHeight, $width, $height);
        }
    }

    private static function clip($imageInstance, $canvas, $width, $height, $output, $quality = 100)
    {
        $cropped = imagecreatetruecolor($canvas[0], $canvas[1]);
        $source = $imageInstance['load']($imageInstance["content"]);
        imagecopy($cropped, $source, 0, 0, $canvas[2], $canvas[3], $canvas[0], $canvas[1]);
        if ($height === -1) {
            $width = $canvas[4];
            $height = $canvas[5];
        }
        if ($width != $canvas[0] || $height != $canvas[1]) {
          $dest = imagecreatetruecolor($width, $height);
          imagecopyresampled($dest, $cropped, 0, 0, 0, 0, $width, $height, $canvas[0], $canvas[1]);
          imagedestroy($cropped);
          $cropped = $dest;
        }
        $imageInstance["save"]($cropped, $output, $quality);
        imagedestroy($cropped);
    }

    private static function tryLoadFaces($objectId)
    {
        if (Predicates::isNull($objectId)) {
            return self::EMPTY_ARRAY;
        }
        $query = new Query;
        $result = $query->select('data')->from(Us\TableName\MOMENT_PICTURE)->where(['object_id' => $objectId])->one();
        if ($result) {
            return json_decode($result["data"]);
        } else {
            return self::EMPTY_ARRAY;
        }
    }

    private static function tryAdjustCanvas($objectId, $imageInstance, $canvas)
    {
        $faces = self::tryLoadFaces($objectId);
        if (count($faces) == 0) {
            return $canvas;
        }
        $originalWidth = $canvas[0];
        $originalHeight = $canvas[1];
        $originalMinX = $canvas[2];
        $originalMinY = $canvas[3];
        $originalMaxX = $originalMinX + $originalWidth;
        $originalMaxY = $originalMinY + $originalHeight;

        $lastMinX = $minX = $imageInstance[0];
        $lastMinY = $minY = $imageInstance[1];
        $lastMaxX = $lastMaxY = $maxX = $maxY = 0;
        $firstFace = true;
        foreach ($faces as $face) {
            $lx = $face->x;
            $ly = $face->y;
            $rx = $lx + $face->w;
            $ry = $ly + $face->h;
            if ($minX > $lx) {
                $minX = $lx;
            }
            if ($minY > $ly) {
                $minY = $ly;
            }
            if ($maxX < $rx) {
                $maxX = $rx;
            }
            if ($maxY < $ry) {
                $maxY = $ry;
            }
            if ($minX < $originalMinX && $minY < $originalMinY && $maxX > $originalMaxX && $maxY > $originalMaxY) {
                break;
            }
            if ($firstFace) {
                $firstFace = false;
            }
            $lastMinX = $minX;
            $lastMinY = $minY;
            $lastMaxX = $maxX;
            $lastMaxY = $maxY;
        }
        if ($minX >= $originalMinX && $minY >= $originalMinY && $maxX <= $originalMaxX && $maxY <= $originalMaxY) {
            return $canvas;
        }
        if ($firstFace) {
            $centroidX = ($minX + $maxX) / 2;
            $centroidY = ($minY + $maxY) / 2;
        } else {
            $centroidX = ($lastMinX + $lastMaxX) / 2;
            $centroidY = ($lastMinY + $lastMaxY) / 2;
        }
        $centroidX = intval(round($centroidX));
        $centroidY = intval(round($centroidY));
        $newX = $centroidX - intval(round($originalWidth / 2));
        if ($newX < 0) {
            $newX = 0;
        } else if ($newX + $originalWidth > $imageInstance[0]) {
            $newX = $imageInstance[0] - $originalWidth;
        }
        $newY = $centroidY - intval(round($originalHeight / 2));
        if ($newY < 0) {
            $newY = 0;
        } else if ($newY + $originalHeight > $imageInstance[1]) {
            $newY = $imageInstance[1] - $originalHeight;
        }

        $canvas[2] = $newX;
        $canvas[3] = $newY;
        return $canvas;
    }

    private static function doGenerateEventLive($prefix, $spec)
    {
        $query = new Query;
        if (!($result = $query->select('event_id')->from(Us\TableName\EVENT_LIVE)->where(['live_id' => $spec->id])->one()) &&
            !($result = $query->select('id as event_id')->from(Us\TableName\EVENT)->where(['live_id' => $spec->id])->one())) {
            throw new InvalidArgumentException("Invalid live id");
        }
        Live::generate($result["event_id"], Accessor::either($spec->resolution[0], 1280), 
            Accessor::either($spec->quality, 75), $spec->platform);
    }

    private static function doGenerateContent($prefix, $spec)
    {
        $faces = Execution::withFallback(function() use ($spec) {
            return self::tryLoadFaces($spec->id);
        }, function() {
            return self::EMPTY_ARRAY;
        });
        Execution::autoUnlink(function($unlinkLater) use ($prefix, $spec) {
            $path = $prefix . $spec->fileName . $spec->extension; 
            $origin = ContentCache::load($path);
            $tmpPath = tempnam("/tmp", "Content-");
            $unlinkLater($tmpPath);
            $tmpPath .= $spec->extension;
            $unlinkLater($tmpPath);
            $content = file_get_contents($origin);
            $image = self::createImage($content);
            header("Content-Type:" . $image["mime"]);
            if (Predicates::isNotNull($spec->resolution)) {
                $width = $spec->resolution[0];
                $height = $spec->resolution[1];
            } else {
                $width = $image[0];
                $height = $image[1];
            }
            $canvas = self::tryAdjustCanvas($spec->id, $image, self::canvas($image, $width, $height));
            $quality = Accessor::either($spec->quality, 75);
            if (Predicates::isNotNull($spec->resolution)) {
                self::clip($image, $canvas, $width, $height, $tmpPath, Predicates::isNull($spec->filter) ? $quality : 100);
                if (Predicates::isNull($spec->filter)) {
                    goto end;
                }
            } else {
              file_put_contents($tmpPath, $content);
            }
            $tmpPath2 = tempnam("/tmp", "Trash-");
            $unlinkLater($tmpPath2);
            $tmpPath2 .= $spec->extension;
            $unlinkLater($tmpPath2);
            Moca\PhotoFilter\Filter::run($spec->filter | Moca\PhotoFilter\BALANCE_TYPE_DUMMY, $tmpPath, $tmpPath2, [Moca\PhotoFilter\SAVE_JPEG_QUALITY => $quality]);
            $tmpPath = $tmpPath2;
        end:
            header("Content-Length:" . filesize($tmpPath));
            readfile($tmpPath);
        });
    }

    private static function tryParsePlatform($part)
    {
        return in_array($part, ["weibo"]) ? ["platform", $part] : NULL;
    }

    private static function tryParseResolution($part)
    {
        if (!mb_strstr($part, "x")) {
            return NULL;
        }
        $parts = explode("x", $part);
        if (count($parts) != 2) {
            throw new InvalidArgumentException("Invalid resolution " . $part);
        }
        if (Predicates::isEmpty($parts[1])) {
          return ["resolution", intval($parts[0]), -1];
        } else {
          return ["resolution", intval($parts[0]), intval($parts[1])];
        }
    }

    private static function tryParseQuality($part)
    {
        if (!mb_strstr($part, "q")) {
            return NULL;
        }
        $len = mb_strlen($part);
        if (mb_substr($part, $len - 1, 1) !== "q") {
            throw new InvalidArgumentException("Invalid quality " . $part);
        }
        return ["quality", intval(mb_substr($part, 0, $len - 1))];
    }

    private static function tryParseFilter($part)
    {
        if (!ctype_digit($part)) {
            return NULL;
        }
        return ["filter", intval($part)];
    }

    private static function parseFileName($fileName, $objectIdGenerator)
    {
        $extension = strrchr($fileName, ".");
        if (!$extension) {
            throw new InvalidArgumentException("Unknown file extension");
        }
        $fileName = substr($fileName, 0, -strlen($extension));
        $spec = explode("_", $fileName);
        array_walk($spec, function(&$part, $index) {
            if ($index == 0) {
                $part = ["fileName", $part];
            } else {
                $part = Accessor::either(self::tryParseResolution($part), self::tryParseQuality($part), self::tryParseFilter($part), self::tryParsePlatform($part));
            }
        });
        $spec[] = ["extension", $extension];
        $spec[] = ["id", $objectIdGenerator($spec[0][1])];
        $tmp = new StdClass();
        $tmp->fileName = NULL;
        $tmp->quality = NULL;
        $tmp->extension = NULL;
        $tmp->id = NULL;
        $tmp->resolution = NULL;
        $tmp->filter = NULL;
        $tmp->platform = NULL;
        return array_reduce($spec, function($carry, $item) { $array = array_slice($item, 1); Preconditions::checkNull($carry->$item[0]); $carry->$item[0] = count($array) > 1 ? $array : $array[0]; return $carry; }, $tmp);
    }

    private static function validateSpec($fileName, $spec, $isLive = false)
    {
        try {
            if (!$isLive) {
                if ((Predicates::isNull($spec->resolution) && Predicates::isNull($spec->filter)) || Predicates::isNotNull($spec->platform)) {
                    throw new Exception("Invalid request");
                }
            } else {
                if (Predicates::isNotNull($spec->filter)) {
                    throw new Exception("Invalid request");
                }
                if (Predicates::isNotNull($spec->resolution) && ($spec->resolution[1] != -1 || !in_array($spec->resolution[0], [640]))) {
                    throw new Exception("Invalid request");
                }
            }
            if (Predicates::isNotNull($spec->resolution)) {
                $limit = json_decode(Us\Config\CONTENT_GENERATOR_RESOLUTION_LIMIT);
                if ($spec->resolution[1] == -1) {
                    if ($spec->resolution[0] > $limit[0]) {
                        throw new Exception("Invalid resolution");
                    }
                } else if (min($spec->resolution) > $limit[1] || max($spec->resolution) > $limit[0]) {
                    throw new Exception("Invalid resolution");
                }
            }
            if (Predicates::isNotNull($spec->filter) && ($spec->filter < 0 || $spec->filter > 3)) {
                throw new Exception("Invalid filter");
            }
            if (Predicates::isNotNull($spec->quality) && !in_array($spec->quality, json_decode(Us\Config\CONTENT_GENERATOR_QUALITY))) {
                throw new Exception("Invalid quality");
            }
    
            $params = [$spec->fileName];
            if (Predicates::isNotNull($spec->resolution)) {
                $params[] = $spec->resolution[0] . "x" . ($spec->resolution[1]== -1 ? "" : $spec->resolution[1]);
            }
            if (Predicates::isNotNull($spec->filter)) {
                $params[] = $spec->filter;
            }
            if (Predicates::isNotNull($spec->quality)) {
                $params[] = $spec->quality . "q";
            }
            if (Predicates::isNotNull($spec->platform)) {
                $params[] = $spec->platform;
            }
            if (implode("_", $params) . $spec->extension !== $fileName) {
                throw new Exception("Invalid request");
            }
            return $spec;
        } catch (Exception $e) {
            error_log("path " . $fileName . " is not normalized according to spec of " . var_export($spec, true));
            throw $e;
        }
    }

    private static function isFromTencent()
    {
        return Protocol::userAgent() === Us\Config\QCloud\COS_USER_AGENT;
    }

    private static function checkLimit($fileName)
    {
        if (self::isFromTencent()) {
            return;
        }
        $key = Us\Config\CONTENT_GENERATOR_RATE_LIMIT_KEY_PREFIX . $fileName;
        $generation = Yii::$app->redis->incr($key);
        /* workaround crs expire bug */
        Yii::$app->redis->expire($key, Us\Config\CONTENT_GENERATOR_RATE_LIMIT_EXPIRE);
        if ($generation > Us\Config\CONTENT_GENERATOR_RATE_LIMIT) {
            Protocol::tooManyRequest();
        }
    }

    public function doGenerate($prefix, $objectIdGenerator) 
    {
        try {
            ini_set('memory_limit', Us\Config\CONTENT_GENERATOR_MEMORY_LIMIT);
            $fileName = $this->getRequest()->getParam("fileName");
            $spec = self::parseFileName(Preconditions::checkNotEmpty($fileName), $objectIdGenerator);
            $isLive = $prefix === "/event/live/";
            $spec = self::validateSpec($fileName, $spec, $isLive);
            //self::checkLimit($prefix . $fileName);
            if ($isLive) {
                self::doGenerateEventLive($prefix, $spec);
            } else {
                self::doGenerateContent($prefix, $spec);
            }
        } catch (Exception $e) {
            error_log($e->getMessage() . "\n" . $e->getTraceAsString());
            throw new HttpException(404, $e, "Could not generate content");
        }
    }

    public function generateAvatarAction()
    {
        $this->doGenerate("/profile/avatar/", function($fileName) { return NULL; });
    }

    public function generateMomentAction()
    {
        $this->doGenerate("/event/moment/", function($fileName) { return $fileName; });
    }

    public function generateLiveAction()
    {
        $this->doGenerate("/event/live/", function($fileName) { return $fileName; });
    }

    public function generateCoverPageAction()
    {
        $this->doGenerate("/event/coverpage/", function($fileName) { return NULL; });
    }
    public function generateGroupCoverPageAction()
    {
        $this->doGenerate("/group/coverpage/", function($fileName) { return NULL; });
    }
    
}
?>
