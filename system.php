<?php
function decho($msg) {
    echo date('Y-m-d H:i:s')."\t$msg\n";
}

function filesize_nc($path) {
    clearstatcache();
    return filesize($path);
}
function get_url($url) {
    $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
    $options = array(
        CURLOPT_CUSTOMREQUEST  =>"GET",        //set request type post or get
        CURLOPT_POST           => false,        //set to GET
        CURLOPT_USERAGENT      => $user_agent, //set user agent
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => false,    // don't return headers
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_ENCODING       => "",       // handle all encodings
        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
        CURLOPT_SSL_VERIFYHOST => 0,		// don't verify ssl
        CURLOPT_SSL_VERIFYPEER => 0,	// don't verify ssl
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

function store_url($filename,$url) {
    if (file_exists($filename)) {
        $err = 0;
        $errmsg = 'File cached';
    } else {
        $fp = fopen($filename,"wb");
        if ($fp == FALSE) {
            $err = '999';
            $errmsg = 'Cannot create file';
        } else {
            $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
            $options = array(
                CURLOPT_CUSTOMREQUEST  =>"GET",        //set request type post or get
                CURLOPT_POST           => false,        //set to GET
                CURLOPT_USERAGENT      => $user_agent, //set user agent
                CURLOPT_RETURNTRANSFER => true,     // return web page
                CURLOPT_HEADER         => false,    // don't return headers
                CURLOPT_FOLLOWLOCATION => true,     // follow redirects
                CURLOPT_ENCODING       => "",       // handle all encodings
                CURLOPT_AUTOREFERER    => true,     // set referer on redirect
                CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
                CURLOPT_TIMEOUT        => 120,      // timeout on response
                CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
                CURLOPT_SSL_VERIFYHOST => 0,	//don't verify ssl
                CURLOPT_SSL_VERIFYPEER => 0,	//don't verify ssl
                CURLOPT_FILE => $fp, 	// filepointer for storing the downloaded file
            );
            $ch      = curl_init( $url );
            curl_setopt_array( $ch, $options );
            curl_exec( $ch );
            $err     = curl_errno( $ch );
            $errmsg  = curl_error( $ch );
            $header  = curl_getinfo( $ch );
            curl_close( $ch );
        }
    }
    $header['errno']   = $err;
    $header['errmsg']  = $errmsg;
    return $header;
}

function get_file_extension($filename) {
    $map = array(
        'application/pdf'   => '.pdf',
        'application/zip'   => '.zip',
        'image/gif'         => '.gif',
        'image/jpeg'        => '.jpg',
        'image/png'         => '.png',
        'text/css'          => '.css',
        'text/html'         => '.html',
        'text/javascript'   => '.js',
        'text/plain'        => '.txt',
        'text/xml'          => '.xml',
        'image/x-icon'      => '.ico',
    );
    if (file_exists($filename)){
        $mime = mime_content_type($filename);
        if (isset($map[$mime])){
            return $map[$mime];
        } else {
            $p = pathinfo($filename);
            $ext = strtolower($p['extension']);
            if (strlen($ext)>0) {
                return ".$ext";
            } else return '';
        }
    } else return '';
}

function sha1_salt(&$data) {
    global $m;
    return sha1($data.$m['salt']);
}

function md5_salt(&$data) {
    global $m;
    return md5($data.$m['salt']);
}