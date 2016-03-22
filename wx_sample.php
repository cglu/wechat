<?php
/**
 * wechat php test
 */

// 开发模式
//define('DEBUG', 1);
// define your token
define("TOKEN", "YTXB70SORP");

/**
 * LOG输出
 *
 * @param unknown $msg            
 */
function wxlog($msg)
{
    if (defined('DEBUG')) {
        file_put_contents('wechat.log', $msg, FILE_APPEND);
    }
}

$wechatObj = new wechatCallbackapiTest();
$wechatObj->responseMsg();

class wechatCallbackapiTest
{

    /**
     * 文本消息XML数据包结构
     *
     * @var unknown
     */
    const WX_TEXT_TPL = <<<EOD
        <xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[text]]></MsgType>
        <Content><![CDATA[%s]]></Content>
        <FuncFlag>0</FuncFlag>
        </xml>
EOD;

    /**
     * 文章消息XML数据包模板
     *
     * @var unknown
     */
    const WX_NEWS_TPL = <<<EOD
    <xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[news]]></MsgType>
<ArticleCount>%s</ArticleCount>
<Articles>
%s
</Articles>
</xml> 
EOD;

    /**
     * 文章消息XML数据包(ITEM)模板
     *
     * @var unknown
     */
    const WX_NEWS_ITEM_TPL = <<<EOD
    <item>
<Title><![CDATA[%s]]></Title> 
<Description><![CDATA[%s]]></Description>
<PicUrl><![CDATA[%s]]></PicUrl>
<Url><![CDATA[%s]]></Url>
</item>
EOD;
    
    // 最新文章
    const CGLU_NEWEST_ARTICL = 1;
    // 随机文章
    const CGLU_RANDOM_ARTICLE = 2;
    // 博文主页
    const CGLU_BLOG_HOME = 3;
    // 问题反馈
    const CGLU_QUESTION_BACK = 6;
    // 关于我
    const CGLU_ABOUT_ME = 7;
    // 获取帮助
    const CGLU_HELP = 8;
    // 提交问题
    const CGLU_SUBMIT_QUESTION = '@';

    const CGLU_WELCOME_INFO = <<<EOD
 您好，欢迎您关注[cglu]个人订
 阅，您可以回复指定序号以获取
 相关专栏的内容，还可以直接和
 图灵机器人聊天哦。
【1】最新文章
【2】随机文章
【3】博文主页
【6】问题反馈
【7】关于我
【8】获取帮助 
EOD;

    public function valid()
    {
        $echoStr = $_GET["echostr"];
        
        // valid signature , option
        if ($this->checkSignature()) {
            echo $echoStr;
            exit();
        }
    }

    public function responseMsg()
    {
        
        // get post data, May be due to the different environments
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        
        // extract post data
        if (! empty($postStr)) {
            /*
             * libxml_disable_entity_loader is to prevent XML eXternal Entity Injection,
             * the best way is to check the validity of xml by yourself
             */
            libxml_disable_entity_loader(true);
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            
            $msgType = $postObj->MsgType;
            $time = time();
            // 如果是语音消息，则获取语音消息的识别结果
            if ($msgType == "voice") {
                $keyword = $postObj->Recognition;
            } else 
                if ($msgType == "text") {
                    $keyword = trim($postObj->Content);
                } elseif ($msgType == 'event' && $postObj->Event == 'subscribe') {
                    $keyword = self::CGLU_HELP; // 处理关注事件
                } else {
                    $keyword = self::CGLU_HELP; // 修正为帮助
                }
            wxlog('本次请求keyword=' . $keyword);
            if (! empty($keyword)) {
                
                if ($keyword == self::CGLU_HELP) {
                    // 输出帮助信息
                    $contentStr = self::CGLU_WELCOME_INFO;
                    $resultStr = sprintf(self::WX_TEXT_TPL, $fromUsername, $toUsername, $time, $contentStr);
                } elseif ($keyword == self::CGLU_NEWEST_ARTICL || $keyword == self::CGLU_RANDOM_ARTICLE) {
                    
                    if ($keyword == self::CGLU_NEWEST_ARTICL) {
                         wxlog('读取lublog中最新的三篇文章。');
                        // 输出最新的文章，取得前三篇.
                        $sql = "SELECT description,title,id FROM articles ORDER BY created_at DESC limit 3;";
                    } else {
                        //随机取出三篇文章
                        $sql = "SELECT description,title,id FROM articles ORDER BY RAND() DESC limit 3";
                    }
                   
                    $list = $this->execSql($sql);
                    // wxlog('最新的文章检索结果。' . var_export($list, 1));
                    $listCount = 0;
                    $items = '';
                    foreach ($list as $new) {
                        $listCount ++;
                        $title = $new['title'];
                        $description = $new['description'];
                        $icon = 'http://luhu.in/images/avatar.jpg';
                        $url = 'http://luhu.in/article/' . $new['id'];
                        $items .= sprintf(self::WX_NEWS_ITEM_TPL, $title, $description, $icon, $url);
                    }
                    $resultStr = sprintf(self::WX_NEWS_TPL, $fromUsername, $toUsername, $time, $listCount, $items);
                } elseif ($keyword == self::CGLU_BLOG_HOME) {
                    // 输出个人博客主页
                    $items = sprintf(self::WX_NEWS_ITEM_TPL, '个人博客LuBlog', '灵感 - 来自生活的馈赠', 'http://luhu.in/images/avatar.jpg', 'http://luhu.in');
                    $resultStr = sprintf(self::WX_NEWS_TPL, $fromUsername, $toUsername, $time, 1, $items);
                } elseif ($keyword == self::CGLU_ABOUT_ME) {
                    // 输出自我介绍
                    $items = sprintf(self::WX_NEWS_ITEM_TPL, '关于我', '关于我', 'http://luhu.in/images/avatar.jpg', 'http://luhu.in/about');
                    $resultStr = sprintf(self::WX_NEWS_TPL, $fromUsername, $toUsername, $time, 1, $items);
                } elseif ($keyword == self::CGLU_QUESTION_BACK) {
                    // 用户反馈格式输出
                    $contentStr = "尊敬的用户，为了更好的完善订阅号功能，请将对系统的不足之处反馈给cglu。";
                    $contentStr .= "\n反馈格式：@+建议内容\n例如：@希望增加***功能";
                    $resultStr = sprintf(self::WX_TEXT_TPL, $fromUsername, $toUsername, $time, $contentStr);
                } elseif (strpos($keyword, self::CGLU_SUBMIT_QUESTION) === 0) {
                    // 保存用户的反馈并输出感谢用语
                    $contentStr = "感谢您的宝贵建议，cglu会不断努力完善功能。";
                    $note = substr($keyword, 1);
                    $note .= "\r\n";
                    file_put_contents('notes.txt', $note, FILE_APPEND);
                    $resultStr = sprintf(self::WX_TEXT_TPL, $fromUsername, $toUsername, $time, $contentStr);
                } else {
                    // 图灵文本消息
                    define('TULING_TEXT_MSG', 100000);
                    // 图灵连接消息
                    define('TULING_LINK_MSG', 200000);
                    // 图灵新闻消息
                    define('TULING_NEWS_MSG', 302000);
                    // 图灵菜谱消息
                    define('TULING_COOKBOOK_MSG', 308000);
                    // 接入图灵机器人
                    $url = "http://www.tuling123.com/openapi/api?key=4625ea3c28073dc3cd91c33dbe4775ab&info=" . urlencode($keyword);
                    $str = file_get_contents($url);
                    $json = json_decode($str);
                    $contentStr = $json->text;
                    $code = $json->code;
                    if ($code == TULING_TEXT_MSG) {
                        // 文本消息
                        $resultStr = sprintf(self::WX_TEXT_TPL, $fromUsername, $toUsername, $time, $contentStr);
                    } elseif ($code == TULING_LINK_MSG) {
                        // 连接消息
                        $resultStr = sprintf(self::WX_TEXT_TPL, $fromUsername, $toUsername, $time, $contentStr . $json->url);
                    } elseif ($code == TULING_NEWS_MSG) {
                        // 新闻消息
                        $list = $json->list;
                        $listCount = count($list);
                        $items = '';
                        foreach ($list as $new) {
                            $title = $new->article;
                            $source = $new->source;
                            $icon = $new->icon;
                            $url = $new->detailurl;
                            $items .= sprintf(self::WX_NEWS_ITEM_TPL, $title, $title, $icon, $url);
                        }
                        $resultStr = sprintf(self::WX_NEWS_TPL, $fromUsername, $toUsername, $time, $listCount, $items);
                    } elseif ($code == TULING_COOKBOOK_MSG) {
                        // 菜谱消息
                        $list = $json->list;
                        $listCount = count($list);
                        $items = '';
                        foreach ($list as $cb) {
                            $name = $cb->name;
                            $info = $cb->info;
                            $icon = $cb->icon;
                            $url = $cb->detailurl;
                            $items .= sprintf(self::WX_NEWS_ITEM_TPL, $name, $info, $icon, $url);
                        }
                        $resultStr = sprintf(self::WX_NEWS_TPL, $fromUsername, $toUsername, $time, $listCount, $items);
                    }
                }
                echo $resultStr; // 输出数据包
            } else {
                echo "Input something...";
            }
        } else {
            echo "";
            exit();
        }
    }

    private function execSql($sql)
    {
        try {
            $pdo = new PDO('mysql:dbname=lublog;host=127.0.0.1;charset=UTF8', 'lublog', 'rUUQpPFBTJ5r3F3W');
            return $pdo->query($sql);
        } catch (Exception $e) {
            echo 'Connection failed: ' . $e->getMessage();
        }
    }

    private function checkSignature()
    {
        // you must define TOKEN by yourself
        if (! defined("TOKEN")) {
            throw new Exception('TOKEN is not defined!');
        }
        
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        
        $token = TOKEN;
        $tmpArr = array(
            $token,
            $timestamp,
            $nonce
        );
        // use SORT_STRING rule
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        
        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }
}

?>
