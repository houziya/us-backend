<?php
use yii\db\Query;
/**
 * 
 * 图像处理类
 * @author ZhouXin
 * @internal功能包含：水印,缩略图
 */
class Img
{
	//图片格式
	private  $exts  = array ('image/jpg', 'image/jpeg', 'image/gif', 'image/bmp', 'image/png' );
	const ERR_MSG  = '加载GD库失败！';
	const ERR_MSG2 = '原图长度与宽度不能小于0';
	
 	public function __construct()
 	{
  		if (!function_exists( 'gd_info' ))
  		{
   			throw new Exception (self::ERR_MSG);
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
	    $thirdPic = @file_get_contents($url);
	    //自动重试机制
	    $httpError = explode(' ', $http_response_header[0]);
	    if ($httpError[1] != 200) {
	        $thirdPic = file_get_contents($url);
	    }
	    return $thirdPic;
	}
	
   /**
    * 获取腾讯云原图
    * @param unknown_type $category
    */
	  public static function getTenCentPic($category, $tenCentUrl, $path = '')
	  {
	      $tenCentPic = '';
	      $array = get_headers($tenCentUrl,1);
	      if (preg_match('/200/',$array[0])) {
	          $tenCentPic = Img::getOriginData($tenCentUrl);
	      }
	      //创建本地临时文件路径
	      $urlEx = explode('/', $tenCentUrl);
	      $url = '/tmp/'.$urlEx[4];
	      return [
	          'url' => $url,
	          'tenCentPic' => $tenCentPic,
	      ];
	  }
	  
	  /**
	   * 固定尺寸缩略图
	   */
	  public static function fixSizeThumb($category, $tencentUrl, $srcPath, $bucketName, $tencentFileName, $categoryDir, $suffix)
	  {
              $result = Img::getTenCentPic($categoryDir,  $tencentUrl);
              $option = array(150);
              $img_info = getimagesize($srcPath);
              foreach ($option as $value)
              {
                  $subUrl = explode('.', $result['url']);
                  $save_path = $subUrl[0].'_'.$value.'.'.$suffix;
                  Img::resize_image($srcPath, $save_path, array('width' => $value, 'height' => $value));
                  $fileName = explode('/', explode('.', $tencentFileName)[0]);
                  //调用腾讯云上传接口
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
                  }
                  $value = $value.'x';
                  $fixThumb = TencentCosapi::upload_slice($save_path,$bucketName,$dirName.$fileName[3].'_'.$value.'.'.$suffix);
              }
              return $fixThumb;
	  }
	  
	  /**
	   * 利用原图的name取出人脸识别信息
	   */
	  public static function getPicFace($url)
	  {
	      $query = new Query;
	      return $query->select('data') ->from('moment_picture') ->where(['object_id' => $url])->one();
	  }
	  
	  /**
	   * 确定人脸位置 
	   * @param unknown_type $type
	   * 腾讯云的缓存到本地的文件 $tmpTenCentPic :  /tmp/f0d624b6_origin_200_300_event_face.jpg
       * 存在本地的位置 $save_path : /usr/local/nginx/us/public/thumb/event/f0d624b6_origin_200_300_event_face.jpg
       * 需要生成的尺寸 $option : Array ( [width] => 200 [height] => 300 ) 
       * 图片人脸识别的信息 $jsonData : Array ( [data] => {"session_id":"","image_height":489,"image_width":743,"face":[{"face_id":"1213952648176271359","x":298,"y":190,"height":64,"width":64,"pitch":-4,"roll":-8,"yaw":-7,"age":15,"gender":22,"glass":false,"expression":19,"beauty":66}],"errorcode":0,"errormsg":"OK"} ) 
	   */
	  public static function faceLocation($tmpTenCentPic, $save_path, $option, $jsonData, $path, $result, $suffix0, $tenCentSub, $flag = 0)
	  { 
          $statRet = TencentCosapi::stat('uspic', $tenCentSub.'/'.$path);
          if ($statRet['code'] == 0) {
              $newUrl = file_get_contents($statRet['data']['access_url']);
              if ($flag == 0) {
                  header ( 'Content-Type: image/png' );
                  echo $newUrl;
                  return true;
              } else {
                  return @imagecreatefromjpeg($newUrl);
              }
          }
	      
	      $lastArrX = 0;
	      $lastArrY = 0;
	      if (!empty($jsonData)) {
	          $data = json_decode($jsonData['data']);
	          
	          //老数据兼容，清除数据后可删掉
	          if (!empty($data->image_height)) {
	              $data = $data->face;
	          } 
	          if(!empty($data)) {
	              foreach ($data as $k => $v) {
	                  $xGroup[] = $v->x;
	                  $yGroup[] = $v->y;
	              }
	              if (!empty($xGroup) && !empty($yGroup)) {
	                  sort($xGroup);
	                  sort($yGroup);
	                  $lastArrX = $xGroup[0];
	                  $lastArrY = $yGroup[0];
	              }
	          }
	      }
	      Img::thumb_img($tmpTenCentPic, $save_path, $option, $lastArrX, $lastArrY);
	      
	      if ($suffix0 != -1) {
	          Moca\PhotoFilter\Filter::run($suffix0, $save_path, $save_path);
	      }
	      
	      //加上回源以后可去掉
	      TencentCosapi::upload_slice($save_path, 'uspic', '/event/'.$path);
	      
	      $newUrl = file_get_contents($save_path);
	      if ($flag == '0') {
	          header ( 'Content-Type: image/png' );
	          echo $newUrl;
	      }
	      return @imagecreatefromjpeg($save_path);
	  }
	  
 	/**
  	* 裁剪压缩
  	* @param $src_img 图片 
  	* @param $save_img 生成后的图片
  	* @param $option 参数选项，包括： $maxwidth  宽  $maxheight 高
  	* array('width'=>xx,'height'=>xxx)
  	* @internal
  	*/
	  
 	public static function thumb_img($src_img, $save_img = '', $option, $objectFaceX = 0, $objectFaceY = 0)
 	{
 		if (empty ( $option ['width'] ) or empty ( $option ['height'] )) {
   			return array ('flag' => False, 'msg' => self::ERR_MSG2 );
  		}
  		
  		$org_ext = Img::is_img($src_img);
  		if (!$org_ext['flag']) {
   			return $org_ext;
  		}

  		//如果有保存路径，则确定路径是否正确
  		if (!empty($save_img)) {
   			$f = Img::check_dir($save_img);
   			if (!$f['flag'])
    			return $f;
  		}
  
  		//获取出相应的方法
  		$org_funcs = Img::get_img_funcs($org_ext['msg']);
  
  		//获取原大小
  		$source = $org_funcs['create_func']($src_img);
  		$src_w = imagesx($source);
  		$src_h = imagesy($source);
  		//调整原始图像(保持图片原形状裁剪图像)
  		$dst_scale = $option['height'] / $option['width']; //目标图像长宽比
  		$src_scale = $src_h / $src_w; // 原图长宽比
  		if ($src_scale >= $dst_scale) { 
  			// 过高
   			$w = intval($src_w);
   			$h = intval($dst_scale * $w);
  	 		$x = 0;
   			$y = ($src_h - $h) / 3;
  		} else { 
  			// 过宽
   			$h = intval($src_h);
   			$w = intval($h / $dst_scale);
   			$x = ($src_w - $w) / 2;
   			$y = 0;
  		}
  		
        if ($x > $objectFaceX && $objectFaceX != 0 || $option['height'] > $option['width'] && $x > 200) {
            $x = $objectFaceX;
        } else if ($y > $objectFaceY && $objectFaceY != 0) {
            $y = $objectFaceY;
        }
  		// 剪裁
  		$croped = imagecreatetruecolor($w, $h);
  		imagecopy($croped, $source, 0, 0, $x, $y, $src_w, $src_h);
  		// 缩放
  		$scale = $option['width'] / $w;
  		$target = imagecreatetruecolor($option['width'], $option['height']);
  		$final_w = intval($w * $scale);
  		$final_h = intval($h * $scale);
  		imagecopyresampled($target, $croped, 0, 0, 0, 0, $final_w, $final_h, $w, $h);
  		imagedestroy($croped);
  		// 输出(保存)图片
  		if (!empty($save_img)) {
  			$org_funcs['save_func']($target, $save_img, 100);
  		} else {
   			header($org_funcs ['header']);
   			$org_funcs ['save_func']($target, NULL, 100);
  		}
  		return ['target' => $target];
 	}
 
 	/**
  	* 
  	* 等比例缩放图像
  	* @param $src_img 原图片
  	* @param $save_img 需要保存的地方
  	* @param $option 参数设置
  	* 
  	*/
 	public static function  resize_image($src_img, $save_img = '', $option )
 	{
  		$org_ext = Img::is_img ( $src_img );
  		if (! $org_ext ['flag']) {
   			return $org_ext;
  		}
  		//如果有保存路径，则确定路径是否正确
  		if (! empty ( $save_img )) {
   			$f = Img::check_dir ( $save_img );
   			if (! $f ['flag']) {
    			return $f;
   			}
  		}
  		//获取出相应的方法
  		$org_funcs = Img::get_img_funcs ( $org_ext ['msg'] );
  		//获取原大小
  		$source = $org_funcs ['create_func'] ( $src_img );
  		$src_w = imagesx ( $source );
  		$src_h = imagesy ( $source );
  		if (($option ['width'] && $src_w >= $option ['width']) || ($option ['height'] && $src_h >= $option ['height'])) {
   			if ($option ['width'] && $src_w >= $option ['width']) {
    			$widthratio = $option ['width'] / $src_w;
    			$resizewidth_tag = true;
   			} else {
   				$resizewidth_tag = false;
   			}
		   	if ($option ['height'] && $src_h >= $option ['height']) {
		   		$heightratio = $option ['height'] / $src_h;
		    	$resizeheight_tag = true;
		   	} else {
   				$resizeheight_tag = false;
   			}
		   	if ($resizewidth_tag && $resizeheight_tag) 	{
		   		if ($widthratio < $heightratio) {
		     		$ratio = $heightratio;
		   		} else {
					$ratio = $widthratio;
		   		}
			}
		   	if ($resizewidth_tag && ! $resizeheight_tag) {
		    	$ratio = $widthratio;
		   	}
		   	if ($resizeheight_tag && ! $resizewidth_tag) {
		    	$ratio = $heightratio;
		   	}
		   	$newwidth = $src_w * $ratio;
		   	$newheight = $src_h * $ratio;
		   	if (function_exists ( "imagecopyresampled" )) {
		    	$newim = imagecreatetruecolor ( $newwidth, $newheight);
		    	imagecopyresampled ( $newim, $source, 0, 0, 0, 0, $newwidth, $newheight, $src_w, $src_h );
		   	} else {
		    	$newim = imagecreate ( $newwidth, $newheight );
		    	imagecopyresized ( $newim, $source, 0, 0, 0, 0, $newwidth, $newheight, $src_w, $src_h );
		   	}
  		} else {
  		    $newim = imagecreatefromjpeg($src_img);
  		} 
		// 输出(保存)图片
		if ( !empty ( $save_img )) {
			$org_funcs ['save_func'] ( $newim, $save_img, 50);
		} else {
		   	header ( $org_funcs ['header'] );
		   	$org_funcs ['save_func'] ( $newim, NULL, 50);
		}
		
		imagedestroy ( $newim );
		return array ('flag' => True, 'msg' => '' );
 	}
 
	/**
	 * 
	 * 生成水印图片
	 * @param  $org_img 原图像
	 * @param  $mark_img 水印标记图像
	 * @param  $save_img 当其目录不存在时，会试着创建目录
	 * @param array $option 为水印的一些基本设置包含：
	 * x:水印的水平位置,默认为减去水印图宽度后的值
	 * y:水印的垂直位置,默认为减去水印图高度后的值
	 * alpha:alpha值(控制透明度),默认为50
	 */
	public static function water_mark($org_img, $mark_img, $save_img = '', $option = array())
	{
	   
		//检查图片
		$org_ext = $this->is_img ( $org_img );
		if (! $org_ext ['flag'])
	  	{
	   		return $org_ext;
	  	}
	  	
	  	$mark_ext = $this->is_img ( $mark_img );
	  	if (! $mark_ext ['flag'])
	  	{
	   		return $mark_ext;
	  	}
	  	//如果有保存路径，则确定路径是否正确
	  	if (! empty ( $save_img ))
	  	{
	   		$f = $this->check_dir ( $save_img );
	   		if (! $f ['flag'])
	   		{
	    		return $f;
	   		}
	  	}
	  
	  	//获取相应画布
		$org_funcs = $this->get_img_funcs ( $org_ext ['msg'] );
	  	$org_img_im = $org_funcs ['create_func'] ( $org_img );
	  
	  	$mark_funcs = $this->get_img_funcs ( $mark_ext ['msg'] );
	  	$mark_img_im = $mark_funcs ['create_func'] ( $mark_img );
	  
	  	//拷贝水印图片坐标
	  	$mark_img_im_x = 0;
	  	$mark_img_im_y = 0;
	  	//拷贝水印图片高宽
	  	$mark_img_w = imagesx ( $mark_img_im );
	  	$mark_img_h = imagesy ( $mark_img_im );
	  
	  	$org_img_w = imagesx ( $org_img_im );
	  	$org_img_h = imagesx ( $org_img_im );
	  
	  	//合成生成点坐标
	  	$x = $org_img_w - $mark_img_w;
	  	$org_img_im_x = isset ( $option ['x'] ) ? $option ['x'] : $x;
	  	$org_img_im_x = ($org_img_im_x > $org_img_w or $org_img_im_x < 0) ? $x : $org_img_im_x;
	  	$y = $org_img_h - $mark_img_h;
	  	$org_img_im_y = isset ( $option ['y'] ) ? $option ['y'] : $y;
	  	$org_img_im_y = ($org_img_im_y > $org_img_h or $org_img_im_y < 0) ? $y : $org_img_im_y;
	  
	  	//alpha
	  	$alpha = isset ( $option ['alpha'] ) ? $option ['alpha'] : 50;
	  	$alpha = ($alpha > 100 or $alpha < 0) ? 50 : $alpha;
	  
	  	//合并图片
	  	imagecopymerge ( $org_img_im, $mark_img_im, $org_img_im_x, $org_img_im_y, $mark_img_im_x, $mark_img_im_y, $mark_img_w, $mark_img_h, $alpha );
	  
	  	//输出(保存)图片
	  	if (! empty ( $save_img ))
	  	{
	   		$org_funcs ['save_func'] ( $org_img_im, $save_img, 100);
	  	} 
	  	else
	  	{
	   		header ( $org_funcs ['header'] );
	   		$org_funcs ['save_func'] ( $org_img_im, NULL, 100);
	  	}
	  	//销毁画布
	  	imagedestroy ( $org_img_im );
	  	imagedestroy ( $mark_img_im );
	  	return array ('flag' => True, 'msg' => '' );
	 }
 
	/**
	 * 
	 * 检查图片
	 * @param unknown_type $img_path
	 * @return array('flag'=>true/false,'msg'=>ext/错误信息) 
	 */
	public static function is_img($img_path)
	{
		
	  	if (! file_exists ( $img_path ))
	  	{
	   		return array ('flag' => False, 'msg' => "加载图片 $img_path 失败！" );
	  	}
	  	
	  	//$ext = explode ( '.', $img_path );
	  	//$ext = strtolower ( end ( $ext ) );
	  	
	  	$img_size = getimagesize($img_path);
	  	
	  	$ext = $img_size['mime'];
	  	
	  	$exts  = array ('image/jpg', 'image/jpeg', 'image/gif', 'image/bmp', 'image/png' );
	  	if (! in_array ( $ext, $exts ))
	  	    
	  	{
	   		return array ('flag' => False, 'msg' => "图片 $img_path 格式不正确！" );
	  	}
	  	return array ('flag' => True, 'msg' => $ext );
	}
 
	/**
  	 * 
  	 * 返回正确的图片函数
     * @param unknown_type $ext
     */
	public static function get_img_funcs($ext)
	{
		//选择
	  	switch ($ext)
	  	{
	   		case 'image/jpg' :
			    $header = 'Content-Type:image/jpeg';
			    $createfunc = 'imagecreatefromjpeg';
			    $savefunc = 'imagejpeg';
			    break;
	   		case 'image/jpeg' :
			    $header = 'Content-Type:image/jpeg';
			    $createfunc = 'imagecreatefromjpeg';
			    $savefunc = 'imagejpeg';
			    break;
	   		case 'image/gif' :
			    $header = 'Content-Type:image/gif';
			    $createfunc = 'imagecreatefromgif';
			    //$savefunc = 'imagegif';
			    $savefunc = 'imagejpeg';
			    break;
	   		case 'image/bmp' :
			    $header = 'Content-Type:image/bmp';
			    $createfunc = 'imagecreatefrombmp';
			    //$savefunc = 'imagebmp';
			    $savefunc = 'imagejpeg';
			    break;
	   		default :
		    $header = 'Content-Type:image/png';
		    $createfunc = 'imagecreatefrompng';
		    //$savefunc = 'imagepng';
		    $savefunc = 'imagejpeg';
	  	}
		return array ('save_func' => $savefunc, 'create_func' => $createfunc, 'header' => $header );
	}
	
	/** 
	 * 
	 * 检查并试着创建目录
	 * @param $save_img
	 */
 	public static function check_dir($save_img)
 	{
  		$dir = dirname ( $save_img );
  		if (! is_dir ( $dir ))
  		{
			if (! mkdir ( $dir, 0777, true ))
		   	{
		    	return array ('flag' => False, 'msg' => "图片保存目录 $dir 无法创建！" );
		   	}
  		}
  		return array ('flag' => True, 'msg' => '' );
 	}
 	
 	function combine_image($image1, $image2, $save_img, $width, $height)
 	{
 		$im = imagecreatetruecolor($width, $height);
 		$white = imagecolorallocatealpha($im, 255, 255, 255,127);
 		imagefill ($im, 0, 0, $white);
 		$wimage_data = GetImageSize($image1);
 		switch($wimage_data[2])
 		{
 			case 1:
 				$im1=@ImageCreateFromGIF($image1);
 				break;
 			case 2:
 				$im1=@ImageCreateFromJPEG($image1);
 				break;
 			case 3:
 				$im1=@ImageCreateFromPNG($image1);
 				break;
 		}
 		$wimage_data = GetImageSize($image2);
 		switch($wimage_data[2])
 		{
 			case 1:
 				$im2=@ImageCreateFromGIF($image2);
 				break;
 			case 2:
 				$im2=@ImageCreateFromJPEG($image2);
 				break;
 			case 3:
 				$im2=@ImageCreateFromPNG($image2);
 				break;
 		}
 	
 		imagecopy($im1, $im2,0,0,0,0,320,240);
 		imagejpeg($im1, $save_img, 100);
 		imagedestroy($im);
 		imagedestroy($im1);
 		imagedestroy($im2); 
 	}

    /**
     * 获取图片
     * @param $url  地址
     * @param string $save_dir  保存路径
     * @param string $filename  文件名
     * @param int $type
     * @return array
     */
    function getImage($url,$save_dir='',$filename='',$type=0)
 	{
 		if(trim($url)=='')
        {
 			return array('file_name'=>'','save_path'=>'','error'=>1);
 		}

 		if(trim($save_dir)=='')
        {
 			$save_dir='./';
 		}

 		if(trim($filename)=='')
        {//保存文件名
 			$ext=strrchr($url,'.');
 			//if($ext!='.gif'&&$ext!='.jpg')
            if ($ext!='.jpg')
            {
 				return array('file_name'=>'','save_path'=>'','error'=>3);
 			}
 			$filename=time().$ext;
 		}

 		if(0!==strrpos($save_dir,'/'))
        {
 			$save_dir.='/';
 		}
 		//创建保存目录
 		if(!file_exists($save_dir)&&!mkdir($save_dir,0777,true))
        {
 			return array('file_name'=>'','save_path'=>'','error'=>5);
 		}
 		//获取远程文件所采用的方法
 		if($type)
        {
 			$ch=curl_init();
 			$timeout=5;
 			curl_setopt($ch,CURLOPT_URL,$url);
 			curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
 			curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
 			$img=curl_exec($ch);
 			curl_close($ch);
 		}
        else
        {
 			ob_start();
 			readfile($url);
 			$img=ob_get_contents();
 			ob_end_clean();
 		}

 		$fp2=@fopen($save_dir.$filename,'a');
 		fwrite($fp2,$img);
 		fclose($fp2);
 		unset($img,$url);
 		return array('file_name'=>$filename,'save_path'=>$save_dir.$filename,'error'=>0);
 	}

    /**
     * 复制图片
     * @param $url
     * @param $save_dir
     * @return bool
     */
    function copy_image($url, $save_dir)
 	{
 		return copy($url, $save_dir);
 	}
}
