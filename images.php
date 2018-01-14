<?php
include('system.php');
include('config.php');

$t['image/png'] = array(
    'max_size' => 1024 * 64, // 64kB for maximum PNG size
    'tools' => array('pngcrush','pngnq'),
    'fallback' => array('jpg',100),
);

$t['image/gif'] = array(
    'max_size' => 1024 * 64, // 64kB for maximum GIF size
    'tools' => array('gifsicle'),
    'fallback' => array('png',100),
);

$t['image/jpeg'] = array(
    'max_size' => 1024 * 384, // 384kB for maximum JPEG size
    'tools' => array('jpegoptim'),
    'fallback' => array('jpg',66),
);

foreach ($sites as $site => $folder) {
    decho("Processing $site");
    include("$folder/params.php");
    if (!file_exists($m['root'].$m['folders']['img'].'orig')) mkdir($m['root'].$m['folders']['img'].'orig');
    if (!file_exists($m['root'].$m['folders']['img'].'temp')) mkdir($m['root'].$m['folders']['img'].'temp');
    $img_folder = $m['root'].$m['folders']['img'];
    $files = scandir($img_folder);
    array_shift($files);
    array_shift($files);
    foreach ($files as $filename) {
        if (is_file($img_folder.$filename)) {
            $mime = mime_content_type($img_folder.$filename);
            $max_size = @$t[$mime]['max_size'];
            if (isset($t[$mime]['max_size']) && filesize_nc($img_folder.$filename) > $max_size) {
                decho("$filename exceeds maximum size!");
                if (!file_exists($img_folder.'orig/'.$filename)) {
                    decho("$filename making backup");
                    copy($img_folder.$filename,$img_folder.'orig/'.$filename);
                }
                foreach ($t[$mime]['tools'] as $tool) {
                    switch ($tool) {
                        case 'jpegoptim':
                            $quality = 99;
                            $step = 0.95;
                            $limit = 70;
                            while ($quality>=$limit) {
                                decho("	trying quality $quality");
                                if (filesize_nc($img_folder.$filename) > $max_size) {
                                    exec("/usr/bin/jpegoptim -S$max_size -m$quality -s --all-progressive $img_folder$filename");
                                }
                                $img_size = filesize_nc($img_folder.$filename);
                                if ($img_size > $max_size) {
                                    decho("	$img_size failed, restoring");
                                    copy($img_folder.'orig/'.$filename,$img_folder.$filename);
                                } else {
                                    decho("	$img_size success!");
                                    break;
                                }
                                $quality = floor($quality * $step);
                            }
                            break;
                    }
                }
                if (filesize_nc($img_folder.$filename) > $max_size) {
                    decho("	trying fallback method");
                    list($extension,$scale) = $t[$mime]['fallback'];
                    exec("/usr/bin/convert -quality 90 -resize $scale% $img_folder$filename /tmp/$filename.$scale.$extension");
                    rename("/tmp/$filename.$scale.$extension","$img_folder$filename");
                    copy($img_folder.$filename,$img_folder.'orig/'.$filename);
                }
            }
            //exit;
        }
    }
}
