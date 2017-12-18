<?php
class Images{
    public $allowedSizes = array(
        'org' => array(
            'org'   => array('width' => false,   'height' => false),
        ),
        'width'  => array(
            '200'  => array('width' => 384,  'height' => false),
            '300'  => array('width' => 768,  'height' => false),
            '300'  => array('width' => 1152, 'height' => false),
            '500'  => array('width' => 1536, 'height' => false),
            '1920' => array('width' => 1920, 'height' => false)
        ),
        'height'=> array(
        ),
        'fixed' => array(
        ),
        false => array(
            false => array('width' => false, 'height' => false)
        )
    );

    public $sizeLimit     = 10485760;//10MB
    public $sizeLimitText = '10MB';
    public $uploadName    = 'imageUpload';
    public $extension     = '.jpg';
    public $currentFolder = false;
    public $folderNumber  = false;
    public $name          = false;
    public $file          = false;
    public $files         = array();
    public $orgMd5        = false;
    public $currentFile   = false;
    public $response      = array();
    public $upload        = false;
    public $width         = false;
    public $height        = false;
    public $type          = false;
    public $key           = false;
    public $imagick       = false;
    public $hasWatermark  = 0;
    public $watermark     = '/var/www/admin/www/img/watermark.png';

    public function __construct(){
       if((isset($_FILES[$this->uploadName], $_FILES[$this->uploadName]['tmp_name']) && is_array($_FILES[$this->uploadName]['tmp_name'])) || substr($_SERVER['REQUEST_URI'], 0, 7) == '/images'){
            if(isset($_FILES[$this->uploadName], $_FILES[$this->uploadName]['tmp_name']) && is_array($_FILES[$this->uploadName]['tmp_name'])){
                $count = count($_FILES[$this->uploadName]['tmp_name']);

                for($i=0; $i < $count; $i++){
                    $this->files[$i]['name']     = $_FILES[$this->uploadName]['name'][$i];
                    $this->files[$i]['type']     = $_FILES[$this->uploadName]['type'][$i];
                    $this->files[$i]['tmp_name'] = $_FILES[$this->uploadName]['tmp_name'][$i];
                    $this->files[$i]['error']    = $_FILES[$this->uploadName]['error'][$i];
                    $this->files[$i]['size']     = $_FILES[$this->uploadName]['size'][$i];
                }

                foreach($this->files as $f){
                    $this->currentFile = $f;
                    $this->checks();
                    $this->setImagick($this->file);
                    $this->convert();
                    $this->orgMd5 = md5($this->imagick->__toString());
                    $this->processOriginal();
                    $this->processResized();
                }
                if(count($this->response) == 1){
                    echo $this->response[0];
                }else{
                    echo json_encode($this->response);
                }
                die;
            }else{
                $this->checks();
                $this->setImagick($this->file);
                $this->orgMd5 = md5_file($this->file);
                $this->processResized();
            }
        }
    }

    public function checks(){
        global $Core;

        if(!isset($_SERVER['REQUEST_URI'])){
            throw new Exception($Core->language->error_unallowed_action);
        }elseif(!empty($this->currentFile)){
            if(empty($this->currentFile['tmp_name'])){
                throw new Exception($Core->language->error_image_is_empty);
            }elseif(!stristr(mime_content_type($this->currentFile['tmp_name']), 'image')){
                throw new Exception($Core->language->error_file_is_not_valid_image_file);
            }elseif(filesize($this->currentFile['tmp_name']) > $this->sizeLimit){
                throw new Exception($Core->language->error_image_must_not_be_bigger_than_.$this->sizeLimitText);
            }

            $this->folderNumber = $this->getDestinationFolder($Core->imagesStorage);
            $this->name         = substr($this->currentFile['name'], 0, strripos($this->currentFile['name'], '.'));
            $this->file         = urldecode($this->currentFile['tmp_name']);

            if(isset($_REQUEST['width']) || isset($_REQUEST['height']) || isset($_REQUEST['fixed'])){
                if(isset($_REQUEST['fixed'])){
                    $this->type          = 'fixed';
                    $this->key           = $_REQUEST['fixed'];
                }elseif(isset($_REQUEST['width'])){
                    $this->type          = 'width';
                    $this->key           = $_REQUEST['width'];
                }elseif(isset($_REQUEST['height'])){
                    $this->type          = 'height';
                    $this->key           = $_REQUEST['height'];
                }
                $this->currentFolder = $this->type.'/'.$this->key.'/'.$this->folderNumber.'/';
            }else{
                $this->type          = 'org';
                $this->key           = 'org';
                $this->currentFolder = $this->type.'/'.$this->folderNumber.'/';
            }
            //image upload
            $this->upload = true;
        }elseif(preg_match("~^/images/([a-zA-Z-]+)/([\d]+)/([\d]+)/(.*)~",$_SERVER['REQUEST_URI'] ,$m)){
            $this->type          = $m[1];
            $this->key           = $m[2];
            $this->name          = urldecode(substr($m[4], 0, strripos($m[4], '.')));
            $this->currentFolder = $m[1].'/'.$m[2].'/'.$m[3].'/';
            $this->file          = urldecode($Core->imagesStorage.$m[3].'/'.$m[4]);

            if(!is_file($this->file)){
                //unexisting file
                $Core->doOrDie();
            }
        }elseif(preg_match("~^/images/([a-zA-Z-]+)/([\d]+-[\d]+)/([\d]+)/(.*)~",$_SERVER['REQUEST_URI'] ,$m)){
            $this->type          = $m[1];
            $this->key           = $m[2];
            $this->name          = urldecode(substr($m[4], 0, strripos($m[4], '.')));
            $this->currentFolder = $m[1].'/'.$m[2].'/'.$m[3].'/';
            $this->file          = urldecode($Core->imagesStorage.$m[3].'/'.$m[4]);

            if(!is_file($this->file)){
                //unexisting file
                $Core->doOrDie();
            }
        }elseif(preg_match("~^/images/(org)/([\d]+)/(.*)~",$_SERVER['REQUEST_URI'] ,$m)){
            $this->type          = 'org';
            $this->key           = 'org';
            $this->name          = urldecode(substr($m[3], 0, strripos($m[3], '.')));
            $this->currentFolder = $m[1].'/'.$m[2].'/';
            $this->file          = urldecode($Core->imagesStorage.$m[2].'/'.$m[3]);

            if(!is_file($this->file)){
                //unexisting file
                $Core->doOrDie();
            }
        }else{
            //unexisting file
            $Core->doOrDie();
        }

        if(!isset($this->allowedSizes[$this->type])){
            throw new Exception($Core->language->error_unallowed_action);
        }elseif(!isset($this->allowedSizes[$this->type][$this->key])){
            throw new Exception($Core->language->unallowed_image_size.' '.$this->type);
        }else{
            $this->width  = $this->allowedSizes[$this->type][$this->key]['width'];
            $this->height = $this->allowedSizes[$this->type][$this->key]['height'];
        }
    }

    public function processOriginal(){
        global $Core;
        $exists = $this->exists($this->orgMd5);

        if(!$exists){
            if(isset($_REQUEST['watermark'])){
                $this->hasWatermark = 1;
            }else{
                $this->hasWatermark = 0;
            }
            $name = $this->getName();

            //keep original witout watermark
            $this->insert($Core->imagesStorage.$this->folderNumber.'/', $name, $this->orgMd5, 1, $this->hasWatermark);
        }else{
            $this->name = substr($exists, strripos($exists, '/')+1);
            $this->name = substr($this->name, 0, strripos($this->name, '.'));
        }
    }

    public function processResized(){
        global $Core;

        //resize image if not org wanted
        if($this->type != 'org'){
            $this->resize();
        }

        //add watermark if needed
        if($Core->db->result("SELECT `watermark` FROM `{$Core->dbName}`.`images` WHERE `hash` = '{$this->orgMd5}' AND `org` = 1")){
            $this->addWatermark();
            $this->hasWatermark = 1;
        }

        $file = $this->imagick->__toString();

        //check if resized image exists && upload is on
        //!!! imagick __toString returns different string from file_get_contents
        if(is_file($Core->imagesDir.$this->currentFolder.$this->name.$this->extension)){
            if(!$this->upload){
                $this->show($file);
            }
            $this->response[] = urldecode($Core->imagesWebDir.$this->currentFolder.$this->name.$this->extension);
            return;
        }

        $this->insert($Core->imagesDir.$this->currentFolder, $this->name, md5($file), NULL, $this->hasWatermark);

        if(!$this->upload){
            $this->show($file);
        }
        $this->response[] = urldecode($Core->imagesWebDir.$this->currentFolder.$this->name.$this->extension);
        return;
    }

    public function insert($folder, $name, $md5, $isOrg = NULL, $watermark = 0){
        global $Core;

        if(!is_dir($folder)){
            mkdir($folder, 0755, true);
        }

        $destination = $folder.$name.$this->extension;
        $this->imagick->writeImage($destination);

        $name = $Core->db->escape($name);
        if(!$isOrg){
            $destination = str_ireplace($Core->imagesDir, '', $Core->imagesWebDir.$destination);
        }
        $destination = $Core->db->escape($destination);

        $Core->db->query("INSERT INTO `{$Core->dbName}`.`images`(`name`, `hash`, `src`, `org`, `watermark`) VALUES('{$name}', '{$md5}', '{$destination}', '{$isOrg}', '{$watermark}')");
    }

    public function addWatermark(){
        // Open the watermark
        $watermark = new Imagick();
        $watermark->readImage($this->watermark);

        // Overlay the watermark on the original image
        $im_d = $this->imagick->getImageGeometry();
        $im_w = $im_d['width'];
        $im_h = $im_d['height'];

        $watermark_d = $watermark->getImageGeometry();
        $watermark_w = $watermark_d['width'];
        $watermark_h = $watermark_d['height'];

        $x_loc = $im_w/2 - $watermark_w/2;
        $y_loc = $im_h/2 - $watermark_h/2;

        $this->imagick->compositeImage($watermark, imagick::COMPOSITE_OVER, $x_loc, $y_loc);
    }

    public function resize(){
        //get  image size
        $cw = $this->imagick->getImageWidth();
        $ch = $this->imagick->getImageHeight();
        //get image resize ratio
        $wr = $this->width / $cw;
        $hr = $this->height / $ch;
        //resizes and crops
        if($wr >= $hr){
            $nh = floor($ch * $wr);
            $this->imagick->resizeImage($this->width, null , imagick::FILTER_LANCZOS,1);

            if($this->height != 0 && $nh > $this->height)
                $this->imagick->cropImage($this->width,$this->height,0,floor(($nh-$this->height)/2));
        }else{
            $nw = floor($cw * $hr);
            $this->imagick->resizeImage(null, $this->height, imagick::FILTER_LANCZOS,1);

            if($this->width != 0 && $nw > $this->width){
                $this->imagick->cropImage($this->width, $this->height, ceil(($nw-$this->width) /2 ),0);
            }
        }
    }

    public function getDestinationFolder($folder){
        global $Core;

        $imagesDir = $Core->imagesDir.$folder;
        $mainFoldersCount = count(glob($imagesDir.'*'));
        if($mainFoldersCount == 0){
            $folder = '1';
        }elseif(count(glob($imagesDir.$mainFoldersCount.'/*')) >= $Core->folderLimit){
            $folder = ($mainFoldersCount+1);
        }else{
            $folder = $mainFoldersCount;
        }

        return $folder;
    }

    public function getName(){
        global $Core;
        $name = trim(preg_replace('~\P{Xan}++~u', ' ', $this->name));
        $name = preg_replace("~\s+~", '-', strtolower($name));
        $name = substr($name, 0, 200);

        $count = 0;
        while($Core->db->result("SELECT `id` FROM `{$Core->dbName}`.`images` WHERE `name` = '$name' AND `org` = 1")){
            $count++;
            $postFix = substr($name, strripos($name, '-'));
            if($count > 1){
                $postFix = str_replace('-'.($count-1),'-'.$count, $postFix);
                $name = substr($name, 0, strripos($name, '-')).$postFix;
            }else{
                $name .= '-'.$count;
            }
        }
        $this->name = $name;

        return $name;
    }

    public function setImagick($file){
        $this->imagick = new Imagick($file);
    }

    public function convert(){
        if(strtolower($this->imagick->getImageFormat()) != 'jpeg'){
            $this->imagick->setImageFormat('jpeg');
        }
    }

    public function show($file){
        header("Content-Type: image/jpeg");
        echo $file;
        die;
    }

    public function exists($md5){
        global $Core;
        return $Core->db->result("SELECT `src` FROM `{$Core->dbName}`.`images` WHERE `hash` = '{$md5}'");
    }
    
    public function getIdBySrc($src,$validate = false){
        global $Core;
        $res = $Core->db->result("SELECT `id` FROM `{$Core->dbName}`.`images` WHERE `src` = '{$src}'");
        if($validate && empty($res)){
            throw new Error($Core->language->error_image_not_found);
        }
        return $res;
    }
    
    public function getSrcById($id,$validate = false){
        global $Core;
        $res = $Core->db->result("SELECT `src` FROM `{$Core->dbName}`.`images` WHERE `id` = {$id}");
        if($validate && empty($res)){
            throw new Error($Core->language->error_image_not_found);
        }
        return $res;
    }
}
?>