<?PHP

class FacePP
{
    public $server = 'http://apicn.faceplusplus.com/v2';

    public static function detect($url)
    {
        $response = self::execute('/detection/detect', ["url" => $url]);
        if ($response["http_code"] != "200" || ($response = @json_decode($response["body"], true)) == null || 
            ($faces = @$response["face"]) == NULL || count($faces) == 0) {
            return null;
        }

        $width = $response["img_width"] / 100;
        $height = $response["img_height"] / 100;
        $faces = array_map(function($face) use ($width, $height) {
            $angle = @$face["attribute"]["pose"]["roll_angle"]["value"];
            $face = $face["position"];
            $widthRatio = $face["width"];
            $heightRatio = $face["height"];
            $position = $face["center"];
            $face = ["x" => $position["x"] * $width, "y" => $position["y"] * $height, "w" => $widthRatio * $width, "h" => $heightRatio * $height];
            $face["x"] = $face["x"] - $face["w"] / 2;
            $face["y"] = $face["y"] - $face["h"] / 2;
            if ($face["x"] < 0) {
                $face["x"] = 0;
            }
            if ($face["y"] < 0) {
                $face["y"] = 0;
            }
            return array_map(function($v) { return intval($v); }, $face);
        }, $faces);
        if (count($faces) > 1) {
            usort($faces, function($lhs, $rhs) {
                $lhsArea = $lhs['h'] * $lhs['w'];
                $rhsArea = $lhs['h'] * $rhs['w'];
                if ($lhsArea == $rhsArea) {
                    return 0;
                } else if ($lhsArea > $rhsArea) {
                    return -1;
                } else {
                    return 1;
                }
            });
        }
        return json_encode($faces);
    }

    /**
     * @param $method - The Face++ API
     * @param array $params - Request Parameters
     * @return array - {'http_code':'Http Status Code', 'request_url':'Http Request URL','body':' JSON Response'}
     * @throws Exception
     */
    private static function execute($method, array $params)
    {
        $params['api_key'] = Us\Config\FacePP\API_KEY;
        $params['api_secret'] = Us\Config\FacePP\API_SECRET;
        $params['attribute'] = 'pose';
        return self::request("http://apicn.faceplusplus.com/v2{$method}", $params);
    }

    private static function request($request_url, $request_body)
    {
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $request_url);
        curl_setopt($curl_handle, CURLOPT_FILETIME, true);
        curl_setopt($curl_handle, CURLOPT_FRESH_CONNECT, false);
        if (version_compare(phpversion(),"5.5","<=")) {
            curl_setopt($curl_handle, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED);
        } else {
            curl_setopt($curl_handle, CURLOPT_SAFE_UPLOAD, false);
        }
        curl_setopt($curl_handle, CURLOPT_MAXREDIRS, 5);
        curl_setopt($curl_handle, CURLOPT_HEADER, false);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 5184000);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($curl_handle, CURLOPT_NOSIGNAL, true);
        curl_setopt($curl_handle, CURLOPT_REFERER, $request_url);
        curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Faceplusplus PHP SDK/1.1');
        
        if (extension_loaded('zlib')) {
            curl_setopt($curl_handle, CURLOPT_ENCODING, '');
        }

        curl_setopt($curl_handle, CURLOPT_POST, true);

        $request_body = http_build_query($request_body);

        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $request_body);

        $response_text      = curl_exec($curl_handle);
        $response_header    = curl_getinfo($curl_handle);
        curl_close($curl_handle);

        return array (
            'http_code'     => $response_header['http_code'],
            'request_url'   => $request_url,
            'body'          => $response_text
        );
    }
}
