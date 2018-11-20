<?php
use Yaf\Controller_Abstract;
use Yaf\Dispatcher;
class StatController extends Controller_Abstract {
    public function AddActiveAction() 
    {
        CInit::config ( $this );
        $data=Protocol::arguments ();
        $start_time=str_replace('-','',$data->optional('start_date'));
        $end_time=str_replace('-','',$data->optional ('end_date'));
        if ($data->optional('start_date') != '' || $data->optional('end_date') != '') {
            $type="type=0";
            $row_count=CStat::getCountByDate($start_time,$end_time,$type);
        } else {
            $condition="type=0";
            $row_count=CStat::count($condition);
        }
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional('page_no','') < 1 ? 1 : $data->optional('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        //查找type=0的数据
        $adds=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=0');
        $actives=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=1');
//         echo '<pre>';
//         var_dump($actives);die;
        if (!empty($adds)) {
            foreach ($adds as &$v1) {
                foreach (@$actives as &$v2) {
                    if ($v1['stat_date'] == $v2['stat_date']) {
                        $v1['active'] = $v2['data'];
                    }
                }
            }
        }
//         echo '<pre>';
//         var_dump($adds);die;
        // 显示分页栏
        $page_html=CStat::showPager("addActive?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date'),$page_no,Console\ADMIN\PAGE_SIZE,$row_count);
        $this->getView ()->assign ( 'page_no', $page_no );
        $this->getView ()->assign ( 'page_size', Console\ADMIN\PAGE_SIZE );
        $this->getView ()->assign ( 'row_count', $row_count );
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'adds', $adds );
        $this->display ('addActive');
    }
    
    public function PicActivityAction() 
    {
        CInit::config ( $this );
        $data = Protocol::arguments ();
        $start_time = str_replace ( '-', '', $data->optional ( 'start_date' ) );
        $end_time = str_replace ( '-', '', $data->optional ( 'end_date' ) );
        if ($data->optional ( 'start_date' ) != '' || $data->optional ( 'end_date' ) != '' || $data->optional('classify') != '') {
            if ($data->optional('classify') == 1) {
                $row_count = CStat::getCountByDate ($start_time,$end_time,'type=7');
            } else {
                $row_count = CStat::getCountByDate ($start_time,$end_time,'type=2');
            }
            
        } else {
            $condition = array (
                    'type' => 2 
            );
            $row_count = CStat::count ( $condition );
        }
        // START 数据库查询及分页数据
        $page_size = Console\ADMIN\PAGE_SIZE;
        $page_no = $data->optional ( 'page_no', '' ) < 1 ? 1 : $data->optional ( 'page_no', '' );
        $total_page = $row_count % $page_size == 0 ? $row_count / $page_size : ceil ( $row_count / $page_size );
        $total_page = $total_page < 1 ? 1 : $total_page;
        $page_no = $page_no > ($total_page) ? ($total_page) : $page_no;
        $start = ($page_no - 1) * $page_size;
        // END
        $stat_datas = CStat::getDatas ( $start, $page_size, $start_time, $end_time, 'type=2' );
        $adds = CStat::getDatas ( $start, $page_size, $start_time, $end_time, 'type=7' );
        $event_adds = CStat::getDatas ( $start, $page_size, $start_time, $end_time, 'type=3' );
//         echo '<pre>';
//         var_dump($stat_datas);die;
        if (!empty($stat_datas)) {
            foreach ($stat_datas as &$stat_data) {
                foreach ($event_adds as &$event_add) {
                    if ($stat_data['stat_date'] == $event_add['stat_date']) {
                        $stat_data['cea'] = @$event_add['data']['cea'];
                    }
                }
            }
        }
//         echo '<pre>';
//         var_dump($stat_datas);die;
        // 显示分页栏
        $page_html = CStat::showPager ( "picActivity?start_date=" . $data->optional ( 'start_date' ) . "&end_date=" . $data->optional ( 'end_date' )."&classify=".$data->optional('classify'), $page_no, Console\ADMIN\PAGE_SIZE, $row_count );
        $this->getView ()->assign ( 'page_no', $page_no );
        $this->getView ()->assign ( 'page_size', Console\ADMIN\PAGE_SIZE );
        $this->getView ()->assign ( 'row_count', $row_count );
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'stat_datas', $stat_datas );
        $this->getView ()->assign ( 'adds', $adds );
        $this->display ( 'picActivity' );
    }
    
    public function SharerelatedAction()
    {
    if ($_POST) {
            $start_time=str_replace('-','',$_POST['start_date']);
            $end_time=str_replace('-','',$_POST['end_date']);
            $list=CStat::search($start_time,$end_time);
            $sum=0;
            foreach ($list as $v) {
                if ($v['type'] == 4 && $_POST['classify'] == 1) {
                    $sum+=$v['data']['s'];
                } elseif ($v['type'] == 4 && $_POST['classify'] == 2) {
                    $sum+=$v['data']['s'];
                } elseif ($v['type'] == 4 && $_POST['classify'] == 3) {
                    $sum+=$v['data']['s'];
                } elseif ($v['type'] == 4 && $_POST['classify'] == 4) {
                    $sum+=$v['data']['s'];
                } elseif ($v['type'] == 4 && $_POST['classify'] == 5) {
                    $sum+=$v['data']['s'];
                }
        
            }
            echo json_encode($sum);
            return;
        }
        CInit::config ( $this );
        $data = Protocol::arguments ();
        $start_time = str_replace ( '-', '', $data->optional ( 'start_date' ) );
        $end_time = str_replace ( '-', '', $data->optional ( 'end_date' ) );
        if ($data->optional ( 'start_date' ) != '' || $data->optional ( 'end_date' ) != '') {
            $type='type=4';
            $row_count = CStat::getCountByDate ($start_time,$end_time,$type);
        } else {
            $type='type=4';
            $row_count = CStat::count ( $type );
        }
        // START 数据库查询及分页数据
        $page_size = Console\ADMIN\PAGE_SIZE;
        $page_no = $data->optional ( 'page_no', '' ) < 1 ? 1 : $data->optional ( 'page_no', '' );
        $total_page = $row_count % $page_size == 0 ? $row_count / $page_size : ceil ( $row_count / $page_size );
        $total_page = $total_page < 1 ? 1 : $total_page;
        $page_no = $page_no > ($total_page) ? ($total_page) : $page_no;
        $start = ($page_no - 1) * $page_size;
        // END
        $type="type=4";
        $stat_datas = CStat::getDatas ( $start, $page_size, $start_time, $end_time,$type );
        foreach ($stat_datas as &$stat_data) {
            $y=substr($stat_data['stat_date'],0,4);
            $m=substr($stat_data['stat_date'],4,2);
            $d=substr($stat_data['stat_date'],6,2);
            $stat_data['stat_date']=$y.'-'.$m.'-'.$d;
        }
//         $loadedClz = array ();
//         $namePool = array ();
        // 显示分页栏
        $page_html = CStat::showPager ( "sharerelated?start_date=" . $data->optional ( 'start_date' ) . "&end_date=" . $data->optional ( 'end_date' ), $page_no, Console\ADMIN\PAGE_SIZE, $row_count );
        $this->getView ()->assign ( 'page_no', $page_no );
        $this->getView ()->assign ( 'page_size', Console\ADMIN\PAGE_SIZE );
        $this->getView ()->assign ( 'row_count', $row_count );
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( 'stat_datas', $stat_datas );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->display('sharerelated');
    }
    
    public function ApplicationsharingAction()
    {
        CInit::config ( $this );
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $start_time=str_replace('-','',$data->optional('start_date'));
        $end_time=str_replace('-','',$data->optional('end_date'));
        $type='type=3';
        if ($data->optional( 'start_date' ) != '' || $data->optional('end_date') != '' || $data->optional('classify') != '') {
            if ($data->optional('classify') == 1) {
                $row_count=CStat::getCountByDate ($start_time,$end_time,'type=7');
            } else {
                $row_count=CStat::getCountByDate ($start_time,$end_time,$type);
            }
            
        } else {
            $row_count=CStat::count ($type);
        }
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $stat_datas=CStat::getDatas($start,$page_size,$start_time,$end_time,$type);
        $adds=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=7');
        // 显示分页栏
        $page_html=CStat::showPager("applicationsharing?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date')."&classify=".$data->optional('classify'),$page_no,$page_size,$row_count );
        $this->getView ()->assign ( 'page_no', $page_no );
        $this->getView ()->assign ( 'page_size', $page_size );
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'stat_datas', $stat_datas );
        $this->getView ()->assign ( 'adds', $adds );
        $this->display('applicationsharing');
    }
    
    public function KeepAction()
    {
        CInit::config ( $this );
        $data=Protocol::arguments ();
        $start_time=str_replace('-','',$data->optional('start_date'));
        $end_time=str_replace('-','',$data->optional ('end_date'));
        if ($data->optional('start_date') != '' || $data->optional('end_date') != '') {
            $type="type=1";
            $row_count=CStat::getCountByDate($start_time,$end_time,$type);
        } else {
            $condition="type=1";
            $row_count=CStat::count($condition);
        }
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
//         $page_size=25;
        $page_no=$data->optional('page_no','') < 1 ? 1 : $data->optional('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
//         $list1=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=0');
        $list2=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=1');
//         echo '<pre>';
//         var_dump($list2);die;
        $temp=array();
        $imp=array();
            foreach (@$list2 as &$ite) {
                    $d=date("Ymd",strtotime($ite['stat_date']));
                    $one_day = date("Ymd",strtotime("$d +1 day"));
                    $two_day = date("Ymd",strtotime("$d +2 day"));
                    $three_day = date("Ymd",strtotime("$d +3 day"));
                    $four_day = date("Ymd",strtotime("$d +4 day"));
                    $five_day = date("Ymd",strtotime("$d +5 day"));
                    $six_day = date("Ymd",strtotime("$d +6 day"));
                    $seven_day = date("Ymd",strtotime("$d +1 week"));
                    $fifteen_day = date("Ymd",strtotime("$d +15 day"));
                    $thrity_day = date("Ymd",strtotime("$d +30 day"));
                    $thrity_five_day = date("Ymd",strtotime("$d +35 day"));
                    $fourty_day = date("Ymd",strtotime("$d +40 day"));
                    $fourty_five_day = date("Ymd",strtotime("$d +45 day"));
                    $fifty_day = date("Ymd",strtotime("$d +50 day"));
                    $fifty_five_day = date("Ymd",strtotime("$d +55 day"));
                    $sixty_day = date("Ymd",strtotime("$d +60 day"));
                    $total=@array_sum(@$ite['data'][0][0])+@array_sum(@$ite['data'][0][1])+@array_sum(@$ite['data'][0][2])+@array_sum(@$ite['data'][0][3])+@array_sum(@$ite['data'][0][4]);
//                         echo '<pre>';
//                     var_dump($ite);die;
                    if (isset($one_day) && $total != 0) {
                        $imp[1]=((@array_sum(@$ite['data'][1][0])+@array_sum(@$ite['data'][1][1])+@array_sum(@$ite['data'][1][2])+@array_sum(@$ite['data'][1][3])+@array_sum(@$ite['data'][1][4]))/$total)*100;//ios留存率
                        $imp[1]=number_format(round($imp[1],2),2);
                    }
                    if (isset($two_day) && $total != 0) {
                        $imp[2]=((@array_sum(@$ite['data'][2][0])+@array_sum(@$ite['data'][2][1])+@array_sum(@$ite['data'][2][2])+@array_sum(@$ite['data'][2][3])+@array_sum(@$ite['data'][2][4]))/$total)*100;
                        $imp[2]=number_format(round($imp[2],2),2);
                    }
                    if (isset($three_day) && $total != 0) {
                        $imp[3]=((@array_sum(@$ite['data'][3][0])+@array_sum(@$ite['data'][3][1])+@array_sum(@$ite['data'][3][2])+@array_sum(@$ite['data'][3][3])+@array_sum(@$ite['data'][3][4]))/$total)*100;
                        $imp[3]=number_format(round($imp[3],2),2);
                    }
                    if (isset($four_day) && $total != 0) {
                        $imp[4]=((@array_sum(@$ite['data'][4][0])+@array_sum(@$ite['data'][4][1])+@array_sum(@$ite['data'][4][2])+@array_sum(@$ite['data'][4][3])+@array_sum(@$ite['data'][4][4]))/$total)*100;
                        $imp[4]=number_format(round($imp[4],2),2);
                    }
                    if (isset($five_day) && $total != 0) {
                        $imp[5]=((@array_sum(@$ite['data'][5][0])+@array_sum(@$ite['data'][5][1])+@array_sum(@$ite['data'][5][2])+@array_sum(@$ite['data'][5][3])+@array_sum(@$ite['data'][5][4]))/$total)*100;
                        $imp[5]=number_format(round($imp[5],2),2);
                    }
                    if (isset($six_day) && $total != 0) {
                        $imp[6]=((@array_sum(@$ite['data'][6][0])+@array_sum(@$ite['data'][6][1])+@array_sum(@$ite['data'][6][2])+@array_sum(@$ite['data'][6][3])+@array_sum(@$ite['data'][6][4]))/$total)*100;
                        $imp[6]=number_format(round($imp[6],2),2);
                    }
                    if (isset($seven_day) && $total != 0) {
                        $imp[7]=((@array_sum(@$ite['data'][7][0])+@array_sum(@$ite['data'][7][1])+@array_sum(@$ite['data'][7][2])+@array_sum(@$ite['data'][7][3])+@array_sum(@$ite['data'][7][4]))/$total)*100;
                        $imp[7]=number_format(round($imp[7],2),2);
                    }
                    if (isset($fifteen_day) && $total != 0) {
                        $imp[8]=((@array_sum($ite['data'][15][0])+@array_sum($ite['data'][15][1])+@array_sum($ite['data'][15][2])+@array_sum($ite['data'][15][3])+@array_sum($ite['data'][15][4]))/$total)*100;
                        $imp[8]=number_format(round($imp[8],2),2);
                    }
                    if (isset($thrity_day) && $total != 0) {
                        $imp[9]=((@array_sum($ite['data'][30][0])+@array_sum($ite['data'][30][1])+@array_sum($ite['data'][30][2])+@array_sum($ite['data'][30][3])+@array_sum($ite['data'][30][4]))/$total)*100;
                        $imp[9]=number_format($imp[9],2);
                    }
                    if (isset($thrity_five_day) && $total != 0) {
                        $imp[10]=((@array_sum($ite['data'][35][0])+@array_sum($ite['data'][35][1])+@array_sum($ite['data'][35][2])+@array_sum($ite['data'][35][3])+@array_sum($ite['data'][35][4]))/$total)*100;
                        $imp[10]=number_format($imp[10],2);
                    }
                    if (isset($fourty_day) && $total != 0) {
                        $imp[11]=((@array_sum($ite['data'][40][0])+@array_sum($ite['data'][40][1])+@array_sum($ite['data'][40][2])+@array_sum($ite['data'][40][3])+@array_sum($ite['data'][40][4]))/$total)*100;
                        $imp[11]=number_format($imp[11],2);
                    }
                    if (isset($fourty_five_day) && $total != 0) {
                        $imp[12]=((@array_sum($ite['data'][45][0])+@array_sum($ite['data'][45][1])+@array_sum($ite['data'][45][2])+@array_sum($ite['data'][45][3])+@array_sum($ite['data'][45][4]))/$total)*100;
                        $imp[12]=number_format($imp[12],2);
                    }
                    if (isset($fifty_day) && $total != 0) {
                        $imp[13]=((@array_sum($ite['data'][50][0])+@array_sum($ite['data'][50][1])+@array_sum($ite['data'][50][2])+@array_sum($ite['data'][50][3])+@array_sum($ite['data'][50][4]))/$total)*100;
                        $imp[13]=number_format($imp[13],2);
                    }
                    if (isset($fifty_five_day) && $total != 0) {
                        $imp[14]=((@array_sum($ite['data'][55][0])+@array_sum($ite['data'][55][1])+@array_sum($ite['data'][55][2])+@array_sum($ite['data'][55][3])+@array_sum($ite['data'][55][4]))/$total)*100;
                        $imp[14]=number_format($imp[14],2);
                    }
                    if (isset($sixty_day) && $total != 0) {
                        $imp[15]=((@array_sum($ite['data'][60][0])+@array_sum($ite['data'][60][1])+@array_sum($ite['data'][60][2])+@array_sum($ite['data'][60][3])+@array_sum($ite['data'][60][4]))/$total)*100;
                        $imp[15]=number_format($imp[15],2);
                    }
                    if ($total ==0) {
                        $temp=array();
                    } else {
                        $temp[]=[
                                'stat_date' => $ite['stat_date'],
                                0 => @$total,
                                1 => @$imp[1],
                                2 => @$imp[2],
                                3 => @$imp[3],
                                4 => @$imp[4],
                                5 => @$imp[5],
                                6 => @$imp[6],
                                7 => @$imp[7],
                                8 => @$imp[8],
                                9 => @$imp[9],
                                10 => @$imp[10],
                                11 => @$imp[11],
                                12 => @$imp[12],
                                13 => @$imp[13],
                                14 => @$imp[14],
                                15 => @$imp[15],
                        ];
                    }
            }
            
//         echo '<pre>';
//         var_dump($temp);die; 
        // 显示分页栏
        $page_html=CStat::showPager("keep?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date'),$page_no,Console\ADMIN\PAGE_SIZE,$row_count);
        $this->getView ()->assign ( 'page_no', $page_no );
        $this->getView ()->assign ( 'page_size', Console\ADMIN\PAGE_SIZE );
        $this->getView ()->assign ( 'row_count', $row_count );
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'temp', $temp );
        $this->display('keep');
    }
    
    public function InviteAction()
    {
        CInit::config ( $this );
        $data = Protocol::arguments();
        $start_time=str_replace('-','',$data->optional('start_date'));
        $end_time=str_replace('-','',$data->optional('end_date'));
        $type='type=3';
        if ($data->optional( 'start_date' ) != '' || $data->optional('end_date') != '' || $data->optional('classify') != '') {
            if ($data->optional('classify') == 1) {
                $row_count=CStat::getCountByDate ($start_time,$end_time,'type=7');
            } else {
                $row_count=CStat::getCountByDate ($start_time,$end_time,$type);
            }
            
        } else {
            $row_count=CStat::count ($type);
        }
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $stat_datas=CStat::getDatas($start,$page_size,$start_time,$end_time,$type);
        $h5_datas=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=0');
        $h5_uploads=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=6');
        $adds=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=7');//新增用户
        $invited_actives=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=2');
        //遍历数组，查找相关信息
        foreach (@$stat_datas as &$item) {
//             $y=substr($item['stat_date'],0,4);
//             $m=substr($item['stat_date'],4,2);
//             $d=substr($item['stat_date'],6,2);
//             $date=$y.' '.$m.' '.$d;
//             $item['stat_date']=str_replace(' ','-',$date);
               foreach (@$h5_datas as $h5_data) {
                   if ($h5_data['stat_date'] == $item['stat_date']) {
                        $item['h5']=$h5_data['data']['p'][2]+$h5_data['data']['p'][3]+$h5_data['data']['p'][4];
                   }
               }
               foreach (@$h5_uploads as $h5_upload) {
                   if ($h5_upload['stat_date'] == $item['stat_date']) {
                       $item['u_pv']=@$h5_upload['data']['u']['pv'];
                       $item['u_uv']=@$h5_upload['data']['u']['uv'];
                   }
               }
               foreach (@$invited_actives as $invited_active) {
                   if ($invited_active['stat_date'] == $item['stat_date']) {
                       $item['invited_actives']=@$invited_active['data']['eu']-@array_sum(@$invited_active['data']['s']['p']);
                   }
               }
               
        }
//         echo '<pre>';
//         var_dump($stat_datas);die;
        // 显示分页栏
        $page_html=CStat::showPager("invite?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date')."&classify=".$data->optional('classify'),$page_no,$page_size,$row_count );
        $this->getView ()->assign ( 'page_no', $page_no );
        $this->getView ()->assign ( 'page_size', $page_size );
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'stat_datas', $stat_datas );
        $this->getView ()->assign ( 'adds', $adds );
        $this->display('invite');
    }
    
    public function effectiveDetailAction()
    {
        CInit::config ( $this );
        $current_user_info = CUserSession::getSessionInfo();
        $data = Protocol::arguments();
        $start_time=str_replace('-','',$data->optional('start_date'));
        $end_time=str_replace('-','',$data->optional('end_date'));
        if ($data->optional( 'start_date' ) != '' || $data->optional('end_date') != '') {
            $row_count=CStat::getCountByDate ($start_time,$end_time,'type=11');
        } else {
            $row_count=CStat::count ('type=11');
        }
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional( 'page_no', '' ) < 1 ? 1 : $data->optional ('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil ($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        $stat_datas=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=11');
//         echo '<pre>';
//         var_dump($stat_datas);die;
        //遍历数组，查找相关信息
        $temp=array();
        foreach (@$stat_datas as $k1 => $v1) {
            $imp=array();
            $sum=0;
            if ($data->optional('classify') == 0 || $data->optional('classify') == '') {
                foreach (@$v1['data']['sum'] as $k2 => $v2) {
                    $imp[]=$k2.'人'.':'.count($v2);
                    $sum=$sum+count($v2);
                }
                $temp[]=array(
                            'id' => $v1['id'],
                            'stat_date' => $v1['stat_date'],
                            'sum' => $sum,
                            'count' => implode(',',$imp)
                            );
            } else {
                foreach (@$v1['data']['new'] as $k2 => $v2) {
                    $imp[]=$k2.'人'.':'.count($v2);
                    $sum=$sum+count($v2);
                }
                $temp[]=array(
                    'id' => $v1['id'],
                    'stat_date' => $v1['stat_date'],
                    'sum' => $sum,
                    'count' => implode(',',$imp)
                );
            }
        }
//         echo '<pre>';
//         var_dump($temp);die;
        // 显示分页栏
        $page_html=CStat::showPager("effectiveDetail?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date'),$page_no,$page_size,$row_count );
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'temp', $temp );
        $this->display('effectiveDetail');
    }
    
    public function detailAction()
    {
        CInit::config ( $this );
        $data = Protocol::arguments();
        $stat_datas=CStat::find($data->optional('stat_date'),'type=11');
//         echo '<pre>';
//         var_dump($stat_datas);die;
        $imp=array();
        $tmp=array();
        if ($data->optional('classify') == 0 || $data->optional('classify') == '') {
            foreach (@$stat_datas['data']['sum'] as $k1=>$v1) {
                $temp=array();
                $tmp[]=array('value' => $v1,'count' => count($v1),'key' => $k1);
                //
            }
        } else {
            foreach (@$stat_datas['data']['new'] as $k1=>$v1) {
                $temp=array();
                $tmp[]=array('value' => $v1,'count' => count($v1),'key' => $k1);
                //
            }
        }
        $imp=array('stat_date' => @$stat_datas['stat_date'],$tmp);
//         echo '<pre>';
//         var_dump($imp);die;
        // 显示分页栏
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'imp', $imp );
        $this->display('detail');
    }
    
    public function commentLikeAction()
    {
        CInit::config ( $this );
        $data = Protocol::arguments();
        $start_time=str_replace('-','',$data->optional('start_date'));
        $end_time=str_replace('-','',$data->optional ('end_date'));
        if ($data->optional('start_date') != '' || $data->optional('end_date') != '') {
            $type="type=12";
            $row_count=CStat::getCountByDate($start_time,$end_time,$type);
        } else {
            $condition="type=12";
            $row_count=CStat::count($condition);
        }
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional('page_no','') < 1 ? 1 : $data->optional('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        //查找type=12的数据
        $commentLikes=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=12');
        // 显示分页栏
        $page_html=CStat::showPager("commentLike?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date'),$page_no,Console\ADMIN\PAGE_SIZE,$row_count);
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'commentLikes', $commentLikes );
        $this->display('commentLike');
    }
    
    public function groupDataAction()
    {
        CInit::config ( $this );
        $data = Protocol::arguments();
        $start_time=str_replace('-','',$data->optional('start_date'));
        $end_time=str_replace('-','',$data->optional ('end_date'));
        if ($data->optional('start_date') != '' || $data->optional('end_date') != '') {
            $type="type=13";
            $row_count=CStat::getCountByDate($start_time,$end_time,$type);
        } else {
            $condition="type=13";
            $row_count=CStat::count($condition);
        }
        // START 数据库查询及分页数据
        $page_size=Console\ADMIN\PAGE_SIZE;
        $page_no=$data->optional('page_no','') < 1 ? 1 : $data->optional('page_no','');
        $total_page=$row_count % $page_size == 0 ? $row_count / $page_size : ceil($row_count / $page_size );
        $total_page=$total_page < 1 ? 1 : $total_page;
        $page_no=$page_no > ($total_page) ? ($total_page) : $page_no;
        $start=($page_no - 1) * $page_size;
        // END
        //查找type=13的数据
        $groupDatas=CStat::getDatas($start,$page_size,$start_time,$end_time,'type=13');
//         echo '<pre>';
//         var_dump($groupDatas);die;
        // 显示分页栏
        $page_html=CStat::showPager("groupData?start_date=".$data->optional('start_date')."&end_date=".$data->optional('end_date'),$page_no,Console\ADMIN\PAGE_SIZE,$row_count);
        $this->getView ()->assign ( 'page_html', $page_html );
        $this->getView ()->assign ( '_GET', $_GET );
        $this->getView ()->assign ( 'class_options', yii::$app->params ['consloe_class_for_log'] );
        $this->getView ()->assign ( 'groupDatas', $groupDatas );
        $this->display('groupData');
    }
}