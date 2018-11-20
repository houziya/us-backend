<?php 
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
class StartimgController extends Controller_Abstract
{
    public function ImgsAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data=Protocol::arguments();
        $row_count=CStartImg::count();
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $startimgs=CStartImg::getDatas($start,$page_size);
        foreach ($startimgs as &$startimg) {
                $startimg['images']=json_decode($startimg['images'],true);
                $startimg['code']=json_decode($startimg['code'],true);
                $startimg['code']=implode(',',$startimg['code']);
        }
//         var_dump($startimgs);die;
        $page_html=CPagination::showPager("imgs",$page_no,$page_size,$row_count);
        //追加操作的确认层
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign('startimgs',$startimgs);
        $this->display('imgs');
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
                        
                        if (empty($data->optional('duration'))) {
                            $duration = 3;
                        } else {
                            $duration = $data->optional('duration');
                        }
                        $result=CosFile::uploadFile($_FILES['android'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $android=array( 'android' => $result['subUrlName']);
                        $images=json_encode($android);
                        $input_data = array ('images' => $images, 'title' => $data->required('title'),  'operator' => $current_user_info['user_name'], 'action_type' => $data->required('type') ,'skip_url' => $data->required('skip_url') ,'code' => $code, 'duration' => $duration , 'can_skip' => $data->optional('can_skip'), 'platform' => 0);
                    } else {
                        if (empty($data->optional('code'))) {
                            $code = '';
                        } else {
                            $code=json_encode($data->optional('code'));
                        }
                        
                        if (empty($data->optional('duration'))) {
                            $duration = 3;
                        } else {
                            $duration = $data->optional('duration');
                        }
                        $result1=CosFile::uploadFile($_FILES['ios4'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result2=CosFile::uploadFile($_FILES['ios5'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result3=CosFile::uploadFile($_FILES['ios6'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result4=CosFile::uploadFile($_FILES['ios6s'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result=array( 'ios4' => $result1['subUrlName'] , 'ios5' => $result2['subUrlName'] , 'ios6' => $result3['subUrlName'] , 'ios6s' => $result4['subUrlName']);
                        $images=json_encode($result);
                        $input_data = array ( 'images' => $images , 'title' => $data->required('title'), 'operator' => $current_user_info['user_name'], 'action_type' => $data->required('type') , 'skip_url' => $data->required('skip_url') ,'code' => $code, 'duration' => $duration , 'can_skip' => $data->optional('can_skip'),'platform' => 1);
                    }
                    $startImg_id = CStartImg::addStartImg( $input_data );
                    if ($startImg_id) {
                        CSysLog::addLog ( CUserSession::getUserName(), 'ADD', 'Startimg' ,$startImg_id, json_encode($input_data) );
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
                    CCommon::exitWithSuccess ($this,'android启动图添加成功','Console/Startimg/imgs');
                } else {
                    CCommon::exitWithSuccess ($this,'ios启动图添加成功','Console/Startimg/imgs');
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
        $img=CStartImg::find($data->optional('img_id'));
        if (!empty($img['images'])) {
            $img['images']=json_decode($img['images'],true);
            $img['code']=json_decode($img['code'],true);
        }
        
        if (Protocol::getMethod() == "POST") {
            $start_img=CStartImg::find($data->optional('img_id'));
            $transaction=Yii::$app->db->beginTransaction();
            $commit=false;
            try{
                    if (!empty($_FILES['android'])) {
                        if (empty($data->optional('code'))) {
                            $code = '';
                        } else {
                            $code=json_encode($data->optional('code'));
                        }
                        
                        if (empty($data->optional('duration'))) {
                            $duration = 3;
                        } else {
                            $duration = $data->optional('duration');
                        }
                        
                        $result=CosFile::uploadFile($_FILES['android'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $android=array( 'android' => $result['subUrlName']);
                        $images=json_encode($android);
                        $input_data = array ('images' => $images, 'title' => $data->required('title'),  'operator' => $current_user_info['user_name'], 'action_type' => $data->required('type') ,'skip_url' => $data->required('skip_url') ,'code' => $code, 'duration' => $duration , 'can_skip' => $data->optional('can_skip'), 'platform' => 0);
                        $search_datas=CStartImg::updateSearch($data->required('img_id'));
                    } else {
                        if (empty($data->optional('code'))) {
                            $code = '';
                        } else {
                            $code=json_encode($data->optional('code'));
                        }
                        
                        if (empty($data->optional('duration'))) {
                            $duration = 3;
                        } else {
                            $duration = $data->optional('duration');
                        }
                        $result1=CosFile::uploadFile($_FILES['ios4'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result2=CosFile::uploadFile($_FILES['ios5'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result3=CosFile::uploadFile($_FILES['ios6'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result4=CosFile::uploadFile($_FILES['ios6s'], $current_user_info['user_id'], CosFile::CATEGORY_STARTED, 0, 0, 0, 0);
                        $result=array( 'ios4' => $result1['subUrlName'] , 'ios5' => $result2['subUrlName'] , 'ios6' => $result3['subUrlName'] , 'ios6s' => $result4['subUrlName']);
                        $images=json_encode($result);
                        $input_data = array ( 'images' => $images , 'title' => $data->required('title'), 'operator' => $current_user_info['user_name'], 'action_type' => $data->required('type') , 'skip_url' => $data->required('skip_url') ,'code' => $code, 'duration' => $duration , 'can_skip' => $data->optional('can_skip'),'platform' => 1);
                        $search_datas=CStartImg::updateSearch($data->required('img_id'));
                    }
                    
                    $startImg_id = CStartImg::updateStartImg( $input_data, $data->required('img_id'));
                    if ($startImg_id) {
                        CSysLog::addLog ( CUserSession::getUserName(), 'MODIFY', 'Startimg' ,$startImg_id, json_encode($input_data) );
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
                    CCommon::exitWithSuccess ($this,'android启动图修改成功','Console/Startimg/imgs');
                } else {
                    CCommon::exitWithSuccess ($this,'ios启动图修改成功','Console/Startimg/imgs');
                }
                return;
        
            }
        }
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
        $img=CStartImg::find($data->optional('img_id'));
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
        $start_img=CStartImg::find($data->required('img_id'));
        $start_img['images']=json_decode($start_img['images'],true);
        
        $banner_push=array('push' => 2);
        $banner_img=CBanner::findPush($banner_push);
        if (!empty($banner_img)) {
            $banner_img['images']=json_decode($banner_img['images'],true);
            $banner_img['code']=json_decode($banner_img['code'],true);
            $banner_img['code']=implode(',',$banner_img['code']);
        } else {
            $banner_img['code']='';
            $banner_img['images']['ios4']='';
            $banner_img['action_type']='';
            $banner_img['skip_url']='';
            $banner_img['title']='';
        }
        
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
            $package_img['skip_url']='';
        }
        
        $app_code=$package_img['code']['q'];
        $app_version=$package_img['code']['v'];
        $app_desc=$package_img['descs'];
        $app_url=$package_img['skip_url'];
        
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
                $push=CStartImg::findPush($input_data);
                if ($push) {
                    $datas = array( 'push' => 1 , 'platform' => 1);
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
                if (!empty($start_img['images'])) {
                     CCommon::exitWithSuccess($this,'Ios启动图推送成功','Console/Startimg/imgs');
                } else {
                    CCommon::exitWithError($this,'Ios启动图推送失败','Console/Startimg/imgs');
                }
                return;
            }
           
        } 
    }
    
    public function pushAndroidConfigAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        $start_img=CStartImg::find($data->required('img_id'));
        $start_img['images']=json_decode($start_img['images'],true);
        $start_img['code']=json_decode($start_img['code'],true);
        $platform=array('platform' => 1);
        $package_img=CPackage::findPlatform($platform);
        if (!empty($package_img)) {
            $package_img['code']=json_decode($package_img['code'],true);
            if (isset($package_img['code']['f'])) {
                $type = 2;
            } else {
                $type = 1;
            }
        } else {
            $type='';
            $package_img['code']['q']='';
            $package_img['code']['v']='';
            $package_img['descs']='';
            $package_img['skip_url']='';
        }
        
        
        $app_code=$package_img['code']['q'];
        $app_version=$package_img['code']['v'];
        $app_desc=$package_img['descs'];
        $app_url=$package_img['skip_url'];
        
        $start_code=$start_img['code'];
        $start_android=$start_img['images']['android'];
        $start_type=$start_img['action_type'];
        $start_skip_url=$start_img['skip_url'];
        $start_title=$start_img['title'];
        $start_duration=$start_img['duration'];
        $start_can_skip=$start_img['can_skip'];
        
        $party_data=CSystem::get('party');
        $travel_data=CSystem::get('travel');
        $wedding_data=CSystem::get('wedding');
        
        $response=CPackage::pushAndroidConfigAction($type,$app_code,$app_version,$app_desc,$app_url,$start_code,$start_android,$start_type,$start_skip_url,$start_title,$start_duration,$start_can_skip,$party_data,$travel_data,$wedding_data);
        
        if ($response) {
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
                if (!empty($start_img['images'])) {
                     CCommon::exitWithSuccess($this,'Android启动图推送成功','Console/Startimg/imgs');
                } else {
                    CCommon::exitWithError($this,'Android启动图推送失败','Console/Startimg/imgs');
                }
                return;
            }
           
        } 
    }
    
    public function pushEventAddressAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            if ($data->optional('party')) {
                $value1=CSystem::set('ios_party',$data->optional('party'));
            }
            if ($data->optional('travel')) {
                $value2=CSystem::set('ios_travel',$data->optional('travel'));
            }
            if ($data->optional('wedding')) {
                $value3=CSystem::set('ios_wedding',$data->optional('wedding'));
            }
            
            $party_data=CSystem::get('ios_party');
            $travel_data=CSystem::get('ios_travel');
            $wedding_data=CSystem::get('ios_wedding');
            
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
            
            $banner_push=array('push' => 2);
            $banner_img=CBanner::findPush($banner_push);
            if (!empty($banner_img)) {
                $banner_img['images']=json_decode($banner_img['images'],true);
                $banner_img['code']=json_decode($banner_img['code'],true);
                $banner_img['code']=implode(',',$banner_img['code']);
            } else {
                $banner_img['code']='';
                $banner_img['images']['ios4']='';
                $banner_img['action_type']='';
                $banner_img['skip_url']='';
                $banner_img['title']='';
            }
            
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
                $package_img['skip_url']='';
            }
            
            $app_code=$package_img['code']['q'];
            $app_version=$package_img['code']['v'];
            $app_desc=$package_img['descs'];
            $app_url=$package_img['skip_url'];
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
            
            $response=CPackage::pushConfigAction($type,$app_code,$app_version,$app_desc,$banner_code,$banner_url,$banner_type,$banner_skip_url,$banner_title,$start_ios4,$start_ios5,$start_ios6,$start_ios6s,$start_type,$start_skip_url,$start_title,$start_duration,$start_can_skip,$party_data,$travel_data,$wedding_data);
            if ($response) {
                CCommon::exitWithSuccess($this,'IOS活动地址推送成功','Console/Startimg/pushEventAddress');
                return;
            } else {
                CCommon::exitWithError($this,'IOS活动地址推送成功','Console/Startimg/pushEventAddress');
                return;
            }
        }
        $this -> display('pushEventAddress');
    }
    
    public function pushAndroidEventAddressAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
        if ($data->optional('party')) {
            $value1=CSystem::set('party',$data->optional('party'));
        }
        if ($data->optional('travel')) {
            $value2=CSystem::set('travel',$data->optional('travel'));
        }
        if ($data->optional('wedding')) {
            $value3=CSystem::set('wedding',$data->optional('wedding'));
        }
        
        $party_data=CSystem::get('party');
        $travel_data=CSystem::get('travel');
        $wedding_data=CSystem::get('wedding');
		
        $start_push=array('push' => 2 ,'platform' => 0);
        $start_img=CStartImg::findPush($start_push);
        if (!empty($start_img)) {
            $start_img['images']=json_decode($start_img['images'],true);
            $start_img['code']=json_decode($start_img['code'],true);
        } else {
            $start_img['images']['android']='';
            $start_img['action_type']='';
            $start_img['skip_url']='';
            $start_img['title']='';
            $start_img['duration']='';
            $start_img['can_skip']='';
        }
        
        $platform=array('platform' => 0);
        $package_img=CPackage::findPlatform($platform);
        if (!empty($package_img)) {
            $package_img['code']=json_decode($package_img['code'],true);
            if (isset($package_img['code']['f'])) {
                $type = 2;
            } else {
                $type = 1; 
            }
        } else {
            $type='';
            $package_img['code']['q']='';
            $package_img['code']['v']='';
            $package_img['descs']='';
            $package_img['skip_url']='';
        }
    
    
        $app_code=$package_img['code']['q'];
        $app_version=$package_img['code']['v'];
        $app_desc=$package_img['descs'];
        $app_url=$package_img['skip_url'];
        $start_code=$start_img['code'];
        $start_android=$start_img['images']['android'];
        $start_type=$start_img['action_type'];
        $start_skip_url=$start_img['skip_url'];
        $start_title=$start_img['title'];
        $start_duration=$start_img['duration'];
        $start_can_skip=$start_img['can_skip'];
    
        $response=CPackage::pushAndroidConfigAction($type,$app_code,$app_version,$app_desc,$app_url,$start_code,$start_android,$start_type,$start_skip_url,$start_title,$start_duration,$start_can_skip,$party_data,$travel_data,$wedding_data);
    
        if ($response) {
            CCommon::exitWithSuccess($this,'Android活动地址推送成功','Console/Startimg/pushEventAddress');
            return;
        } else {
            CCommon::exitWithError($this,'Android活动地址推送成功','Console/Startimg/pushEventAddress');
            return;
        }
    }
}