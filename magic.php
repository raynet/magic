<?php
include('params.php');

ob_start("magic");
// Execute the application.
$app->execute();
ob_end_flush();

function magic($buffer) {
    $memcached = new Memcached();
    $memcached->addServer("127.0.0.1", 11211);
    $hash = sha1($buffer);
    $cached = $memcached->get($hash); 
    if (strlen($cached)>0) {
        // cached, return from memcached
        return $cached;
    } else {
        // generate page
        $tidy = new tidy;
        $tidy_params=array(
            'indent'=>TRUE,
            'output-xhtml'=>TRUE,
            'wrap'=>65535
        );
        $tidy->parseString($buffer, $tidy_params, 'utf8');
        $tidy->cleanRepair();
        $rows = explode("\n",$tidy->html());
        $html = '';
        $head = false;
        $css = '';
        $js = '';
        foreach ($rows as $i=>$row) {
            $skip = false;
            if (strpos($row,'<head>')!==false) $head = true;
            if ($head) {
                 // process HEAD   
                 $skip = true;
            } else {
                // process BODY and rest
                
            }
            if (strpos($row,'</head>')!==false) {
                $head = false;
            }
            if (!$skip) $html .= $row."\n"; 
        }
        return $html;
    }
}

/*
            $skip = false;
            if (strpos($row,'<head>')!==false) $head = true;
            if ($head) {
                // scanning head
                if (strpos($row,'<link ')!==false && strpos($row,'type="application/')!==false) $skip = true;
                if (strpos($row,'<script ')!==false && strpos($row,'src="')!==false) {
                    list($a,$url) = explode('src="',$row);
                    $url = substr($url,1,strrpos($url,'"')-1);
                    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                        $url = substr($url,0,strrpos($url,'.js')).'.js';
                        $item = file_get_contents($url);
                        $skip = true;
                    } else {
                        return "error1";
                    }
                    $js .= $item."\n";
                }
                if (strpos($row,'<link ')!==false && strpos($row,'rel="stylesheet"')!==false) {
                    list($a,$url) = explode('href="',$row);
                    $url = substr($url,1,strrpos($url,'"')-1);
                    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                        $item = "@import url('/$url');";
                        $skip = true;
                    } else {
                        return 'error2';
                    }
                    $css .= $item."\n";
                }
                if (!$skip && strpos($row,'<link ')!==false && strpos($row,'href="')!==false) {
                    list($a,$url) = explode('href="',$row);
                    $url = substr($url,1,strpos($url,'"')-1);
                    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                        $img_hash = md5_file($url);
                        $p = pathinfo($url);
                        $ext = $p['extension'];
                        if (!file_exists("img/$img_hash.$ext")) copy($url,"img/$img_hash.$ext");
                        $row = strtr($row,array(
                            $url => "img/$img_hash.$ext"
                        ));
                    } else {
                        return 'error3';
                    }
                }
                if (strpos($row,'</head>')!==false) {
                    if (strlen($css)>0) {
                        $css_hash = md5($css);
                        if (!file_exists("css/$css_hash.css")) file_put_contents("css/$css_hash.css",$css);
                        $html .= '    <link rel="stylesheet" href="'."/css/$css_hash.css".'" />'."\n";
                    }
                    if (strlen($js)>0) {
                        $js_hash = md5($js);
                        if (!file_exists("js/$js_hash.js")) file_put_contents("js/$js_hash.js",$js);
                        $html .= '    <script src="'."/js/$js_hash.js".'" type="text/javascript"></script>'."\n";
                    }
                }
            } else {
                // scanning body
                if (!$skip && strpos($row,'<img ')!==false && strpos($row,'src="')!==false) {
                    list($a,$url) = explode('src="',$row);
                    $url = substr($url,1,strpos($url,'"')-1);
                    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                        $img_hash = md5_file($url);
                        $p = pathinfo($url);
                        $ext = $p['extension'];
                        if (!file_exists("img/$img_hash.$ext")) copy($url,"img/$img_hash.$ext");
                        $row = strtr($row,array(
                            $url => "img/$img_hash.$ext"
                        ));
                    } else {
                        return "error4";
                    }
                }
            }
            
            if (strpos($row,'</head>')!==false) {
                $head = false;
            }
            if (!$skip) $html .= $row."\n";            
        }
        //$memcached->set($hash,$html,3600*24);
        return $html;
*/

function get_web_page( $url )
{
    $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
    $options = array(
        CURLOPT_CUSTOMREQUEST  =>"GET",        //set request type post or get
        CURLOPT_POST           =>false,        //set to GET
        CURLOPT_USERAGENT      => $user_agent, //set user agent
        CURLOPT_COOKIEFILE     =>"/tmp/cookie.txt", //set cookie file
        CURLOPT_COOKIEJAR      =>"/tmp/cookie.txt", //set cookie jar
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => false,    // don't return headers
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_ENCODING       => "",       // handle all encodings
        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
    );
    
    $ch      = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $content = curl_exec( $ch );
    $err     = curl_errno( $ch );
    $errmsg  = curl_error( $ch );
    $header  = curl_getinfo( $ch );
    curl_close( $ch );
    $header['errno']   = $err;
    $header['errmsg']  = $errmsg;
    $header['content'] = $content;
    return $header;
}
