<?php

class Types
{
    public static function unix2SQLTimestamp($timestamp = NULL)
    {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    public static function versionToLong($version)
    {
        $result = 0;
        foreach(explode(".", Preconditions::checkNotNull($version)) as $value) {
            $result <<= 8;
            $result |= $value;
        }
        return $result;
    }
    
    public static function longToVersion($long)
    {
        $result = "";
        $tmp = [];
        while ($long) {
            array_push($tmp, $long & 0xff);
            $long >>= 8;
        }
        $len = count($tmp);
        for ($i=$len-1; $i>=0; $i--) {
            if (!$i) {
                $result .= $tmp[$i];
            }
            else {
                $result .= $tmp[$i].".";
            }
        }
        return $result;
    }
}

?>
