<?php

define("TOKEN", "testtoken");
$wechatObj = new wechatCallbackapiTest();

if (isset($_GET['echostr'])) {
    $wechatObj->valid();
}else{
    $wechatObj->responseMsg();
}

class wechatCallbackapiTest
{
    public function valid()
    {
        $echoStr = $_GET["echostr"];
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
    }

    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }

    //响应消息
    public function responseMsg()
    {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];//获取post数据
        if (!empty($postStr)){
            //解析xml字符串
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $RX_TYPE = trim($postObj->MsgType);   //获取MsgType类型

            //消息类型分离，根据消息类型来执行不同操作
            switch ($RX_TYPE)
            {
                case "event":
                    $result = $this->receiveEvent($postObj);
                    break;
                case "text":
                    $result = $this->receiveText($postObj);
                    break;
            }
            echo $result;
        }else {
            echo "";
            exit;
        }
    }

    //接收事件消息
    private function receiveEvent($object)
    {
        $content = "";
        switch ($object->Event)
        {
            case "subscribe":
            //$content = "欢迎关注Linux菜园子^-^";
            $content[] = array("Description"=>"欢迎关注linux菜园子!\n这里记录了一个linux小菜鸟的成长历程^_^\n回复help查看帮助!\n");
                break;
        }
        if(is_array($content)){
            if (isset($content[0])){
                $result = $this->transmitNews($object, $content);
            }else if (isset($content['MusicUrl'])){
                $result = $this->transmitMusic($object, $content);
            }
        }else{
            $result = $this->transmitText($object, $content);
        }

        return $result;
    }

    //接收文本消息
    public function receiveText($object)
    {
        $keyword = trim($object->Content);
        
        if (strstr($keyword, "help")){
             $content = array();
             $content = "回复ls可查看最近文章列表！\n回复'ls p数字'可查看上一页文章列表，如'ls p2' \n回复‘cat文章编号’可查看文章内容!\n回复2048可以玩游戏哦^_^\n命令还在继续支持中，敬请期待！";
        }else if (strstr($keyword, "2048")){
             $content = array();
             $content[] = array("Title"=>"2048游戏",  "Description"=>"游戏规则很简单，每次可以选择上下左右其中一个方向去滑动，每滑动一次，所有的数字方块都会往滑动的方向靠拢外，系统也会在空白的地方乱数出现一个数字方块，相同数字的方块在靠拢、相撞时会相加。系统给予的数字方块不是2就是4，玩家要想办法在这小小的16格范围中凑出“2048”这个数字方块。", "PicUrl"=>"http://img.laohu.com/www/201403/27/1395908994962.png", "Url" =>"http://gabrielecirulli.github.io/2048/");
        }else if(stristr($keyword, "ls")){
            if(stristr($keyword,"p")){
                 if(preg_match_all("/\d+/", $keyword, $matches)){
                        $num=$matches[0][0];
                        $str=$this->getContentFromDb($num,20);  //获取文章
                 }else{
                        $content="请输入正确的命令";
                 }
            }else{
                    $str=$this->getContentFromDb(1,20);  //默认第一页，也就是最近20篇
            }
            foreach($str as $v){
                $content.=$v['id'].":".$v['title']."\n";
            }
       }else if(stristr($keyword, "cat")){
                if(preg_match_all("/\d+/", $keyword, $matches)){
                     if(!empty($matches)){
                            $num=$matches[0][0];
                            $result=$this->GetContentById($num);
                            $title=$result['post_title'];
                            $des=substr($result['post_content'],0,400);
                            $cont="http://lisux.me/lishuai/?p=$num";
                            $content[] = array("Title"=>$title,  "Description"=>$des, "PicUrl"=>"", "Url" =>$cont);
                     }
              }else{
                     $content="请输入正确的命令";
              }

       }else{
                $content = "请输入正确的命令!"."\n";
       }
         
        if(is_array($content)){
            if (isset($content[0]['PicUrl'])){
                $result = $this->transmitNews($object, $content);
            }else if (isset($content['MusicUrl'])){
                $result = $this->transmitMusic($object, $content);
            }
        }else{
            $result = $this->transmitText($object, $content);
        }
        
        return $result;
    }

    //回复文本消息
    private function transmitText($object, $content)
    {
        //输出消息的xml模版
        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[text]]></MsgType>
<Content><![CDATA[%s]]></Content>
</xml>";
        //对消息模版中的通配符进行替换
        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), $content);
        return $result;
    }

    //回复图文消息   
    private function transmitNews($object, $newsArray)
    {
        if(!is_array($newsArray)){
            return;
        }
        $itemTpl = "<item>
        <Title><![CDATA[%s]]></Title>
        <Description><![CDATA[%s]]></Description>
        <PicUrl><![CDATA[%s]]></PicUrl>
        <Url><![CDATA[%s]]></Url>
    </item>";
        $item_str = "";
        foreach ($newsArray as $item){
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
        }
        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[news]]></MsgType>
<ArticleCount>%s</ArticleCount>
<Articles>
$item_str</Articles>
</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), count($newsArray));
        return $result;
    }

    private function getContentFromDb($page,$pagesize){
            $dbh=$this->GetDbHandle();
            $num=($page-1) * $pagesize;
            $sql="SELECT * from wp_posts where post_status='publish' and post_type='post' order by  post_date desc limit $num,$pagesize";
            $statement = $dbh->query($sql);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            foreach ($result as $v) {
                    if(!strpos($v['post_title'], "草稿")|| $v['post_content']!=''){

                        //if($v['post_status']=='publish'  &&  $v['post_type']=='post'){
                            $_item['id'] =$v['ID'];
                            $_item['title']=$v['post_title'];
                            $_item['post_comtent']=$v['post_content'];
                            $arr[]=$_item;
                        //}
                    }
            }
            return $arr;
    }

	private	function getContentById($id){
            $dbh=$this->GetDbHandle();
            $sql="SELECT post_title,post_content from wp_posts where ID=$id";
            $statement = $dbh->query($sql);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            if(!empty($result)){
                $result=$result[0];
                return $result;
            }
    }

	private function GetDbHandle(){
            include_once("./.config.php");
            try {
                    $dbh = new PDO('mysql:host=localhost;port=3306;dbname=dbname', $user, $pwd);
                    $dbh->query("set names utf8");
                    return $dbh;
            } catch (PDOException $e) {
                    print "Error!: " . $e->getMessage() . "<br/>";
                    die();
            }
	}

}
?>
