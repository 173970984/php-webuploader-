<?php
/**
 * upload.php
 *
 * Copyright 2013, Moxiecode Systems AB
 * Released under GPL License.
 *
 * License: http://www.plupload.com/license
 * Contributing: http://www.plupload.com/contributing
 */

class Upload
{
    private $filepath = 'uploads/'; //上传目录
    private $blobNum; //第几个文件块
    private $totalBlobNum; //文件块总数
    private $fileName; //文件名
    #允许上传的文件
    private $allowExtension = ['psd','jpg','jpeg'];
    #文件后缀
    private $fileExtension ='';
    #当前块内容
    private $nowFile = '';
    #文件大小
    private $totalSize = 0;
    #文件总大小只允许2G
    private $allowFileSize = 0;
    #文件md5  前端传过来的   用于创建临时文件夹  上传完后删除
    private $fileMd5='';
    public function __construct($savePath =''){
        $postData = $_POST;
        #测试断点上传
        if(isset($postData['test'])){
            sleep(1);
        }
        if($savePath){
            $this->filepath = $this->filepath.$savePath;
        }
        #    #文件名称
      #  var_dump($postData);
        $postData['name'] =isset($postData['name'])?$postData['name']:'';
        $this->fileName =$postData['name'];
        if($this->isHaveFile()){
            $this->ajaxReturn(['status'=>299,'msg'=>'文件已存在！']);
        }

        $this->fileMd5 =$postData['fileMd5'];

        #允许文件的大小  2g
        $this->allowFileSize =(2*1024*1024*1024);

        if((int)$postData['size']>$this->allowFileSize){
            $this->ajaxReturn(['status'=>204,'msg'=>"文件大小超2G限制！"]);
        }
        #文件大小
        $this->totalSize=$postData['size'];
        $postData['chunks']=isset($postData['chunks'])?(int)$postData['chunks']:1;
        $postData['chunk']=isset($postData['chunk'])?(int)$postData['chunk']:0;
        if(!(int)$postData['chunks']){
            $this->ajaxReturn(['status'=>208,'msg'=>'chunks参数错误']);
        }

        #当前块
        $this->blobNum =$postData['chunk']+1;
        #总共块
        $this->totalBlobNum =$postData['chunks'];

        #获取后缀
        $fileExtension =explode(".",basename( $this->fileName));
        $this->fileExtension=array_pop($fileExtension);
        #检测后缀是否在允许范围
        $this->checkFileExtension();
        $this->nowFile =  $_FILES['file'];
        if( $this->nowFile['error'] > 0) {
            $msg['status'] = 502;
            $msg['msg'] = "文件错误！";
            $this->ajaxReturn($msg);
        }

    }
    function doUpload(){
        #临时文件移动到指定目录下
        $res = $this->moveFile();
        if($res['status']==999){
            return $this->fileMerge();
        }else{
            return $res;
        }
    }

    #创建md5  文件名
    function createFileName(){
        return $this->filepath.$this->fileName;
    }

    #检测文件是否重复
    function isHaveFile(){
        if(file_exists($this->filepath.$this->fileName)){
            return true;
        }
        return false;
    }
    #文件合并
    function fileMerge(){
        if ($this->blobNum == $this->totalBlobNum) {
            $fileName = $this->createFileName();
            @unlink($fileName);
            #删除旧文件
            #文件合并  文件名以
            $handle=fopen($fileName,"a+");
            for($i=1; $i<= $this->totalBlobNum; $i++){
                #当前分片数
                $this->blobNum = $i;
                #吧每个块的文件追加到 上传的文件中
                fwrite($handle,file_get_contents($this->createBlockFileName()));
            }
            fclose($handle);
            #删除分片
            for($i=1; $i<= $this->totalBlobNum; $i++){
                $this->blobNum = $i;
                @unlink($this->createBlockFileName());
            }
            #删除临时目录
            @rmdir($this->filepath.$this->fileMd5);
            if(filesize($fileName) == $this->totalSize){
                $msg['status'] = 200;
                $msg['msg'] = '上传成功！';
                $msg['size'] = $this->totalSize;
                $msg['filename'] = "http://".$_SERVER['HTTP_HOST']."/".$this->createFileName();
                $msg['name'] = $this->fileName;
            }else{
                $msg['status'] = 501;
                $msg['msg'] = '上传文件大小和总大小有误！';
                @unlink($this->createFileName());
            }
            return $msg;
            # $this->ajaxReturn($msg);
        }
    }
    #检测上传类型
    function checkFileExtension(){
        if(!in_array(strtolower($this->fileExtension),$this->allowExtension)){
            $this->ajaxReturn(['status'=>203,'msg'=>"文件类型不允许"]);
        }
    }
    #将临时文件移动到指定目录
    function moveFile(){
        try{
            #每个块的文件名 以文件名的MD5作为命名
            $filename=$this->createBlockFileName();
            #分片文件写入
            $handle=fopen($filename,"w+");
            fwrite($handle,file_get_contents($this->nowFile ['tmp_name']));
            fclose($handle);
            #不是最后一块就返回当前信息   是最后一块往下执行合并操作
            if($this->blobNum != $this->totalBlobNum) {
                $msg['status'] = 201;
                $msg['msg'] = "上传成功！";
                $msg['blobNum'] = $this->blobNum;
                return $msg;
                #$this->ajaxReturn($msg);
            }else{
                $msg['status'] = 999;
                $msg['msg'] = "上传成功！";
                $msg['blobNum'] = $this->blobNum;
                return $msg;
            }
        }catch (Exception $e){
            $msg['status'] = 501;
            $msg['error'] = $e->getMessage();
            return $msg;
            #$this->ajaxReturn($msg);
        }
    }
    #创建分片文件名
    function createBlockFileName(){
        $dirName = $this->filepath.$this->fileMd5."/";
        if (!is_dir($dirName) ) {
            @mkdir($dirName, 0700);
        };
        return $dirName.$this->blobNum.".part";
    }

    #json格式放回处理
    function ajaxReturn($msg){
        exit(json_encode($msg));
    }
}
$model =new Upload();
$res = $model->doUpload();
$model->ajaxReturn($res);
