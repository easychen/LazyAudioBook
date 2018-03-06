<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
    // define public methods as commands

    /**
     * 将文本文件转化为mp3
     */
    public function convert()
    {
        // 准备key
        @include 'account.php' ;
        if( !isset( $GLOBALS['baidu_akey'] ) )
        {
            $akey = $this->ask("请输入百度语音合成服务的appkey");
            $skey = $this->ask("请输入百度语音合成服务的appsecret");
        }
        else
        {
            $akey = $GLOBALS['baidu_akey'];
            $skey = $GLOBALS['baidu_skey'];
        }

        $i = 0;
        // 每次发送 400 个字符
        $max = 400;
        $len = 0;
        $ship = [];
        $audio_count = 1;

        

        $last_file = 'last.json';
        if( file_exists( $last_file ) )
        {
            $info = json_decode( file_get_contents( $last_file ) , 1  );
            $this->save = $info['save'];
            $content_lines = $info['content_lines'];
            if( isset( $info['voice_type'] ) )
                $this->voice_type = $info['voice_type'];
        }
        else
        {
            // 获取txt文件路径
            $path = $this->askDefault("请输入要转换的txt文件（仅支持UTF8格式）","/Users/Easy/Desktop/money.txt");
            if( !file_exists( $path ) )
            {
                $this->say("该文件不存在");
                return false;
            }

            $this->save = $this->askDefault("请输入生成mp3文件的地址",'out.mp3');
            $this->voice_type = $this->askDefault("请输入生成语音的风格，3-情感男声；4-情感女生",'3'); ;

            $content_lines = file( $path );

            $EC = mb_detect_encoding( join( "\r\n" , $content_lines )  , "UTF8, GB2312, GBK , CP936");
            
            if( $EC != "UTF8" )
            {
                foreach( $content_lines as $key => $value )
                {
                    $content_lines[$key] = mb_convert_encoding( $value , "UTF8" ,  $EC  );
                }
            }
            
            $new_lines = [];
            foreach( $content_lines as $key => $value )
            {
                // 如果单行文字超过了最大长度
                if( mb_strlen( $value , 'UTF8' ) > $max )
                {
                    // 分拆成几句
                    $subs = mb_str_split( $value , $max , 'UTF8' );
                    foreach(  $subs as $item )
                    {
                        array_push( $new_lines , $item );
                    }
                }
                else
                {
                    $new_lines[] = $value;
                }
                //$content_lines[$key] = mb_convert_encoding( $value , "UTF8" ,  $EC  );
            }

            $content_lines = $new_lines;
        }        

        // 读取全部文件内容
        // 直接按行分割
        
        
        $this->say("读取文件成功，共".count($content_lines)."行");
        
        
        // show( $content_lines , 100 );
        
        while( $len <= $max && count( $content_lines ) > 0 )
        {
            $snap_lines = $content_lines;
            // 从最上边取出
            $now_line = array_shift( $content_lines );

            $do_convert = false;
            
            if( $len + mb_strlen( $now_line , 'UTF8' ) > $max )
            {
                array_unshift( $content_lines , $now_line  );
                
                // 调用音频转换函数
                $do_convert = true; 
                
                
            }
            else
            {
                array_push( $ship , $now_line );
                $len += mb_strlen( $now_line , 'UTF8' );

                if( count( $content_lines ) == 0 ) $do_convert = true; 
                
            }

            if( $do_convert )
            {
                if( $this->txt_to_audio( $audio_count++, $akey , $skey , $ship ) )
                {
                    if( count( $content_lines ) === 0 )
                    {
                        $this->say("转化完成 🥇");
                        @unlink( 'last.json' );
                        exit;
                    }
                    
                    $this->say("待转化段数". count( $content_lines ) );

                    // 保存当前工作数据和目标文件
                    $last = [];
                    $last['save'] = $this->save;
                    $last['voice_type'] = $this->voice_type;
                    $last['content_lines'] = $content_lines;


                    file_put_contents( 'last.json' , json_encode( $last , JSON_UNESCAPED_UNICODE ) );
                    
                    
                    
                    // 清空
                    $ship = [];
                    $len = 0;
                }
                else
                {
                    // 回滚
                    
                    // // 保存当前工作数据和目标文件
                    // $last = [];
                    // $last['save'] = $this->save;
                    // $last['content_lines'] = $snap_lines;

                    // file_put_contents( 'last.json' , json_encode( $last , JSON_UNESCAPED_UNICODE ) );

                    $this->say("音频转换失败，程序中止");
                    break;
                }

                
            }

            $i++;
            // if( $i > 500 ) break;
            
        }

        if( $len >= $max )
        {
            $this->say("len = $len , max =  $max ，转化结束");
        }

        if( count( $content_lines ) <= 0 )
        {
            $this->say( count( $content_lines ) . "<--待处理行数为零 ，转化结束");
        }


    }

    private function txt_to_audio( $count , $akey , $skey , $data_array )
    {
        // 如果没有token
        if( !isset( $GLOBALS['token'] ) )
        {
            $this->say( "Token 不存在，换取 token" );
            $ret = file_get_contents( "https://openapi.baidu.com/oauth/2.0/token?grant_type=client_credentials&client_id=" . urlencode( $akey ) . "&client_secret=" . urlencode( $skey ) );

            // print_r( json_decode( $ret , 1 ) );
            
            $bad_token = true;
            
            if( $ret )
            {
                if(  $info = json_decode( $ret , 1 ) )
                {
                   if( isset( $info['access_token'] ) ) 
                   {
                    $this->say("换取Token成功");
                        $GLOBALS['token'] = $info['access_token'];
                        $bad_token = false;
                   }
                }    
            }

            if( $bad_token )
            {
                $this->say("换取Token失败");
                return false;
            }
        }
        $text = join( "\r\n" , $data_array );

        $this->say("转换..." . mb_substr( trim( $text ) , 0 , 30 , 'UTF8' ));
        if( mb_strlen( trim( $text ) , 'UTF8' ) < 1 )
        {
            $this->say("文字空白，跳过");
            return true;
        }
        // 获取音频下载地址：
        $re_try = 0;

        get_audio:
        
        $audio = file_get_contents( 'http://tsn.baidu.com/text2audio?lan=zh&ctp=1&cuid=LOCALMAC1022&tok=' . urlencode( $GLOBALS['token'] ) . '&tex=' . urlencode( urlencode( $text ) ) . '&vol=9&per=' . intval( $this->voice_type ) . '&spd=5&pit=5');

        $headers = parseHeaders( $http_response_header );
        if( $headers['Content-Type'] == 'audio/mp3' )
        {
            file_put_contents( $this->save , $audio , FILE_APPEND );
            $this->say( "此部分已追加写入音频文件 🤠 \r\n" );
            return true;
        }
        else
        {
            // $this->say( "音频转码失败，转换中止" );
            $re_try++;
            print_r( $audio );
            
            if( $re_try < 2 ) goto get_audio;
            else return false;
        }
        

       
    }

  
}

function parseHeaders( $headers )
{
    $head = array();
    foreach( $headers as $k=>$v )
    {
        $t = explode( ':', $v, 2 );
        if( isset( $t[1] ) )
            $head[ trim($t[0]) ] = trim( $t[1] );
        else
        {
            $head[] = $v;
            if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) )
                $head['reponse_code'] = intval($out[1]);
        }
    }
    return $head;
}

function mb_str_split($string,$string_length=1,$charset='utf-8') 
{
    if(mb_strlen($string,$charset)>$string_length || !$string_length) 
    {
    do {
    $c = mb_strlen($string,$charset);
    $parts[] = mb_substr($string,0,$string_length,$charset);
    $string = mb_substr($string,$string_length,$c-$string_length,$charset);
    }while(!empty($string));
    } else {
    $parts = array($string);
    }
    return $parts;
}

function show( $array , $len )
{
    for( $i = 0 ; $i<$len ; $i++ )
    {
        echo "[$i]>>>>".$array[$i]."<<<<<\r\n";
    }
}