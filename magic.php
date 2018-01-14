<?php
include('params.php');
require($m['libs'].'system.php');
require($m['libs'].'vendor/autoload.php');

use Masterminds\HTML5;

ob_start("magic");
// Execute the application.
$app->execute();
ob_end_flush();

function magic($buffer) {
    global $m;
    $memcached = new Memcached();
    $memcached->addServer($m['memcached']['ip'], $m['memcached']['port']);
    $hash = sha1_salt($buffer);
    $cached = $memcached->get($hash); 
    if (strlen($cached)>0) {
        // cached, return from memcached
        return $cached;
    } else {
        // generate page
        $html5 = new HTML5();
        $tidy = new tidy;
        $tidy_params=array(
            'indent'=>TRUE,
            'output-xhtml'=>TRUE,
            'wrap'=>65535
        );
        $tidy->parseString($buffer, $tidy_params, 'utf8');
        $tidy->cleanRepair();
        
        // processing for <head>
        $head = tidy_get_head($tidy);
        $html5 = new HTML5();
        $dom = $html5->loadHTML($head);
        // process head for javascript includes
        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $skip = false;
            $url = $script->getAttribute('src');
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                // script via local filesystem, change to fetch over http
                $url = $m['base'].$url;
            }
            $url_hash = sha1_salt($url);
            $result = store_url($m['temp'].$url_hash,$url);
            if ($result['errno']==0) {
                $js_hash = md5_file($m['temp'].$url_hash);
                $ext = '.js';
                if (!file_exists($m['root'].$m['folders']['js'].$js_hash.$ext)) copy($m['temp'].$url_hash,$m['root'].$m['folders']['js'].$js_hash.$ext);
                $url = $m['base'].$m['folders']['js'].$js_hash.$ext;
            } else {
                $skip = true;
            }
            if (!$skip) {
                // script at local cache, swap the src url
                $script->setAttribute('src', $url);
            }
        }
        // process head for <link>
        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            $skip = false;
            $url = $link->getAttribute('href');
            $rel = $link->getAttribute('rel');
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                // link via local filesystem, change to fetch over http
                $url = $m['base'].$url;
            }
            if ($rel == 'stylesheet') {
                $css = '@import url("'.$url.'");'."\n";
                $css_hash = md5_salt($css);
                $ext = '.css';
                if (!file_exists($m['root'].$m['folders']['css'].$css_hash.$ext)) file_put_contents($m['root'].$m['folders']['css'].$css_hash.$ext,$css);
                $url = $m['base'].$m['folders']['css'].$css_hash.$ext;
            } else {
                $url_hash = sha1_salt($url);
                $filename = $m['temp'].$url_hash;
                $result = store_url($filename,$url);
                if ($result['errno']==0) {
                    $js_hash = md5_file($m['temp'].$url_hash);
                    $ext = get_file_extension($filename);
                    if (!file_exists($m['root'].$m['folders']['img'].$js_hash.$ext)) copy($filename,$m['root'].$m['folders']['img'].$js_hash.$ext);
                    $url = $m['base'].$m['folders']['img'].$js_hash.$ext;
                } else {
                    $skip = true;
                }
            }
            if (!$skip) {
                // script at local cache, swap the src url
                $link->setAttribute('href', $url);
            }
        }
        $head = explode("\n",trim($html5->saveHTML($dom)));
        array_shift($head);
        array_shift($head);
        array_pop($head);
        
        // processing for <body>
        $body = tidy_get_body($tidy);
        $html5 = new HTML5();
        $dom = $html5->loadHTML($body);
        // process <img> -tags on <body> for local cache and future optimization
        $imgs = $dom->getElementsByTagName('img');
        foreach($imgs as $img) {
            $skip = false;
            $url = $img->getAttribute('src');
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                // image is on local filesystem, hash and copy to cache folder for further optimization
                if ($url[0] == '/') $url = substr($url,1);
                $filename = $m['root'].$url;
                if (file_exists($filename)) {
                    $img_hash = md5_file($filename);
                    $ext = get_file_extension($filename);
                    if (!file_exists($m['root'].$m['folders']['img'].$img_hash.$ext)) copy($filename,$m['root'].$m['folders']['img'].$img_hash.$ext);
                    $url = $m['base'].$m['folders']['img'].$img_hash.$ext;
                } else {
                    $skip = true; // file not found, dont modify
                }
            } else {
                // image is external, store it locally to cache folder for further optimization
                $url_hash = sha1_salt($url);
                $result = store_url($m['temp'].$url_hash,$url);
                if ($result['errno']==0) {
                    $img_hash = md5_file($m['temp'].$url_hash);
                    $ext = get_file_extension($m['temp'].$url_hash);
                    if (!file_exists($m['root'].$m['folders']['img'].$img_hash.$ext)) copy($m['temp'].$url_hash,$m['root'].$m['folders']['img'].$img_hash.$ext);
                    $url = $m['base'].$m['folders']['img'].$img_hash.$ext;
                } else {
                    $skip = true; // download error, dont modify
                }
            }
            if (!$skip) {
                // image at local cache, swap the src url
                $img->setAttribute('src', $url);
            }
        }
        $body = explode("\n",trim($html5->saveHTML($dom)));
        array_shift($body);
        array_shift($body);
        array_pop($body);
        
        // merge processed parts into the document
        $document = '';
        $html =  explode("\n",$tidy->html());
        $skip = false;
        foreach ($html as $i=>$row) {
            if (strpos($row,'<head')!==false) {
                $skip = true;
                $document .= $row."\n".implode("\n",$head)."\n";
            }
            if (strpos($row,'<body')!==false) {
                $skip = true;
                $document .= $row."\n".implode("\n",$body)."\n";
            }
            
            if (!$skip) {
                $document .= $row."\n";
            }            
            if ($skip) {
                if (strpos($row,'</head>')!==false) {
                    $skip = false;
                    $document .= $row."\n";
                }
                if (strpos($row,'</body>')!==false) {
                    $skip = false;
                    $document .= $row."\n";
                }
            }
            
        }
        $memcached->set($hash,$document,3600*24);
        return $document;
    }
}
