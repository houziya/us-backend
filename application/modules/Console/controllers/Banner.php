<?php 
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
class BannerController extends Controller_Abstract
{
    public function BannersAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data=Protocol::arguments();
        $row_count=CBanner::count();
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $banners=CBanner::getDatas($start,$page_size);
        foreach ($banners as &$banner) {
                $banner['images']=json_decode($banner['images'],true);
                $banner['code']=json_decode($banner['code'],true);
                $banner['code']=implode(',',$banner['code']);
        }
//         var_dump($startimgs);die;
        $page_html=CPagination::showPager("imgs",$page_no,$page_size,$row_count);
        //追加操作的确认层
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign('banners',$banners);
        $this->display('banners');
    }
    
    public function AddAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $package_datas=CPackage::getSearch();
        if (Protocol::getMethod() == "POST") {
            $transaction=Yii::$app->db->beginTransaction();
            $commit=false;
            try{
                    if (!empty($_FILES['android'])) {
                        if (empty($data->optional('code'))) {
                            $code = '';
                        } else {
                            $code=json_encode($data->optional('code'));
                        }
                        
                        $result=CosFile::uploadFile($_FILES['android'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $android=array( 'android' => $result['subUrlName']);
                        $images=json_encode($android);
                        $input_data = array ('images' => $images, 'title' => $data->required('title'),  'operator' => $current_user_info['user_name'], 'action_type' => $data->required('type') ,'skip_url' => $data->required('skip_url') ,'code' => $code, 'platform' => 0);
                    } else {
                        if (empty($data->optional('code'))) {
                            $code = '';
                        } else {
                            $code=json_encode($data->optional('code'));
                        }
                        
                        $result1=CosFile::uploadFile($_FILES['ios4'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result2=CosFile::uploadFile($_FILES['ios5'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result3=CosFile::uploadFile($_FILES['ios6'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result4=CosFile::uploadFile($_FILES['ios6s'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result=array( 'ios4' => $result1['subUrlName'] , 'ios5' => $result2['subUrlName'] , 'ios6' => $result3['subUrlName'] , 'ios6s' => $result4['subUrlName']);
                        $images=json_encode($result);
                        $input_data = array ( 'images' => $images , 'title' => $data->required('title'), 'operator' => $current_user_info['user_name'], 'action_type' => $data->required('type') , 'skip_url' => $data->required('skip_url') ,'code' => $code , 'platform' => 1);
                    }
                    $banner_id = CBanner::addBanner( $input_data );
                    if ($banner_id) {
                        CSysLog::addLog ( CUserSession::getUserName(), 'ADD', 'Banner' ,$banner_id, json_encode($input_data) );
                        $commit = true;
                    }
            }catch(InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            }finally {
                if ($commit) {
                    $transaction -> commit();
                }else {
                    $transaction -> rollback();
                }
            }
            if ($commit) {
                if (!empty($_FILES['android'])) {
                    CCommon::exitWithSuccess ($this,'android  Banner图添加成功','Console/Banner/banners');
                } else {
                    CCommon::exitWithSuccess ($this,'ios  Banner图添加成功','Console/Banner/banners');
                }
                return;
                
            }
        }
        $this->getView()->assign('package_datas',$package_datas);
        $this->display('add');
    }
    
    public function ModifyAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $package_datas=CPackage::getSearch();
        $img=CBanner::find($data->optional('banner_id'));
        if (!empty($img['images'])) {
            $img['images']=json_decode($img['images'],true);
            $img['code']=json_decode($img['code'],true);
        }
        
        if (Protocol::getMethod() == "POST") {
            $banner_img=CBanner::find($data->required('banner_id'));
            $transaction=Yii::$app->db->beginTransaction();
            $commit=false;
            try{
                
                    if ($_FILES['ios4'] !='' && $_FILES['ios5'] !='' && $_FILES['ios6'] !='' && $_FILES['ios6s'] !='') {
                        if (empty($data->optional('code'))) {
                            $code = '';
                        } else {
                            $code=json_encode($data->optional('code'));
                        }
                        
                        $result1=CosFile::uploadFile($_FILES['ios4'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result2=CosFile::uploadFile($_FILES['ios5'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result3=CosFile::uploadFile($_FILES['ios6'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result4=CosFile::uploadFile($_FILES['ios6s'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result=array( 'ios4' => $result1['subUrlName'] , 'ios5' => $result2['subUrlName'] , 'ios6' => $result3['subUrlName'] , 'ios6s' => $result4['subUrlName']);
                        $images=json_encode($result);
                        $input_data = array ( 'images' => $images , 'title' => $data->required('title'), 'operator' => $current_user_info['user_name'], 'action_type' => $data->required('type') , 'skip_url' => $data->required('skip_url') ,'code' => $code, 'platform' => 1);
                    } else {
                        CCommon::exitWithError ($this,Console\ADMIN\NEED_PARAM,'Console/Banner/banners');
                        return;
                    }
                    $banner_id = CBanner::updateBanner( $input_data, $data->required('banner_id'));
                    if ($banner_id) {
                        CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'Banner' ,$banner_id, json_encode($input_data) );
                        $commit = true;
                    }
            }catch(InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            }finally {
                if ($commit) {
                    $transaction -> commit();
                }else {
                    $transaction -> rollback();
                }
            }
            if ($commit) {
                CCommon::exitWithSuccess ($this,'ios  Banner图修改成功','Console/Banner/banners');
                return;
        
            }
        }
//         var_dump($package_datas);die;
        $this->getView()->assign('img',$img);
        $this->getView()->assign('package_datas',$package_datas);
        $this->display('modify');
    }
    
    public function DetailAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $package_datas=CPackage::getSearch();
        $img=CBanner::find($data->optional('banner_id'));
        if (!empty($img['images'])) {
            $img['images']=json_decode($img['images'],true);
            $img['code']=json_decode($img['code'],true);
        } 
        $this->getView()->assign('img',$img);
        $this->getView()->assign('package_datas',$package_datas);
        $this->display('detail');
    }
    
    public function pushConfigAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        
        $banner_img=CBanner::find($data->required('banner_id'));
        $banner_img['images']=json_decode($banner_img['images'],true);
        $banner_img['code']=json_decode($banner_img['code'],true);
        $banner_img['code']=implode(',',$banner_img['code']);
        
        $platform=array('platform' => 1);
        $package_img=CPackage::findPlatform($platform);
        if (!empty($package_img)) {
            $package_img['code']=json_decode($package_img['code'],true);
            if (isset($package_img['code']['f'])) {
                $type = 1;
            } else {
                $type = 2;
            }
        } else {
            $type='';
            $package_img['code']['q']='';
            $package_img['code']['v']='';
            $package_img['descs']='';
        }
        
        $start_push=array('push' => 2 ,'platform' => 1);
        $start_img=CStartImg::findPush($start_push);
        if (!empty($start_img)) {
            $start_img['images']=json_decode($start_img['images'],true);
        } else {
            $start_img['images']['ios4']='';
            $start_img['images']['ios5']='';
            $start_img['images']['ios6']='';
            $start_img['images']['ios6s']='';
            $start_img['action_type']='';
            $start_img['skip_url']='';
            $start_img['title']='';
            $start_img['duration']='';
            $start_img['can_skip']='';
        }
        
        $app_code=$package_img['code']['q'];
        $app_version=$package_img['code']['v'];
        $app_desc=$package_img['descs'];
        
        $banner_code=$banner_img['code'];
        $banner_url=$banner_img['images']['ios4'];
        $banner_type=$banner_img['action_type'];
        $banner_skip_url=$banner_img['skip_url'];
        $banner_title=$banner_img['title'];
        
        $start_ios4=$start_img['images']['ios4'];
        $start_ios5=$start_img['images']['ios5'];
        $start_ios6=$start_img['images']['ios6'];
        $start_ios6s=$start_img['images']['ios6s'];
        $start_type=$start_img['action_type'];
        $start_skip_url=$start_img['skip_url'];
        $start_title=$start_img['title'];
        $start_duration=$start_img['duration'];
        $start_can_skip=$start_img['can_skip'];
        
        $party_data=CSystem::get('ios_party');
        $travel_data=CSystem::get('ios_travel');
        $wedding_data=CSystem::get('ios_wedding');
        
        $response=CPackage::pushConfigAction($type,$app_code,$app_version,$app_desc,$banner_code,$banner_url,$banner_type,$banner_skip_url,$banner_title,$start_ios4,$start_ios5,$start_ios6,$start_ios6s,$start_type,$start_skip_url,$start_title,$start_duration,$start_can_skip,$party_data,$travel_data,$wedding_data);
        if ($response) {
            $transaction = Yii::$app->db->beginTransaction();
            $commit = false;
            try {
                $input_data = array( 'push' => 2 , 'platform' => 1);
                $push=CBanner::findPush($input_data);
                if ($push) {
                    $datas = array( 'push' => 1 , 'platform' => 1);
                    $id=CBanner::updatePush( $datas , $input_data );
                    if ($id) {
                        $banner_id=CBanner::updateBanner( $input_data , $data->required('banner_id') );
                        if ($banner_id) {
                            CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'Banner' ,$banner_id, json_encode($input_data) );
                            $commit = true;
                        }
                    }
                } else {
                    $img_id=CBanner::updateBanner( $input_data , $data->required('banner_id') );
                    if ($img_id) {
                        CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'Banner' ,$img_id, json_encode($input_data) );
                        $commit = true;
                    }
                }
                
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            } finally {
                if ($commit) {
                    $transaction -> commit();
                } else {
                    $transaction -> rollback();
                }
            }
            if ($commit) {
                if (!empty($banner_img['images'])) {
                     CCommon::exitWithSuccess($this,'Ios Banner推送成功','Console/Banner/banners');
                } else {
                    CCommon::exitWithError($this,'Ios Banner推送失败','Console/Banner/banners');
                }
                return;
            }
           
        } 
    }
    
    public function pushAndroidConfigAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        $img=CStartImg::find($data->required('img_id'));
        $sysinfo_android = [
//         "app_info" => [
//         "type" => 1,
//         "code" => 1,
//         "version" => "1.0",
//         "desc" => "f",
//         ],
    
        "launch_info" => [
        "enable_version" => $img['version'],
        "img_url" => "images/splash/".$img['android'].".jpg",
        "action" => [
        "type" => $img['action_type'],
        "url" => $img['url'],
        "title" => $img['title'],
        ],
        "duration" => "3",
        "can_skip" => 0,
        ],
        ];
        $response['sysinfo_android'] = Push::zk("/moca/spread/subscription/sysinfo.android", json_encode($sysinfo_android));
        if ($response['sysinfo_android']) {
            $transaction = Yii::$app->db->beginTransaction();
            $commit = false;
            try {
                $input_data = array( 'push' => 2 , 'platform' => 0);
                $push=CStartImg::findPush($input_data);
                if ($push) {
                    $datas = array( 'push' => 1 , 'platform' => 0);
                    $id=CStartImg::updatePush( $datas , $input_data );
                    if ($id) {
                        $img_id=CStartImg::updateStartImg( $input_data , $data->required('img_id') );
                        if ($img_id) {
                            CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'Startimg' ,$img_id, json_encode($input_data) );
                            $commit = true;
                        }
                    }
                } else {
                    $img_id=CStartImg::updateStartImg( $input_data , $data->required('img_id') );
                    if ($img_id) {
                        CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'Startimg' ,$img_id, json_encode($input_data) );
                        $commit = true;
                    }
                }
        
            } catch (InvalidArgumentException $e) {
                CAdmin::alert($this, "error", $e->getMessage());
            } finally {
                if ($commit) {
                    $transaction -> commit();
                } else {
                    $transaction -> rollback();
                }
            }
            if ($commit) {
                if (!empty($img['android'])) {
                    CCommon::exitWithSuccess($this,'Android推送成功','Console/Startimg/imgs');
                } else {
                    CCommon::exitWithError($this,'Android推送失败','Console/Startimg/imgs');
                }
                return;
            }
             
        }
    }
    
    private function doReadJsonFile($fileName)
    {
        if (empty($fileName)) {
            return false;
        }
        $jsonFile = APP_PATH . $fileName;
        $template = trim(stripslashes(file_get_contents($jsonFile)));
        $template = preg_replace("/\s/","",$template);
        return $template;
    }
    
    
}