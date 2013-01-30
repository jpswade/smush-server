<?php

/*
 * Smushit class
 */

class Smushit {

    var $config = array(
        'results' => array('dir' => '%dir%%slash%results%slash%%file%'),
        'debug' => array('enabled' => 'yes'),
        'path' => array(
                'home' => '/path/to/user/public_html',
                'host' => 'http://example.com/smush.it/'
            ),
        'command' => array(
            'identify' => 'identify %src%',
            'convert' => 'convert %src% -quality 70 %dest%',
            'jpegtran' => 'jpegtran -copy none -progressive -outfile %dest% %src%',
            'gifsicle' => '/usr/local/bin/gifsicle -O2 %src% -o %dest%',
            'gifsicle_reduce_color' => '/usr/local/bin/gifsicle --colors 256 -O2 %src% > %dest%',
            'gifcolors' => "/usr/local/bin/gifsicle --color-info %src% | grep  'color table'",
            'topng' => 'convert %src% %dest%',
            'topng8' => 'convert %src% PNG8:%dest%',
            'pngcrush' => '/usr/local/bin/pngcrush -rem alla -brute -reduce %src% %dest%',
            'compress' => 'convert -sample %rate% %src% %dest%',
            'crop' => 'convert %src% -crop %params% %dest%',
            'env' => array('ua' => 'Smushit'),
        ),
        'operation' => array('convert_gif' => true)
    );
    
    var $debug = false;
    var $dbg = array();
    var $last_status = '';
    var $last_command = '';

    /* Structure
     * Completion of some initialization parameters
     */

    function Smushit($conf = false, $convertGif = null) {
        if ($conf) {
            loadConfig($conf);
        }
        //Whether to debug
        $debug = $this->config['debug']['enabled'];
        $this->debug = (strcasecmp($debug, 'yes') == 0);

        //this-> convertGif defaults to true
        if ($convertGif === null) {
            $convertGif = $this->config['operation']['convert_gif'];
        }
        $this->convertGif = (boolean) $convertGif;
        $this->host = $this->config['path']['host'];
    }
    
    //Load config
    function loadConfig ($conf) {
        //Read the default config file
        if (!file_exists($conf)) {
            /*
             * __FILE__For the current PHP script where the path + file name
             * dirname(__FILE__)Returns the current PHP script where the path
             * $conf config file path for the current path + file name
             */
            $conf = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.ini';
        }
        if (!file_exists($conf)) {
            return array('error', "Config file '$conf' does not exist.");
            return false;
        }
        //To obtain ini file multidimensional array
        $this->config = parse_ini_file($conf, true);
        return true;
    }

    //Create a duplicate files?
    function noDupes($dest) {

        // 256 is a cool number, no special reason to picking it, just making
        //  sure we don't get extremely long filenames.
        if (strlen($dest) > 256) {
            $path = dirname($dest);
            $dest = $path . DIRECTORY_SEPARATOR . substr(md5($dest), 0, 8);
        }

        $i = 1;
        $orig = $dest;

        while (file_exists($dest)) {
            // -4 is where the extension is, if exists
            // if not a normal extension, what the hell matters
            $dest = substr_replace($orig, $i++, -4, -4);
        }
        return $dest;
    }

    /*
     * To optimize picture file, and returns the array of data before and after
     *  optimization.
     */

    function optimize($filename, $output) {
        $this->dest = $output;
        //File Size
        $src_size = filesize($filename);
        if (!$src_size) {
            return array(
                'error' => 'Error reading the input file'
            );
        }
        //File Type
        $type = $this->getType($filename);

        // gif animations return one "gif" for every frame
        if (substr($type, 0, 6) === 'gifgif') {
            $type = 'gifgif';
        }
        if ('gif' === $type && false === $this->convertGif) {
            $type = 'gifgif';
        }
        $dest = '';
        /*
         * Document Type processing is divided into four
         *  jpg&jpeg,gif&bmp,gifgif,png.
         */
        switch ($type) {
            case 'jpg':
            case 'jpeg':
                $dest = $this->jpegtran($filename);
                break;
            case 'gif':
            case 'bmp': // yeah, I know!
                //Create a png file of the same name
                $png = $this->toPNG($filename);
                if (!$png) {
                    $error = "Failed to convert '$type' file to png format.";
                    return array('error' => $error);
                }
                //Need to create excessive alternative png image
                $dest = $this->crush($png, true);
                //If the transition png file converted to the target gif file
                /**
                 * do not delete the mid-file to increase the performance.
                  unlink($png);
                  rename($dest, $png);
                  $dest = $png;
                 */
                break;
            //Dynamic File
            case 'gifgif':
                //Get color value
                $gifColors = $this->getGifInfo($filename);
                //The progressive image optimization, color values ​​greater than
                // 256 additional color values ​​to optimize.
                if (256 < $gifColors) {
                    $dest = $this->gifsicle($filename, true);
                } else {
                    $dest = $this->gifsicle($filename);
                }
                break;
            case 'png':
                //Optimize PNG images
                $dest = $this->crush($filename);
                break;
            case '':
                $error = 'Cannot determine the type, is this an image?';
                return array('error' => $error);
                break;
            default:
                $error = 'Cannot do anythig about this file type:' . $type;
                return array('error' => $error);
        }
        //The optimized picture file size
        $dest_size = filesize($dest);
        if (!$dest_size) {
            return array('error' => 'Error writing the optimized file');
        }
        //Optimize the size of the percentage
        $percent = 100 * ($src_size - $dest_size) / $src_size;
        $percent = number_format($percent, 2);

        $result = array(
            'src' => $this->host . $filename,
            'src_size' => $src_size,
            'dest' => $this->host . $dest,
            'dest_size' => $dest_size,
            'percent' => $percent,
        );

        return $result;
    }

    /*
     * Through the the config.ini configuration files in the command line and
     *  the incoming parameters, assembled the results of the command line, 
     *  and return to the implementation of the command.
     */

    private function exec($command_name, $data) {
        /*
         * 0 => %test_home%
         * 1 => %user%
         */
        $find = array_keys($this->config['path']);
        foreach ($find as $k => $v) {
            $find[$k] = "%$v%";
        }
        //Get the list of commands in the configuration file
        $command = $this->config['command'][$command_name];
        $values = array_values($this->config['path']);
        //Find the value of $find $command, use $this->config ['path'] replace
        $command = str_replace($find, $values, $command);

        /*
         * Incoming $data parameter instead of the default placeholder in the
         *  command list
         */
        $find = array_keys($data);
        foreach ($find as $k => $v) {
            $find[$k] = "%$v%";
        }

        //Safe handling
        $data = array_map('escapeshellarg', $data);
        $command = str_replace($find, $data, $command);

        //error_log($command);
        exec($command, $ret, $status);
        //Status code after execution, command line
        $this->last_status = $status;
        $this->last_command = $command;
        //debug mode
        if ($this->debug) {
            $this->dbg[] = array(
                'command' => $command,
                'output' => $ret,
                'return_code' => $status
            );
        }
        if ($status == 1) {
            return -1;
        }
        //Return all perform output
        return $ret;
    }

    /*
     * Get the type of file
     */

    function getType($filename) {
        $ret = $this->exec('identify', array('src' => $filename));
        $retType = '';
        if (!($ret !== -1 && !empty($ret[0]))) {
            return false;
        }
        //if ($ret !== -1 && !empty($ret[0]))
        foreach ($ret as $retStr) {
            //$retStr = $ret[0];
            //Or two spaces between the content, can be understood as the
            // spaces before and after cleanup.
            $beginPos = strpos($retStr, ' ');
            $endPos = strpos($retStr, ' ', $beginPos + 1);
            $fType = substr($retStr, $beginPos + 1, $endPos - $beginPos - 1);
            //Converted to lowercase
            $retType .= strtolower($fType);
        }
        return $retType;
    }

    /*
     * According to the specified file to create a new png file (not optimized)
     */

    function toPNG($filename, $force8 = false) {
        //Create a directory
        $dest = $this->dest;
        if ($dest === -1) {
            return false;
        }
        //Target png files
        $dest = str_replace('.gif', '.png', $dest);
        //Should 'topng'
        $exec_which = $force8 ? 'topng8' : 'topng';
        /*
         * Call exec method, based on the parameters of the incoming
         *  perform topng command line
         * Create a new file of the same name png gif or bmp file
         */
        $ret = $this->exec($exec_which, array(
            'src' => $filename,
            'dest' => $dest
                )
        );
        if ($ret === -1) {
            return false;
        }
        return $dest;
    }

    /*
     * Optimize png images
     */

    function crush($filename, $already_in = false) {
        $dest = ($already_in) ? $this->noDupes($filename) : $this->dest;
        if ($dest === -1) {
            return false;
        }
        //Call the exec method, based on incoming parameters,
        // execute the the pngcrush command line, returns processing files
        $ret = $this->exec('pngcrush', array(
            'src' => $filename,
            'dest' => $dest
                )
        );
        if ($ret === -1) {
            return false;
        }
        return $dest;
    }

    function compress($filename, $rate) {
        $res = $this->exec('compress', array(
            'src' => $filename,
            'dest' => $filename,
            'rate' => $rate
                ));
    }

    function crop($filename, $params) {
        $res = $this->exec('crop', array(
            'src' => $filename,
            'dest' => $filename,
            'params' => $params
                ));
    }

    /*
     * Handling type jpg & jpeg files
     */

    function jpegtran($filename) {
        $dest = $this->dest;
        if ($dest === -1) {
            return false;
        }

        /*
         * Call exec method, based on the incoming parameters,
         *  perform the convert command line.
         * Create *. Tmp.jpeg new file
         */
        $ret = $this->exec('convert', array(
            'src' => $filename,
            'dest' => $filename . '.tmp.jpeg'
                )
        );
        if ($ret === -1) {
            return false;
        }
        //New archive copy for the target file
        $ret = $this->exec('jpegtran', array(
            'src' => $filename . '.tmp.jpeg',
            'dest' => $dest
                )
        );
        if ($ret === -1) {
            return false;
        }
        return $dest;
    }

    /*
     * Call exec method, according to the the incoming parameters perform
     *  gifsicle_reduce_color, or gifsicle command line.
     * Need to optimize the getGifInfo method returns the color values ​​to decide
     *  whether the number of colors.
     */

    function gifsicle($filename, $reduceColors = false) {
        $dest = $this->dest;
        if ($dest === -1) {
            return false;
        }
        //Decided to call the command line based on the color values
        $cmd = $reduceColors ? 'gifsicle_reduce_color' : 'gifsicle';

        $ret = $this->exec($cmd, array(
            'src' => $filename,
            'dest' => $dest
                ));
        if ($ret === -1) {
            return false;
        }
        return $dest;
    }

    function copy($src, $dest) {
        if (file_exists($src)) {
            return copy($src, $dest);
        }
        if (!strstr($src, 'http://') && !strstr($src, 'https://')) {
            return false;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($src);
            $fp = fopen($dest, 'w');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->config['env']['ua']);
            curl_exec($ch);
            $mimetype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            fclose($fp);
            if (is_null($mimetype) || strncmp($mimetype, 'image/', 6) !== 0) {
                // not an image
                if (file_exists($dest)) {
                    unlink($dest);
                }
                return false;
            }
            return file_exists($dest);
        } else {
            return copy($src, $dest);
        }
    }

    function getDirectoryListing($dir) {
        if (!is_dir($dir)) {
            return array('error', 'Not a directory');
        }
        $files = array();
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file == '.' || $file == '..')
                    continue;
                $path = trim($dir, DIRECTORY_SEPARATOR);
                $file = $path . DIRECTORY_SEPARATOR . $file;
                if (is_dir($file)) {
                    continue;
                }
                $files[] = $file;
            }
            closedir($dh);
        }
        return $files;
    }

    function isTheSameImage($file1, $file2) {
        $i1 = imagecreatefromstring(file_get_contents($file1));
        $i2 = imagecreatefromstring(file_get_contents($file2));

        $sx1 = imagesx($i1);
        $sy1 = imagesy($i1);
        if ($sx1 != imagesx($i2) || $sy1 != imagesy($i2)) {
            //image geometric size does not match
            return false;
        }

        for ($x = 0; $x < $sx1; $x++) {
            for ($y = 0; $y < $sy1; $y++) {

                $rgb1 = imagecolorat($i1, $x, $y);
                $pix1 = imagecolorsforindex($i1, $rgb1);

                $rgb2 = imagecolorat($i2, $x, $y);
                $pix2 = imagecolorsforindex($i2, $rgb2);

                if ($pix1 != $pix2) {
                    return false;
                }
            }
        }
        return true;
    }

    /*
     * Obtain the the gif image's color values​​, and returns
     */

    function getGifInfo($gifPic) {
        $ret = $this->exec('gifcolors', array('src' => $gifPic));
        $totalColors = 0;
        foreach ($ret as $retStr) {
            //$retStr = $ret[0];
            $beginPos = strpos($retStr, '[');
            $endPos = strpos($retStr, ']');
            $start = $beginPos + 1;
            $length = $endPos - $beginPos - 1;
            $colorNum = (int) substr($retStr, $start, $lenth);
            $totalColors += $colorNum;
        }
        return $totalColors;
    }

}

//eof