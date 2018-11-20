<?php

use FontLib\Font;
use FontLib\TrueType\Collection;

class DrawFuture {
    public $path;
    private $action;
    private $argument;

    public function __construct($path, $action, $argument)
    {
        $this->path = $path;
        $this->action = $action;
        $this->argument = $argument;
    }

    public function get($path)
    {
        $action = $this->action;
        return $action($path, $this->argument);
    }
}

class RichText {
    private static $hiragino = '/usr/local/nginx/us/res/fonts/hiragino.otf';
    private static $chancery = '/usr/local/nginx/us/res/fonts/chancery.ttf';

    public $content;
    public $fontSize;
    public $font;
    public $color;
    public $envelope;
    public $textbox;
    public $scale;

    public static function font($font)
    {
        switch ($font) {
        case "chancery":
            return '/usr/local/nginx/us/res/fonts/chancery.ttf';
        case "hiragino":
        default:
            return '/usr/local/nginx/us/res/fonts/hiragino.otf';
        }
    }

    public function __construct($content, $textbox, $scale, $envelope = NULL)
    {
        $this->content = $content;
        $this->textbox = $textbox;
        $this->scale = $scale;
        $this->color = Accessor::either($textbox->color, "#000000");
        $this->font = self::font(Accessor::either($textbox->font, "hiragino"));
        $this->fontSize = Accessor::either($textbox->fontSize, 14);
        $this->fontWeight = Accessor::either($textbox->fontWeight, "normal");
        if (Predicates::isNull($envelope)) {
            $envelope = self::envelope($content, $textbox, $scale);
        }
        $this->envelope = $envelope;
    }

    public function color($color)
    {
        $this->color = $color;
        return $this;
    }

    private static function codePoint($u) 
    {
        $k = mb_convert_encoding($u, 'UCS-2LE', 'UTF-8');
        $k1 = ord(substr($k, 0, 1));
        $k2 = ord(substr($k, 1, 1));
        return $k2 * 256 + $k1;
    }

    public static function checkFont($text, $font)
    {
        $font = Font::load(self::font($font));
        if ($font instanceof Collection) {
            $font = $font->getFont(0);
        }
        $subtable = null;

        foreach ($font->getData("cmap", "subtables") as $_subtable) {
            if ($_subtable["platformID"] == 3 && $_subtable["platformSpecificID"] == 1) {
                $subtable = $_subtable;
                break;
            }
        }

        $glyphArray = $subtable["glyphIndexArray"];
        $result = "";
        for ($index = 0, $size = mb_strlen($text); $index < $size; ++$index) {
            $char = mb_substr($text, $index, 1);
            if (isset($glyphArray[self::codePoint($char)])) {
                $result .= $char;
            }
        }
        return $result;
    }

    public static function envelope($text, $textbox, $scale = 1)
    {
        $box = imagettfbbox($textbox->fontSize, 0, self::font($textbox->font), $text);
        return new Box($box[6] / $scale, $box[7] / $scale, ($box[2] - $box[0]) / $scale, ($box[3] - $box[5]) / $scale);
    }
}

class RichTextArea
{
    public $lines = [];
    public $envelope;
    public $textbox;
    public $scale;
    public $lineSpacing;

    public function __construct($text, $textbox, $scale, $lineLimit = 1, $lineSpacing = 4)
    {
        $this->textbox = $textbox;
        $this->scale = $scale;
        $this->lineSpacing = $lineSpacing;
        $widthLimit = $textbox->width;
        $text = RichText::checkFont($text, $textbox->font);
retry:
        $envelope = RichText::envelope($text, $textbox, $scale);
        if ($envelope->width > $widthLimit) {
            /* break text into lines */
            $lines = $envelope->width / $widthLimit;
            if ($lines > $lineLimit) {
                /* chop some text to reduce length */
                $ratio = ($lineLimit * $widthLimit) / $envelope->width;
                $text = mb_substr($text, 0, mb_strlen($text) * $ratio - 4) . "...";
                goto retry;
            }
        }
        $textLength = mb_strlen($text);
        $lastLength = $length = 1;
        $start = 0;
        $lastEnvelope = null;
        $height = -$lineSpacing;
        $width = 0;
        while ($start + $length < $textLength) {
            $needle = mb_substr($text, $start, $length);
            $envelope = RichText::envelope($needle, $textbox, $scale);
            if ($envelope->width > $widthLimit) {
                if (ctype_alnum($char = mb_substr($text, ($current = $start + $length - 1), 1))) {
                    $type = ctype_alpha($char) ? 0 : 1;
                    // slow path
                    for ($pos = $current - 1; $pos > $start; --$pos) {
                        $ptype = ctype_alpha(mb_substr($text, $pos, 1)) ? 0 : 1;
                        if ($ptype != $type) {
                            ++$pos;
                            break;
                        }
                    }
                    /* check if we actually can find boundary of a word or 
                     * number before start of current line */
                    if ($pos > $start) {
                        $lastLength = $pos - $start;
                        $lastEnvelope = RichText::envelope(mb_substr($text, $start, $lastLength), $textbox, $scale);
                    }
                }
                $this->lines[] = new RichText(mb_substr($text, $start, $lastLength), $textbox, $scale, $lastEnvelope);
                $height += $lastEnvelope->height + $lineSpacing;
                if ($lastEnvelope->width > $width) {
                    $width = $lastEnvelope->width;
                }
                $start += $lastLength;
                $lastLength = $length = 1;
            } else {
                $lastLength = $length++;
                $lastEnvelope = $envelope;
            }
        }
        $needle = mb_substr($text, $start, $length);
        $this->lines[] = new RichText($needle, $textbox, $scale);
        $height += $envelope->height + $lineSpacing;
        if ($envelope->width > $width) {
            $width = $envelope->width;
        }
        $this->envelope = new Box(0, 0, $width, $height);
    }
}

class Live {
    private static $alignmentCenter = 0;
    private static $alignmentLeft = 1;
    private static $alignmentRight = 2;
    private static $alignmentMiddle = 0;
    private static $alignmentTop = 4;
    private static $alignmentBottom = 8;

    private static $colors = [];

    private static function checkAndCreateColor($canvas, $code)
    {
        if (Predicates::isNull($code)) {
            $code = "#000000";
        }
        if (strlen($code) != 7 || $code[0] != '#') {
            throw new Exception("Invalid color code " . $code);
        }
        if (!array_key_exists($code, self::$colors)) {
            $color = imagecolorallocate($canvas, hexdec(substr($code, 1, 2)), hexdec(substr($code, 3, 2)), hexdec(substr($code, 5, 2)));
            self::$colors[$code] = $color;
        }
        return self::$colors[$code];
    }

    private static function decodeEmoji($text)
    {
        $result = NULL;
        $startIndex = $endIndex = $startPosition = 0;
        $emoji = null;
        $lastEmojiEnd = ~PHP_INT_MAX;
        $textLength = mb_strlen($text);
        while (($startIndex = mb_strpos($text, ":", $startPosition)) > -1 && ($endIndex = mb_strpos($text, ":", $startIndex + 2)) > -1) {
            $premble = mb_substr($text, $startPosition, $startIndex - $startPosition);
            if (Predicates::isNull($result)) {
                $result = $premble;
            } else {
                $result .= $premble;
            }
            $emojiStart = $startIndex + 1;
            $emojiLength = $endIndex - $startIndex - 1;
            $emoji = mb_substr($text, $emojiStart, $emojiLength);
            if (!mb_strstr($emoji, " ")) {
                $emoji = implode(" ", explode("_", $emoji));
                if (($lastEmojiEnd + 2 == $emojiStart) || ($emojiStart > 1 && ctype_alnum(mb_substr($text, $emojiStart - 2, 1)))) {
                    /* prepend space when last character before beginning of 
                     * emoji is either end of an emoji or alpha numerical 
                     * character */
                    $emoji = " " . $emoji;
                }
                $lastEmojiEnd = $endIndex;
                ++$endIndex;
                if ($endIndex  < $textLength && ctype_alnum(mb_substr($text, $endIndex, 1))) {
                    /* append space when next character after ending of emoji 
                     * is alpha numerical */
                    $emoji .= " ";
                }
            }
            $result .= $emoji;
            $startPosition = $startIndex + 2 + $emojiLength; 
        }

        if (Predicates::isNotNull($result)) {
            $result .= mb_substr($text, $startPosition);
            $text = $result;
        }

        return $text;
    }

    private static function doDrawTo($offset, $box, $scale, $callable)
    {
        $x = intval(round($box->x * $scale));
        $y = intval(round($box->y * $scale)) + $offset;
        $width = intval(round($box->width * $scale));
        $height = intval(round($box->height * $scale));
        return $callable($x, $y, $width, $height);
    }

    private static function createQRCodeLoader($data)
    {
        return function ($width, $height, $filter, $continuation) use ($data) {
            $continuation(NULL, function($tmp) use ($data) { 
                $qrcode = QRCode::image($data, false, QR_ECLEVEL_H); 
                $width = imagesx($qrcode);
                $height = imagesy($qrcode);
                $left = 0;
                $top = 0;
                for ($x = 0; $x < $width; ++$x) {
                    for ($y = 0; $y < $height; ++$y) {
                        if (imagecolorat($qrcode, $x, $y) != 0) {
                            if ($left == 0) {
                                $left = $x;
                            }
                            if ($top == 0) {
                                $top = $y;
                            }
                            if ($left != 0 && $top != 0) {
                                goto crop;
                            }
                        }
                    }
                }
                crop:
                $newWidth = $width - (2 * $left);
                $newHeight = $height - (2 * $top);
                $cropped = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($cropped, $qrcode, 0, 0, $left, $top, $newWidth, $newHeight, $newWidth, $newHeight);
                imagedestroy($qrcode);
                return $cropped;
            });
            return NULL;
        };
    }

    private static function createMembersImageLoader($numMembers)
    {
        return function ($width, $height, $filter, $continuation) use ($numMembers) {
            $continuation(NULL, function($tmp) use ($numMembers) { 
                $image = imagecreatefrompng(APP_PATH . "/res/members.png");
                $imageWidth = imagesx($image);
                $imageHeight = imagesy($image);
                $fontSize = 13;
                $font = RichText::font("hiragino");
                $box = imagettfbbox($fontSize, 0, $font, $numMembers);
                $envelope = new Box($box[6], $box[7], ($box[2] - $box[0]), ($box[3] - $box[5]));
                $x = ($imageWidth - $envelope->width) / 2;
                $y = 60;
                imagettftext($image, $fontSize, 0, $x - 1, $y, imagecolorallocate($image, 255, 255, 255), $font, $numMembers); 
                imagettftext($image, $fontSize, 0, $x, $y - 1, imagecolorallocate($image, 255, 255, 255), $font, $numMembers); 
                return $image;
            });
            return NULL;
        };
    }

    private static function createLocalImageLoader($path, $loader)
    {
        return function ($width, $height, $filter, $continuation) use ($path, $loader) {
            $continuation($path, $loader);
            return NULL;
        };
    }

    private static function createRemoteImageLoader($path, $id, $loader = "imagecreatefromjpeg") 
    {
        return function ($width, $height, $filter, $continuation) use ($path, $id, $loader) {
            $fileName = $id . '_' . $width . 'x' . $height;
            if (Predicates::isNotNull($filter)) {
                $fileName .= '_' . $filter;
            }
            $fileName .= ".jpg";
            return new DrawFuture($path . $fileName, $continuation, $loader);
        };
    }

    private static function drawToRect($canvas, $offset, $rect, $scale, $loader)
    {
        return self::doDrawTo($offset, $rect, $scale, function($x, $y, $width, $height) use ($canvas, $rect, $loader) {
            return $loader($width, $height, $rect->filter, function($path, $imageLoader) use ($width, $height, $x, $y, $canvas) {
                try {
                  $image = $imageLoader($path);
                  try {
                      $imageWidth = imagesx($image);
                      $imageHeight = imagesy($image);
                      if ($imageWidth != $width || $imageHeight != $height) {
                          imagecopyresampled($canvas, $image, $x, $y, 0, 0, $width, $height, $imageWidth, $imageHeight);
                      } else {
                          imagecopy($canvas, $image, $x, $y, 0, 0, $width, $height);
                      }
                  } finally {
                      imagedestroy($image);
                  }
                } catch (Exception $ex) {
                    error_log("Could not load image from '" . $path . "'\n" . var_export($ex, true));
                    throw $ex;
                }
            });
        });
    }

    private static function doDrawTextAt($canvas, $x, $y, $font, $size, $weight, $color, $content)
    {
        if ($weight === "bold") {
          imagettftext($canvas, $size, 0, $x - 1, $y, $color, $font, $content); 
          imagettftext($canvas, $size, 0, $x, $y - 1, $color, $font, $content); 
        } else {
          imagettftext($canvas, $size, 0, $x, $y, $color, $font, $content); 
        }
    }

    private static function doDrawText($canvas, $offset, $textbox, $scale, $alignment, $envelope, $specs, $draw)
    {
        return self::doDrawTo($offset, $textbox, $scale, function($x, $y, $width, $height) use ($canvas, $alignment, $envelope, $specs, $draw, $scale, $textbox) {
            switch ($alignment & 0x3) {
            case self::$alignmentCenter:
                $x += ($width - ($envelope->width * $scale)) / 2;
                break;
            case self::$alignmentRight:
                $x += ($width - ($envelope->width * $scale));
                break;
            default:
            }
            switch ($alignment & 0xC) {
            case self::$alignmentMiddle:
                $y += ($height - ($envelope->height * $scale)) / 2;
                break;
            case self::$alignmentRight:
                $y += ($height - ($envelope->height * $scale));
                break;
            }
            return $draw($canvas, $x, $y, $width, $height, $envelope, $specs);
        });
    }

    private static function drawToTextArea($canvas, $offset, $alignment, $area)
    {
        $textbox = $area->textbox;
        $scale = $area->scale;
        $lineSpacing = $area->lineSpacing;
        return self::doDrawText($canvas, $offset, $textbox, $scale, ($alignment & 0xC) | self::$alignmentLeft, $area->envelope, $area->lines, function($canvas, $x, $y, $width, $height, $envelope, $specs) use($alignment, $scale, $textbox, $lineSpacing) {
            array_reduce($specs, function($carry, $line) use ($canvas, $alignment, $width, $height, $x, $y, $scale, $textbox, $lineSpacing) {
                switch ($alignment & 0x3) {
                case self::$alignmentCenter:
                    $x += ($width - ($line->envelope->width * $scale)) / 2;
                    break;
                case self::$alignmentRight:
                    $x += ($width - ($line->envelope->width * $scale));
                    break;
                default:
                }
                self::doDrawTextAt($canvas, $x, $carry, $line->font, $line->fontSize, $line->fontWeight, self::checkAndCreateColor($canvas, $line->color), $line->content);
                return $carry + ($line->envelope->height + $lineSpacing) * $scale;
            }, $y + $specs[0]->envelope->height * $scale);
            return $envelope;
        });
    }

    private static function drawToTextBox($canvas, $offset, $alignment, ...$specs)
    {
        $textbox = $specs[0]->textbox;
        $scale = $specs[0]->scale;
        $envelope = array_reduce($specs, function($carry, $spec) {
            $envelope = $spec->envelope;
            $carry->width += $envelope->width;
            if ($envelope->height > $carry->height) {
                $carry->height = $envelope->height;
            }
            return $carry;
        }, new Box(0, 0, 0, 0));
        return self::doDrawText($canvas, $offset, $textbox, $scale, $alignment, $envelope, $specs, function($canvas, $x, $y, $width, $height, $envelope, $specs) use ($scale, $textbox) {
            array_reduce($specs, function($offsets, $spec) use ($canvas, $scale, $textbox) {
                $lineEnvelope = $spec->envelope;
                $color = self::checkAndCreateColor($canvas, $spec->color);
                self::doDrawTextAt($canvas, $offsets[0], $offsets[1], $spec->font, $spec->fontSize, $spec->fontWeight, self::checkAndCreateColor($canvas, $spec->color), $spec->content);
                $offsets[0] += $lineEnvelope->width * $scale;
                return $offsets;
            }, [$x, $y + ($envelope->height * $scale)]);
            return $envelope;
        });
    }

    public static function generate($eventId, $width = 640, $quality = 95, $platform = NULL)
    {
        $template = new LiveTemplate(APP_PATH . "/conf/live.template");
        $scale = $width / $template->width;
        $event = Event::GetEventInfoByEvent($eventId);
        $eventTime = date("m.d Y", $event["start_time"] / 1000);
        $eventTitle = self::decodeEmoji($event["name"]);
        $eventCoverPage = $event["cover_page"];
        $eventCoverPagePrefixLength = strlen("event/coverpage/");
        $eventCoverPage = substr($eventCoverPage, $eventCoverPagePrefixLength, strlen($eventCoverPage) - ($eventCoverPagePrefixLength + 4));
        $eventInvitationCode = $event["invitation_code"];
        $eventOwnerId = $event["uid"];
        $data = array_map(function($picture) {
                    $objectId = $picture["object_id"];
                    return ["id" => substr($objectId, 0, strlen($objectId) - 4), "content" => self::decodeEmoji($picture["content"]), 
                            "author" => self::decodeEmoji($picture["nickname"]), "avatar" => $picture["avatar"], 
                            "uid" => $picture["uid"]];
                }, Event::GetEventBigPicture($eventId, 15));
        $numBands = count($template->bands);
        $avatarSet = [];
        $avatars = array_map(function($member) { return $member["avatar"]; }, Event::getEventMembersAvatar($eventId));
        $titleLength = mb_strlen($eventTitle);
        $titleBox = $template->find("theme");
        $titleEnvelope = RichText::envelope($eventTitle, $titleBox, $scale);
        $isDualTitle = $titleEnvelope->width > $titleBox->width;
        $titleBand = $template->find($isDualTitle ? "title-dual" : "title-single");
        $height = $titleBand->height;
        for ($index = 0, $count = count($data); $index < $count;) {
            for ($bandIndex = 0; $bandIndex < $numBands && $index < $count; ++$bandIndex) {
                $band = $template->bands[$bandIndex];
                $numRects = count($band->rects);
                if ($count - $index < $numRects) {
                    $numRects = $count - $index;
                    $band = $template->find("padding-" . $numRects);
                }
                $content = NULL;
                for ($rectIndex = 0; $rectIndex < $numRects && $index < $count; ++$rectIndex, ++$index) {
                    $item = $data[$index];
                    if (Predicates::isNull($content) && Predicates::isNotEmpty($item["content"])) {
                        $content = $item["content"];
                    }
                }
                $height += $band->height;
                if (Predicates::isNotNull($content) && Predicates::isNotEmpty($content)) {
                    $height += 60; /* magic code */
                }
            }
        }
        $numMembers = $numAvatars = count($avatars);
        if ($numAvatars > 6) {
            $avatars = array_slice($avatars, 0, 6);
            $numAvatars = 6;
        } else if ($numAvatars == 0) {
            $avatars = ["default"];
            $numAvatars = 1;
        }
        $avatars[] = NULL;
        $numAvatars += 1;
        $summaryBand = $template->find(Predicates::isNull($platform) ? "summary" : "summary-$platform");
        $height += $summaryBand->height;
        $height *= $scale;
        $canvas = imagecreatetruecolor($width, $height);
        $futures = [];
        try {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            $bandY = 0;
            self::drawToTextBox($canvas, $bandY, self::$alignmentCenter | self::$alignmentMiddle, new RichText($eventTime, $titleBand->find("date"), $scale)); 
            self::drawToTextArea($canvas, $bandY, self::$alignmentCenter | self::$alignmentTop, new RichTextArea($eventTitle, $titleBand->find("theme"), $scale, $isDualTitle ? 2 : 1));
            $futures[] = self::drawToRect($canvas, $bandY, $titleBand->find("coverpage"), $scale, self::createRemoteImageLoader("/event/coverpage/", $eventCoverPage));
            if ($numAvatars & 1) {
                $prefix = "author-odd-";
                $startIndex = (7 - $numAvatars) / 2;
            } else {
                $prefix = "author-even-";
                $startIndex = (6 - $numAvatars) / 2;
            }
            array_reduce($avatars, function($carry, $avatar) use ($canvas, $bandY, $titleBand, $scale, $prefix, $startIndex, &$futures, $avatars, $numMembers) {
                switch ($avatar) {
                case NULL:
                    $loader = self::createMembersImageLoader($numMembers);
                    break;
                case "default":
                    $loader = self::createLocalImageLoader(APP_PATH . "/res/avatar.png", "imagecreatefrompng");
                    break;
                default:
                    $loader = self::createRemoteImageLoader("/profile/avatar/", $avatar);
                }
                $future = self::drawToRect($canvas, $bandY, $titleBand->find($prefix . ($carry + $startIndex)), $scale, $loader);
                if (Predicates::isNotNull($future)) {
                    $futures[] = $future;
                }
                return $carry + 1;
            }, 0);
            $bandY = $titleBand->height * $scale;
            $bandIndex = 0;
            $data = array_reverse($data);
            while (($left = count($data)) > 0) {
                $band = $template->bands[($bandIndex) % $numBands];
                $content = null;
                $authorSet = [];
                $authors = [];
retry:    
                $numRects = count($band->rects);
                if ($left < $numRects) {
                    $band = $template->find("padding-" . $left);
                    goto retry;
                }
                $footer = $band->find("footer");
                for ($rectIndex = 0; $rectIndex < $numRects && Predicates::isNotNull($item = array_pop($data)); ++$rectIndex) {
                    $author = $item["author"];
                    if (!array_key_exists($author, $authorSet)) {
                        $authors[] = $author;
                        $authorSet[$author] = 1;
                    }
                    if (Predicates::isNull($content) && Predicates::isNotEmpty($item["content"])) {
                        $content = $item["content"];
                        $footerY = $footer->y;
                        $footer = $footer->updateHeight(119)->updateY($footerY + 18);
                    }
                    $futures[] = self::drawToRect($canvas, $bandY, $band->rects[$rectIndex], $scale, self::createRemoteImageLoader("/event/moment/", $item["id"]));
                }
                $imageByLabel = $rectIndex > 1 ? "images by " : "image by ";
                $authorTextBox = $band->find("author");
resetAuthors:
                $authorsStr = array_reduce($authors, function($carry, $author) {
                    return (Predicates::isNull($carry) ? "" : $carry . ", ") . $author;
                });
                $authorTextBox = $band->find("author");
                $authorsStr = RichText::checkFont($authorsStr, $authorTextBox->font);
                if (RichText::envelope($imageByLabel . $authorsStr, $authorTextBox, $scale)->width > $authorTextBox->width) {
                    $authors = array_map(function($author) { return mb_strlen($author) > 6 ? mb_substr($author, 0, 6) . "..." : $author; }, $authors);
                    goto resetAuthors;
                }
                self::drawToTextBox($canvas, $bandY, self::$alignmentLeft | self::$alignmentMiddle,
                    new RichText($imageByLabel, $authorTextBox, $scale), 
                    (new RichText($authorsStr, $authorTextBox, $scale))->color("#8e915c"));
                if (Predicates::isNotNull($content)) {
                    self::drawToTextArea($canvas, $bandY, self::$alignmentCenter | self::$alignmentMiddle,
                                         new RichTextArea($content, $footer, $scale, 3));
                    $bandY += ($band->height + 60) * $scale;
                } else {
                    $bandY += $band->height * $scale;
                }
                ++$bandIndex;
            }

            $remoteFiles = ContentCache::loadAll(array_map(function($future) { return $future->path; }, $futures));
            for ($index = 0, $size = count($remoteFiles); $index < $size; ++$index) {
                $futures[$index]->get($remoteFiles[$index]);
            }
          
            self::drawToRect($canvas, $bandY, $summaryBand->find("qrcode"), $scale, self::createQRCodeLoader(Us\Config\SHARE_LINK_PREFIX . $eventInvitationCode));
            self::drawToRect($canvas, $bandY, $summaryBand->find("logo"), $scale, self::createLocalImageLoader(APP_PATH . "/res/us-logo.png", "imagecreatefrompng"));
            self::drawToRect($canvas, $bandY, $summaryBand->find("copyright"), $scale, self::createLocalImageLoader(APP_PATH . (Predicates::isNull($platform) ? "/res/copyright.png" : "/res/copyright-$platform.png"), "imagecreatefrompng"));
            header("Content-Type: image/jpeg");
            ob_start();
            imagejpeg($canvas, NULL, $quality);
            header("Content-Length:" . ob_get_length());
            echo ob_get_flush();
        } finally {
            imagedestroy($canvas);
        }
    }
}
?>
