<?php 
class Http
{
    public static function sendPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $return = curl_exec($ch);
        curl_close($ch);
        return $return;
    }
    
    private static function doSendGet($url)
    {
        $ch=curl_init($url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_BINARYTRANSFER,true);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public static function sendGet($url)
    {
       return json_decode(self::doSendGet($url), true);
    }
    
    public static function download($url)
    {
        return self::doSendGet($url);
    }
}
?>