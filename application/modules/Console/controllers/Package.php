<?php
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
class PackageController extends Controller_Abstract
{
    const IOS_PLATFORM = 1;
    const ANDROID_PLATFORM = 0;
    const C_PACKAGE = 'c_package';
    const C_PACKAGE_UPGRADE = 'c_package_upgrade';
    
    public function ListAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data=Protocol::arguments();
        $row_count=CPackage::count();
        $commit=false;
        if ($data->optional('method', '') == 'del' && ! empty ( $data->required('package_id') )) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                    $package = CPackage::getPackageById($data->required('package_id'));
                    $result = CPackage::delPackage($data->required('package_id'));
                    if ($result) {
                        CSysLog::addLog (CUserSession::getUserName(), 'DELETE', 'Package', $data->required('package_id'), json_encode($package));
                        $commit = true;
                    } else {
                        CAdmin::alert($this, "error");
                    }
                } catch (InvalidArgumentException $e) {
                    CAdmin::alert($this, "error", $e->getMessage());
                } finally {
                    if ($commit) {
                        $transaction->commit();
                    } else {
                        $transaction->rollback();
                    }
                }
                if ($commit) {
                    CCommon::exitWithSuccess ($this, '已将安装包删除','Console/Package/list');
                    return;
                }
        }
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $package_datas=CPackage::getDatas($start,$page_size);
        $page_html=CPagination::showPager("list",$page_no,$page_size,$row_count);
        //追加操作的确认层
        $confirm_html=CAdmin::renderJsConfirm("icon-pause,icon-play,icon-remove");
        $this->getView()->assign('page_no',$page_no);
        $this->getView()->assign('page_html',$page_html);
        $this->getView()->assign('package_datas',$package_datas);
        $this->getView()->assign('osadmin_action_confirm',$confirm_html);
        $this->display('list');
    }
    
    public function AddAction()
    {
        CInit::config($this);
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        if (Protocol::getMethod() == "POST") {
            if ($data->optional('platform') == 0) {
                    if (strrchr($_FILES['file_name']['name'],'.') == '.apk') {
//                         $uploaddir=$_SERVER['DOCUMENT_ROOT']."/apk/";
//                         $uploaddir.=$_FILES['file_name']['name'];
                            $transaction=Yii::$app->db->beginTransaction();
                            $commit=false;
                            try{
                                $exist=CPackage::getPackageByName($_FILES['file_name']['name']);
                                if($exist){
                                    throw new InvalidArgumentException(Console\ADMIN\NAME_CONFLICT);
                                }else if($_FILES['file_name']['name']=="" || $data->required('version')=="" || $data->required('description') =="" || $data->required('code') ==""){
                                    throw new InvalidArgumentException(Console\ADMIN\NAME_CONFLICT);
                                }else{
                                    $size=$_FILES['file_name']['size']/(1024*1024);
                                    $sizef=number_format($size,1);
                                    $android=CosFile::uploadFile($_FILES['file_name'], 0, CosFile::CATEGORY_PACKAGE);
//                                     var_dump(urldecode($android['url']));die;
                                    if ($android) {
                                        $input_data = array ('file_name' => $_FILES['file_name']['name'], 'version' => ( $data->required('version') ), 'description' => $data->required('description'), 'operator' => $current_user_info['user_name'] , 'code' => $data->required('code') , 'package_size' => $size , 'platform' => 0);
                                        $package_id = CPackage::addPackage ( $input_data );
                                        if ($package_id) {
                                            CSysLog::addLog ( CUserSession::getUserName(), 'ADD', 'Package' ,$package_id, json_encode($input_data) );
                                            $commit = true;
                                        }
                                    }
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
                                CCommon::exitWithSuccess ($this,'android包添加成功','Console/Package/list');
                                return;
                            } else {
                                CCommon::exitWithError ($this,'android包添加失败','Console/Package/list');
                                return;
                            }
                    } else {
                        CCommon::exitWithError ($this,'您添加的安装包不符合规则','Console/Package/list');
                        return;
                    }
            } else {
                if ($data->optional('platform') == 1) {
                    $transaction=Yii::$app->db->beginTransaction();
                    $commit=false;
                    try{
                        $input_data = array ('version' => ( $data->required('version') ), 'description' => $data->required('description'), 'operator' => $current_user_info['user_name'] , 'code' => $data->required('code') , 'platform' => 1);
                        $package_id = CPackage::addPackage ( $input_data );
                        if ($package_id) {
                            CSysLog::addLog ( CUserSession::getUserName(), 'ADD', 'Package' ,$package_id, json_encode($input_data) );
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
                        CCommon::exitWithSuccess ($this,'ios添加成功','Console/Package/list');
                        return;
                    }
                } else {
                    CCommon::exitWithError ($this,'ios添加失败','Console/Package/list');
                    return;
                }
            }
            
        }
        $this->display('add');
    }
    
    public function upgradeAction()
    {
        CInit::config($this); 
        $data = Protocol::arguments();
        $transaction=Yii::$app->db->beginTransaction();
        $commit=false;
        try{
            if (Protocol::getMethod() == "POST") {
                $functionData['code'] =  CPackage::doHandleCodeByList($data->optional("code_ids"), CPackage::doHandlePackageByType($data->required("platform")), $data->required("platform"));
                $functionData['skip_url'] = $data->required("skip_url");
                $functionData['descs'] = $data->optional("descs");
                
                if (CPackage::doHandleDbByType($data, $functionData)) {
                    CSysLog::addLog ( CUserSession::getUserName(), '版本升级推送', 'package_upgrade', $data->required('platform'), json_encode($functionData) );
                    $commit = true;
                }
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
        if ($commit && $data->optional("platform") == 0) {
            $response = $this -> pushAndroidConfigAction();
			if ($response) {
				CCommon::exitWithSuccess ($this,'android安装包升级推送成功','Console/Package/upgrade');
			}
            return;
        } elseif ($commit && $data->optional("platform") == 1) {
            $response = $this -> pushConfigAction();
			if ($response) {
				CCommon::exitWithSuccess ($this,'ios安装包升级推送成功','Console/Package/upgrade');
			}
            return;
        }
        
        $this->getView()->assign('newsData', CPackage::getNewsDataByDb());
        $this->getView()->assign('iosList', CPackage::doHandlePackageByType(self::IOS_PLATFORM));
        $this->getView()->assign('andList', CPackage::doHandlePackageByType(self::ANDROID_PLATFORM));
        $this->display("upgrade");
    }
    
	public function readPictureAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
		$transaction=Yii::$app->db->beginTransaction();
        $commit=false;
		try{
			if (Protocol::getMethod() == "POST") {
				if ($data -> optional('platform') == 1) {
					$functionData['message']=CSystem::set('ios_message',$data->required('message'));
					$functionData['enable']=CSystem::set('ios_enable',$data->optional('enable'));
					$message_data=CSystem::get('ios_message');
					$enable_data=CSystem::get('ios_enable');
					
				} else {
					$functionData['message']=CSystem::set('message',$data->required('message'));
					$functionData['enable']=CSystem::set('enable',$data->optional('enable'));
					$message_data=CSystem::get('message');
					$enable_data=CSystem::get('enable');
					
				}
				if (!empty($functionData)) {
					CSysLog::addLog ( CUserSession::getUserName(), '读取照片推送', 'Package', $data->optional('platform'), json_encode($functionData) );
					$commit = true;
				}
				
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
		
		if ($commit && $data->optional("platform") == 1) {
            $response = CPackage::pushCfgAndroidConfigAction($message_data,$enable_data);
			if ($response ) {
				CCommon::exitWithSuccess ($this,'ios读取照片推送成功','Console/Package/readPicture');
			}
			return;
        } elseif ($commit && $data->optional("platform") == 0) {
            $response = CPackage::pushCfgAndroidConfigAction($message_data,$enable_data);
			if ($response ) {
				CCommon::exitWithSuccess ($this,'android读取照片推送成功','Console/Package/readPicture');
			}
			return;
        }
        $this->display("readPicture");
    }
	
    public function pushConfigAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
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
		
		$message_data=CSystem::get('ios_message');
		$enable_data=CSystem::get('ios_enable');
        
        $response=CPackage::pushConfigAction($type,$app_code,$app_version,$app_desc,$banner_code,$banner_url,$banner_type,$banner_skip_url,$banner_title,$start_ios4,$start_ios5,$start_ios6,$start_ios6s,$start_type,$start_skip_url,$start_title,$start_duration,$start_can_skip,$party_data,$travel_data,$wedding_data,$message_data,$enable_data);
		return $response;
    }
    
    public function pushAndroidConfigAction()
    {
        CInit::config($this);
        $data = Protocol::arguments();
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
//         var_dump($package_img);die;
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
        
		$message_data=CSystem::get('message');
		$enable_data=CSystem::get('enable');
		
        $response=CPackage::pushAndroidConfigAction($type,$app_code,$app_version,$app_desc,$app_url,$start_code,$start_android,$start_type,$start_skip_url,$start_title,$start_duration,$start_can_skip,$party_data,$travel_data,$wedding_data,$message_data,$enable_data);
        return $response;
        
    }
	
}