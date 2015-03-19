#!/usr/bin/env php
<?php
/**
 * This little app is designed to selectively override the settings in a
 * composer.json file with ones from an accompanying haydn.json file. It then
 * runs composer against the (temporary) result, allowing all command-line
 * arguments to pass through.
 *
 * It overrides package-versions and repositories, so that a developer on a
 * local machine can easily develop against local branches in local git
 * repositories.
 *
 * Note: This has not been tested or developed for Windows systems.
 * @author Darien Hager
 */

define("SRC_CONF", "composer.json");
define("MOD_CONF", "haydn.json");


function loadJson($path){
    if(!is_readable($path)){
        throw new Exception("File $path cannot be read",1);
    }

    $str = file_get_contents($path);
    try{
        $obj = decodeJson($str);
    }catch(Exception $e){
        $msg = jsonErrorMessage($e->getCode());
        throw new Exception("JSON error in $path: $msg",2,$e);
    }
    return $obj;
}

function decodeJson($str){
    $in_obj = @json_decode($str);
    if($in_obj === null){
        $lastErr = json_last_error();
        if($lastErr !== JSON_ERROR_NONE){
            throw new Exception("JSON error", $lastErr);
        }
    }
    return $in_obj;
}

function jsonErrorMessage($code){
    // For now lets be lazy and just expose the PHP constant name
    $constants = array(
        'JSON_ERROR_DEPTH',
        'JSON_ERROR_STATE_MISMATCH',
        'JSON_ERROR_CTRL_CHAR',
        'JSON_ERROR_SYNTAX',
        'JSON_ERROR_UTF8',
        'JSON_ERROR_RECURSION',
        'JSON_ERROR_INF_OR_NAN',
        'JSON_ERROR_UNSUPPORTED_TYPE'
    );

    foreach($constants as $cname){
        $val = constant($cname);
        if($val !== null && $val == $code){
            return $cname;
        }
    }
    return "UNKNOWN";
}

/**
 * @param object $base Decoded composer.json
 * @param object $modConf Decoded haydn.json
 */
function override($base,$modConf){
    /**
     * Add/replace packages with alternate versions, unless the version is blank in
     * which case the package is removed.
     */
    if(isset($modConf->{'override-require'}) && isset($base->{'require'})){
        foreach($modConf->{'override-require'} as $packageName => $version){
            if($version != ""){
                $base->{'require'}->{$packageName} = $version;
            }else{
                unset($base->{'require'}->{$packageName});
            }
        }
    }
    if(isset($modConf->{'override-require-dev'}) && isset($base->{'require-dev'})){
        foreach($modConf->{'override-require-dev'} as $packageName => $version){
            if($version != ""){
                $base->{'require-dev'}->{$packageName} = $version;
            }else{
                unset($base->{'require-dev'}->{$packageName});
            }
        }
    }
    /**
     * Replace repositories with the same URL, otherwise add new ones
     */
    if(isset($modConf->{'override-repositories'}) && isset($base->{'repositories'})){
        foreach($modConf->{'override-repositories'} as $url => $newRepo){
            $replaced = false;
            foreach($base->{'repositories'} as $index => $repo){
                if(!isset($repo->url)){
                    continue;
                }
                if($repo->url == $url){
                    $base->{'repositories'}[$index] = $newRepo;
                    $replaced = true;
                    break;
                }
            }
            if(!$replaced){
                // Add instead
                $base->{'repositories'}[] = $newRepo;
            }
        }

    }
}

function writeTemp($conf){
    $tempStr = json_encode($conf,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $tempPath = tempnam(sys_get_temp_dir(),"haydn_");
    $fh = fopen($tempPath, "w");
    fwrite($fh,$tempStr);
    fclose($fh);
    return $tempPath;
}

function launchComposer($newConf,$argv){
    /*
     * We assume that the `which` command will work.
     */
    $commandAlts = array(
        "composer",
        "composer.phar",
    );
    $cmd = null;
    foreach($commandAlts as $alt){
        if(commandExists($alt)){
            $cmd = $alt;
        }
    }
    if($cmd === null){
        throw new Exception("Unable to determine composer command, tried [".join(", ",$commandAlts)."]",3);
    }

    array_shift($argv); // Remove haydn.php command
    putenv("COMPOSER=$newConf");
    passthru("$cmd ". join(" ",$argv), $retVal);
    return $retVal;
}

function commandExists($cmd){
    exec("which $cmd",$output,$retVal);
    return $retVal === 0;
}

////////////////////////////////////////////////////////////////////////////////

try{
    $conf = loadJson(SRC_CONF);
    $modifier = loadJson(MOD_CONF);


    override($conf, $modifier);
    $tempPath = writeTemp($conf);
    $retVal = launchComposer($tempPath,$argv);
    exit($retVal);
}catch(Exception $e){
    fwrite(STDERR,$e->getMessage()."\n");
    exit(100 + $e->getCode());
}
