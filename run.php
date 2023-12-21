<?php

#源视频目录
$base = "d:\\mp4";  
#移除后生成视频
$obase = "d:\\mp4_silence\\";
#处理过从源目录中移除到这里
$dbase = "d:\\mp4_done\\";

$dh = opendir($base);
while($i=readdir($dh)){
    if(substr($i,-3)!="mp4")continue;
    $fn = $base . "\\" . $i ;
    StripSilence($fn,$obase.$i);
    $cmd = sprintf("move %s %s%s",$fn,$dbase,$i);
    system($cmd);
}
closedir($dh);


function StripSilence($fn,$ofn){
    $cmd = sprintf("ffprobe -i %s 2>&1",$fn);
    $fp = popen($cmd,"r");
    //视频PTS校正因子，当tbr与fps不同时，需要进行修正
    $r = 1.0;
    while($l = fgets($fp)){
        if( preg_match("/ ([0-9\\.]+) fps, ([0-9\\.]+) tbr/",$l,$m) ){
            $r = floatval($m[2])/floatval($m[1]);
            break;
        }
    }
    pclose($fp);

    $cmd = sprintf("ffmpeg -i %s -af silencedetect=n=-10dB -vn  -f mp3 nul 2>&1",$fn);
    printf("%s\n",$cmd);
    $fp = popen($cmd,"r");
    $choice_start = 0 ; 
    $choice_end = 0 ; 
    $choice_arr = [];
    $btw = "";
    $delta = 0;
    while($l = fgets($fp)){
        if( preg_match("/silence_start: ([0-9]+)/",$l,$m) ){
            $choice_end = floatval($m[1]);
            if($choice_start != $choice_end ){
                $choice_arr[] = [ $choice_start , $choice_end];
                $delta += ($choice_end-$choice_start);
                if($btw != "" ) $btw .= "+";
                printf("%d->%d\n",$choice_start,$choice_end);
                if($choice_start != 0 ){
                    $btw .= sprintf("between(t,%d,%d)",$choice_start-1,$choice_end);
                }else{
                    $btw .= sprintf("between(t,%d,%d)",$choice_start,$choice_end);
                }
            }
            continue;
        }
        if( preg_match("/silence_end: ([0-9]+)/",$l,$m) ){
            $v = floatval($m[1]);
            $choice_start = $v ;
        }
    }
    pclose($fp);

    if($btw!="" && $delta > 15 ){
        $cmd = sprintf("ffmpeg -i %s -vf \"select='%s',setpts=N/(FR*TB)*%.3f\" -af \"aselect='%s',asetpts=N/(SR*TB)\" -r 10 -y %s.mp4",$fn,$btw,$r,$btw,$ofn);
        printf("CMD=%s\n",$cmd);
        system($cmd);
        return true;
    }
    return false;
}

