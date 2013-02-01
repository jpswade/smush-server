<?php

/*
 * Smush class
 */

class Smush {

    var $config = array(
        'results' => array('dir' => '%dir%%slash%results%slash%%file%'),
        'debug' => array('enabled' => 'yes'),
        'command' => array(
            'identify' => '/usr/bin/identify %src%',
            'convert' => '/usr/bin/convert %src% -quality 70 %dest%',
            'jpegtran' => 'jpegtran -copy none -progressive -outfile %dest% %src%',
            'gifsicle' => '/usr/bin/gifsicle -O2 %src% -o %dest%',
            'gifsicle_reduce_color' => '/usr/bin/gifsicle--colors 256 -O2 %src% > %dest%',
            'gifcolors' => "/usr/bin/gifsicle --color-info %src% | grep  'color table'",
            'topng' => '/usr/bin/convert %src% %dest%',
            'topng8' => '/usr/bin/convert %src% PNG8:%dest%',
            'pngcrush' => '/usr/local/bin/pngcrush -rem alla -brute -reduce %src% %dest%',
            'compress' => '/usr/bin/convert -sample %rate% %src% %dest%',
            'crop' => '/usr/bin/convert %src% -crop %params% %dest%'
        ),
        'path' => array(
            'upload' => 'upload',
            'results' => 'results'),
        'env' => array('ua' => 'Smush', 'prefix' => 'smush'),
        'operation' => array('convert_gif' => true)
    );
    var $debug;
    var $dbg = array();
    var $last_status = '';
    var $last_command = '';
    //var $result = array();
    var $originalsrc = null;
    var $res = null;
    var $dest = null;

    /* Structure
     * Completion of some initialization parameters
     */

    function Smush($conf = false, $convertGif = null) {
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
    }

    //Load config
    function loadConfig($conf) {
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
            user_error("Config file '$conf' does not exist.");
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
        //if ($this->debug) echo "output($output)";
        $this->dest = $output;
        //File Size
        //if ($this->debug) echo "filename($filename)";
        if (!file_exists($filename)) {
            user_error('Error reading the input file');
            return false;
        }
        $src_size = filesize($filename);
        if (!$src_size) {
            user_error('Error reading the input file');
            return false;
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
                    user_error("Failed to convert '$type' file to png format.");
                    return false;
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
                user_error('Cannot determine the type, is this an image?');
                return false;
                break;
            default:
                user_error('Cannot do anything about this file type: ' . $type);
                return false;
        }
        //The optimized picture file size
        //if ($this->debug) echo "dest($dest)";
        $dest_size = filesize($dest);
        if (!$dest_size) {
            user_error('Error writing the optimized file');
            return false;
        }
        //Optimize the size of the percentage
        $percent = 100 * ($src_size - $dest_size) / $src_size;
        $percent = number_format($percent, 2);

        $result = array();
        $result['src'] = $this->originalsrc ? $this->originalsrc : $filename;
        $result['src_size'] = $src_size;
        if ($percent > 0) {
            $result['dest'] = $dest;
            $result['dest_size'] = $dest_size;
            $result['percent'] = $percent;
        } else {
            unlink($dest);
            $result['error'] = 'No savings';
            $result['dest_size'] = -1;
        }

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
        /* $find = array_keys($this->config['path']);
          foreach ($find as $k => $v) {
          $find[$k] = "%$v%";
          } */
        //Get the list of commands in the configuration file
        $command = $this->config['command'][$command_name];
        /* $values = array_values($this->config['path']);
          //Find the value of $find $command, use $this->config ['path'] replace
          $command = str_replace($find, $values, $command); */

        /* Check command exists */
        $command_exec = trim(strtok($command, ' '));
        $which = shell_exec("which $command_exec");
        if (!$which) {
            user_error("Command '$command_exec' does not exist.");
            die();
        }

        /*
         * Incoming $data parameter instead of the default placeholder in the
         *  command list
         */
        $find = array_keys($data);
        foreach ($find as $k => $v) {
            $find[$k] = "%$v%";
        }

        //Safe handling
        //if ($this->debug) { var_dump($data); }
        $data = array_map('escapeshellarg', $data);
        $command = str_replace($find, $data, $command);

        //if ($this->debug) { error_log($command); }
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
        if (is_uploaded_file($src)) {
            //move uploaded file
            move_uploaded_file($src, $dest);
        } elseif (filter_var($src, FILTER_VALIDATE_URL)) {
            //must be a valid url
            if (function_exists('curl_init')) {
                //copy from url (using curl)
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
            } else {
                //copy using wget
                shell_exec(sprintf('wget -O %s %s 2>&1 1> /dev/null', $file, $url));
            }
        } elseif (file_exists($src)) {
            //copy from file
            copy($src, $dest);
        }
        //return filename
        return is_file($dest) ? $dest : false;
    }

    function getDirectoryListing($dir) {
        if (!is_dir($dir)) {
            user_error('Not a directory');
            return false;
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

    /*
     * Web service
     */

    function webservice($img = false, $id = '') {
        if (!$this->res && $img) {
            $this->res = $this->upload($img);
        }
        if (!$img || !$this->res) {
            $result['src'] = $img;
            $result['error'] = 'Could not get the image';
            return $result;
        }
        $src = $this->res;
        $resultspath = $this->config['path']['results'] . DIRECTORY_SEPARATOR;
        $dest = $resultspath . basename($this->res);
        //if ($this->debug) echo "dest($dest)";
        $result = $this->optimize($src, $dest);
        if (isset($result['dest'])) {
            $result['dest'] = $this->getURL($dest);
        }
        return $result;
    }

    /*
     * Upload function
     */

    function upload($src = false) {
        // Check paths for uploading
        $this->checkPaths();
        //if it's an array, we only want one.
        if (is_array($src)) {
            $src = array_shift($src);
        }
        //get path
        $uploadpath = realpath($this->config['path']['upload']) . DIRECTORY_SEPARATOR;
        //get prefix
        $prefix = $this->config['env']['prefix'];
        //if it's a string, act
        if ($src && is_string($src)) {
            //web upload
            //check for multiple URLS?
            $matches = array();
            preg_match('/^https?:\/\/[\S]+/i', $src, $matches);
            $url = array_shift($matches);
            $this->originalsrc = $url;
            $filename = parse_url($url, PHP_URL_PATH);
            if (!$filename) {
                $filename = 'image';
            }
            if (!strrchr($filename, '.')) {
                $filename .= '.png';
            }
            $hash = hash('crc32b', uniqid());
            $file = urlencode('/' . $prefix . '/' . $filename);
            $srcfile = $uploadpath . $hash . $file;
            //if ($this->debug) echo "copy($url, $srcfile)";
            $this->res = $this->copy($url, $srcfile);
        } elseif ($_FILES) {
            $fileInfo = array_shift($_FILES);
            $fileTemp = $fileInfo['tmp_name'];
            $filename = $fileInfo['name'];
            $hash = hash('crc32b', uniqid());
            $file = urlencode('/' . $prefix . '/' . $file);
            $srcfile = $uploadpath . $hash . $file;
            $this->res = $this->copy($fileTemp, $srcfile);
        } else {
            return false;
        }
        return $this->res;
    }
    
    //Check paths
    function checkPaths() {
        $config = & $this->config;
        //paths
        if (!isset($config['path']['upload'])) {
            user_error('Upload path is not set in config.');
            die();
        }
        $uploadpath = $config['path']['upload'] . DIRECTORY_SEPARATOR;

        if (!isset($config['path']['results'])) {
            user_error('Results path is not set in config.');
            die();
        }
        $resultspath = $config['path']['results'] . DIRECTORY_SEPARATOR;
        //checks
        if (!is_dir($uploadpath)) {
            user_error('Upload path is invalid');
            die();
        }
        if (!is_writeable($uploadpath)) {
            user_error('Upload path is NOT writable');
            die();
        }
        if (!is_dir($resultspath)) {
            user_error('Results path is invalid');
            die();
        }
        if (!is_writeable($resultspath)) {
            user_error('Results path is NOT writable');
            die();
        }
    }

    function getUrl($path = false) {
        if (isset($_SERVER['HTTP_HOST'])) {
            $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
            $url = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            return $url . '/' . ltrim($path, '/');
        }
    }

}

//end