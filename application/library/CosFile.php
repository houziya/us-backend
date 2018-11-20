 <?php  
spl_autoload_register(function($class){
    $dir = dirname(__FILE__);
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    include($dir.DIRECTORY_SEPARATOR.$class);
});
use Tencent\TencentConf;
use Tencent\TencentCosapi;
use Tencent\TencentYouTuConf;
use Tencent\TencentYouTuHttp;
use Tencent\TencentYouTu;
use Tencent\TencentAuth;
/**
 *  腾讯云文件管理类
 */
class CosFile
{
    //上传图片常量 
    const CATEGORY_HEAD = 0;//头像
    const CATEGORY_EVENT = 1;//活动封面
    const CATEGORY_SCENE = 2;//活动现场
    const CATEGORY_STARTED = 3;//启动图
    const CATEGORY_DEFAULT_STARTED = 4;//活动封面默认图
    const CATEGORY_DEFAULT_AVATAR = 5;//用户默认头像
    const CATEGORY_PACKAGE = 6;//安装包
    const CATEGORY_GROUP_COVERPAGE = 7;//小组封面
    const PICTURE_TYPE_ORIGINAL = 0;//原图
    const PICTURE_TYPE_FILTER = 1;//滤镜
    const FILE_TYPE_PICTURE = 0;//图片
    const FILE_TYPE_VIDEO = 1;//视频
    //文件类型
    private static 	$uptypes = array(
                    'image/jpg',
                    'image/jpeg',
                    'image/png',
                    'Audio/amr',
                    'video/mp4',
                    'application/octet-stream'
    );
    //上传文件大小限制(单位BYTE)
    private static $max_file_size = 52428800;
    
    public static function checkData($file, $uid, $category, $pictureType, $filetype, $fileName, $event_id, $moment_id){
        if (empty($fileName)) {
            $fileName = CosFile::randPicName($event_id, $moment_id, $category, $file['name']);
        }
        CosFile::checkFile($file['tmp_name'], $file["type"]);
        $suffix = explode('/', $file["type"]);
        if ( $suffix[1] == 'jpeg') {
            $suffix[1] = 'jpg';
        }
        if ($suffix[1] == 'octet-stream') {
            $suffix[1] = '';
        }
		$optDir = CosFile::optDir($pictureType, $category, $filetype, $fileName, $suffix[1]);
		$categorys = explode('/',$optDir['tencentFileName']);
    	return [
        	'srcPath' => $file['tmp_name'],
        	'tencentFileName' => $optDir['tencentFileName'],
        	'bucketName' => $optDir['bucketName'],
        	'categoryDir' => '/'.$categorys[1].'/'.$categorys[2].'/',
        	'suffix' => $suffix[1],
    	];
    }
    
    public static function getThird($url)
    {
        $thirdPic = CosFile::getOriginData($url);
        $path = CosFile::randPicName(0, 0).'.jpg';
        file_put_contents("/tmp/".$path,$thirdPic,FILE_APPEND);
        $tmpthirdPic = "/tmp/".$path;
        return [
                'srcPath' =>  $tmpthirdPic,
                'bucketName' => Us\Config\QCloud\BUCKET,
                'tencentFileName' => '/profile/avatar/'.$path,
                'categoryDir' => '/profile/avatar/',
                'suffix' => 'jpg',
        ];
    }
    
	/**
	 * 腾讯云cos上传文件（图片或视频）
	 * @param $filetype：上传类型 0 图片 , 1 视频  
	 * @param $pictureType: 0原图  1滤镜 （只有活动现场时有）
     * @param $category :0头像   1活动封面  2活动现场 3启动图
     * $url = 'http://ww4.sinaimg.cn/crop.0.0.480.480.1024/ea33c7d2jw8egke8rdv17j20dc0dcaan.jpg';
	 */
	public static function uploadFile($file, $uid, $category = 0, $pictureType = 0, $filetype = 0,
	         $event_id = 0, $moment_id = 0, $fileName='', $url='')
	{
	    //第三方图像
	    if (!empty($url)) {
	        $result = CosFile::getThird($url);
        } else {
            $result = CosFile::checkData($file, $uid, $category, $pictureType, $filetype, $fileName,
                     $event_id, $moment_id);
	    }
	    $srcPath = $result['srcPath'];
	    $bucketName = $result['bucketName'];
	    $tencentFileName = $result['tencentFileName'];
	    $categoryDir = $result['categoryDir'];
	    $result = Execution::autoUnlink(function($unlinkLater) use ($file, $srcPath, $bucketName,
	             $tencentFileName, $category, $categoryDir) {
    		//分片上传 10M以上
    		$sliceUploadRet = TencentCosapi::upload_slice($srcPath, $bucketName, $tencentFileName);
    		if ($sliceUploadRet['code'] == 0) {
    		    $resultUrl = CosFile::subUrl($sliceUploadRet['data']['access_url'], $category);
    		    //人脸识别
    		    $data = NULL;
    		    if( $category == self::CATEGORY_SCENE || $category == self::CATEGORY_EVENT) {
    		        switch (Us\Config\QCloud\TENCENT_UPLOAD_SOURCE) {
    		            case 0:
    		                $data = CosFile::face($sliceUploadRet['data']['access_url']);
    		                break;
    		            case 1:
    		                $data = FacePP::detect($sliceUploadRet['data']['access_url']);
    		                break;
    		        }
    		    }
    		    return [
    		        'url' => $sliceUploadRet['data']['access_url'],
    		        'subUrl' => $resultUrl['subUrl'],
    		        'subUrlName' => $resultUrl['subUrlName'],
    		        'data' => $data,
    		    ];
    		} else {
    		    Protocol::badRequest(NULL, Notice::get()->uploadFailed());
    		}
    	});
	    return $result;
	}

	public static function subUrl($sliceUploadRet, $category){
	    $subUrl = explode('com/', $sliceUploadRet);
	    if ($category == self::CATEGORY_PACKAGE) {
	        $subUrlName = explode('/', $subUrl[1])[1];
	    } else {
	        $subUrlName = explode('.', explode('/', $subUrl[1])[2]);
	    }
	    return [
	        'subUrl' => $subUrl[1],
	        'subUrlName' => $subUrlName[0]
	    ];
	}
	
	public static function optDir($pictureType, $category, $filetype, $fileName, $suffix){
	    if (empty($suffix)) {
	        $tencentFileName = $fileName;
	    } else {
    	    switch ($pictureType) {
    	        case 0:
    	            $tencentFileName = $fileName.'.'.$suffix;
    	            break;
    	        case 1:
    	            $tencentFileName = $fileName.'.'.$suffix;
    	            break;
    	    }
	    }
        $dirName = self::tenCentDir($category);
	    switch ($filetype) {
	        case 0:
	            $bucketName = Us\Config\QCloud\BUCKET;
	            break;
            default:
                throw new Exception("Invalid type");
	    }
	    $tencentFileName = $dirName.$tencentFileName;
	    return [
    	    'tencentFileName' => $tencentFileName,
    	    'bucketName' => $bucketName,
	    ];
	}
	
    public static function tenCentDir($category)
    {
        switch ($category) {
            case 0:
                $dirName = '/profile/avatar/';
                break;
            case 1:
                $dirName = '/event/coverpage/';
                break;
            case 2:
                $dirName = '/event/moment/';
                break;
            case 3:
                $dirName = '/images/splash/';
                break;
            case 4:
                $dirName = '/event/coverpage/';
                break;
            case 5:
                $dirName = '/profile/avatar/';
                break;
            case 6:
                $dirName = '/package/';
                break;
            case 7:
                $dirName = '/group/coverpage/';
                break;
        }
        return $dirName;
    }
    
    public static function randPicName($event_id, $moment_id = 0, $category = '', $fileName = ''){
        if ($event_id !=0 && $moment_id != 0) {
            return $event_id.'-'.$moment_id.'-'.uuid_create();
        } elseif ($event_id != 0) {
            return $event_id.'-'.uuid_create();
        } elseif ($category == CosFile::CATEGORY_DEFAULT_STARTED || $category == CosFile::CATEGORY_DEFAULT_AVATAR) {
            return 'default';
        } elseif ($category == CosFile::CATEGORY_PACKAGE) {
            return $fileName;
        } else {
            return uuid_create();
        }
    } 
    
    /**
     * 获取第三方图片信息
     */
    public static function getOriginData($url)
    {
        $subUrl = explode('://',$url);
        if ($subUrl[0] != 'http') {
            $url = urldecode (rawurldecode($url));
        }
        //取得原图
        return Http::download($url);
    }
    
    public static function checkFile($tmpName, $type){
        //是否存在文件
        if(!file_exists($tmpName)){
            //提示源文件不存在
            return false;
        }
        //检查文件大小
        $fileSize = filesize($tmpName);
        if(self::$max_file_size < $fileSize){
            //提示源文件大小超出范围
            return false;
        }
        //检查文件类型
        if(!in_array($type, self::$uptypes)){
            //提示文件类型不符
            return false;
        }
    }

    /**
     * 人脸识别
     */
    public static function face($url)
    {
        //设置APP 鉴权信息
        $appid = TencentConf::APPID;
        $secretId = TencentConf::SECRET_ID;
        $secretKey = TencentConf::SECRET_KEY;
        $userid = TencentConf::USERID;
        //(开平服务)
        TencentYouTuConf::setAppInfo($appid, $secretId, $secretKey, $userid,TencentYouTuConf::API_YOUTU_END_POINT);
        //人脸检测接口调用
        $uploadRet = TencentYouTu::detectfaceurl($url, 0);
        if (empty($uploadRet['face'])) {
            return null;
        }
        $faces = array_map(function($face) {
            $converted = [];
            Accessor::copyRequiredBetween(["x" => "x", "y" => "y", "width" => "w", "height" => "h"], $face, $converted);
            return $converted;
        }, $uploadRet['face']);

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
        return json_encode($faces, JSON_UNESCAPED_UNICODE);
     }
    
     /**
      * 删除原文件
      * $dstPath = 'ee1e3c46-3333-4b04-8bb2-1894a9e07cc4' 原文件名
      * $category = 0 头像   ||  1封面  || 2现场
      */
     public static function delFile($dstPath, $category, $bucketName = NULL)
     {
         $bucketName = Accessor::either($bucketName, Us\Config\QCloud\BUCKET);
         $dir = self::tenCentDir($category);
         $dstPath = $dir.$dstPath.'.jpg';
         return TencentCosapi::del($bucketName, $dstPath);
     }
     
     /**
      * 获取多次有效sign，用于上传和下载
      */
     public static function getRepeatSign()
     {
         return TencentAuth::appSign(time() + Us\Config\QCloud\COS_SIGN_EXPIRE, Us\Config\QCloud\BUCKET);
     }
      
     /**
      * 获取单次有效sign，用于删除和更新
      * 'event/coverpage/001ce282-a942-4e7b-bd91-a5a3e696c2c6_400x.jpg';
      */
     public static function getSingleSign($path)
     {
         return TencentAuth::appSign_once($path, Us\Config\QCloud\BUCKET);
     }
     
     /**
      * 微信图片上传
      */
     public static function uploadWeChatImage ($srcPath, $eventId, $momentId)
     {
         $tencentFileName =  '/event/moment/' . CosFile::randPicName($eventId, $momentId).'.jpg';
         $sliceUploadRet = TencentCosapi::upload_slice($srcPath, Us\Config\QCloud\BUCKET, $tencentFileName);
         if ($sliceUploadRet['code'] == 0) {
             $resultUrl = CosFile::subUrl($sliceUploadRet['data']['access_url'], 2);
             //人脸识别
             $data = NULL;
             switch (Us\Config\QCloud\TENCENT_UPLOAD_SOURCE) {
                case 0:
                    $data = CosFile::face($sliceUploadRet['data']['access_url']);
                    break;
                case 1:
                    $data = FacePP::detect($sliceUploadRet['data']['access_url']);
                    break;
             }
             $img = getimagesize($srcPath);
             return [
             'url' => $sliceUploadRet['data']['access_url'],
             'subUrl' => $resultUrl['subUrl'],
             'subUrlName' => $resultUrl['subUrlName'],
             'data' => $data,
             'size' => $img[0].'x'.$img[1],
             ];
        }
     }
}