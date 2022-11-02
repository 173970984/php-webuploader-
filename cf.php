<?php
#
#检测图片是否上传过
#
$file = "uploads";
$folderPath = $file."/".$_POST['fileMd5']."/";
$countFile = 0;
$totalFiles = glob($folderPath . "*");

if(file_exists($file."/".$_POST['fileName'])){
    $s=['type'=>404,'msg'=>'文件已存在'];
    exit(json_encode($s));
}

if ($totalFiles){
    $countFile = count($totalFiles);
}
$countFile =$countFile?$countFile:0;
#存在分片文件
if($countFile){
    #返回已上传片数
    $s =['type'=>1,'chunk'=>($countFile-1),'chunks'=>10];
}else{
    #没有文件  从0开始上传
    $s =['type'=>3,'chunk'=>0,'chunks'=>10];
}
sleep(1);
exit(json_encode($s));









