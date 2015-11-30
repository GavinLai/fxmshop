<?php
    header("Content-Type:text/html;charset=utf-8");
    error_reporting( E_ERROR | E_WARNING );
    date_default_timezone_set("Asia/chongqing");
    include "Uploader.class.php";
    
    defined('ROOT_PATH') or define('ROOT_PATH', preg_replace('/includes(.*)/i', '', str_replace('\\', '/', __FILE__)));
    
    //上传配置
    $config = array(
        "savePath" => ROOT_PATH."images/editor/pic/" , //存储文件夹
        "maxSize" => 2048 ,                     //允许的文件最大尺寸，单位KB
        "allowFiles" => array( ".gif" , ".png" , ".jpg" , ".jpeg" , ".bmp" )  //允许的文件格式
    );

    //背景保存在临时目录中
    $up = new Uploader( "upfile" , $config );
    $type = $_REQUEST['type'];
    $callback=$_GET['callback'];

    $info = $up->getFileInfo();
    if (!empty($info) && !empty($info['url'])) {
    	$info['url'] = '/'.str_replace(ROOT_PATH, '', $info['url']);
    }
    /**
     * 返回数据
     */
    if($callback) {
        echo '<script>'.$callback.'('.json_encode($info).')</script>';
    } else {
        echo json_encode($info);
    }
