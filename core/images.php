<?php
class Images{
    public $allowedSizes = array(
        'org' => array(
            'org'   => array('width' => false, 'height' => false),
        ),
        'width'  => array(
            '1920' => array('width' => 1920, 'height' => false)
        ),
        'height'=> array(
            '1080' => array('width' => false, 'height' => 1080)
        ),
        'fixed' => array(
            '1920-1080' => array('width' => 1920, 'height' => 1080)
        ),
        false => array(
            false => array('width' => false, 'height' => false)
        )
    );

    public $sizeLimit      = 10485760; //10MB
    public $sizeLimitText  = '10MB';
    public $format         = 'jpg';
    public $uploadName     = 'imageUpload';
    public $onlyUpload     = false;
    public $showResponse   = true;
    public $allowDuplicate = true;
    public $dieWhenDone    = true;
    public $currentFolder  = false;
    public $folderNumber   = false;
    public $name           = false;
    public $file           = false;
    public $files          = array();
    public $orgMd5         = false;
    public $currentFile    = false;
    public $response       = array();
    public $upload         = false; //file upload or http request
    public $width          = false;
    public $height         = false;
    public $type           = false;
    public $key            = false;
    public $imagick        = false;
    public $hasWatermark   = 0;
    public $watermark      = false; //'/var/www/site/www/img/watermark.png';

    private function _404(){
        header("404 Not Found",1,404);
        exit;
    }
    
    public function __construct(){
        global $Core;

        if(isset($_FILES[$this->uploadName], $_FILES[$this->uploadName]['tmp_name'])){
            if(is_array($_FILES[$this->uploadName]['tmp_name'])){
                $this->files = $Core->globalfunctions->reArrangeRequestFiles($_FILES[$this->uploadName]);
            }else{
                $this->files = array($_FILES[$this->uploadName]);
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
                if($this->showResponse){
                    echo $this->response[0];
                }else{
                    return $this->response[0];
                }
            }else{
                if($this->showResponse){
                    echo json_encode($this->response);
                }else{
                    return $this->response;
                }
            }

            if($this->dieWhenDone){
                die;
            }
       }elseif(substr($_SERVER['REQUEST_URI'], 0, 7) == '/images'){
            $this->checks();
            $this->setImagick($this->file);
            $this->orgMd5 = md5_file($this->file);
            $this->processResized();
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
            }elseif($this->sizeLimit && filesize($this->currentFile['tmp_name']) > $this->sizeLimit){
                throw new Exception($Core->language->error_image_must_not_be_bigger_than_.$this->sizeLimitText);
            }

            $this->folderNumber = $Core->globalfunctions->getFolder($Core->imagesStorage, true);
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
        }elseif(preg_match("~^/images/(width|height)/([\d]+)/([\d]+)/(.*)~",$_SERVER['REQUEST_URI'] ,$m)){
            $this->type          = $m[1];
            $this->key           = $m[2];
            $this->name          = urldecode(substr($m[4], 0, strripos($m[4], '.')));
            $this->currentFolder = $m[1].'/'.$m[2].'/'.$m[3].'/';
            $this->file          = urldecode($Core->imagesStorage.$m[3].'/'.$m[4]);

            if(!$this->allowedSizes){
                if($m[1] == 'width'){
                    $this->width  = $m[2];
                }elseif($m[1] == 'height'){
                    $this->height = $m[2];
                }
            }

            if(!is_file($this->file)){
                //unexisting file
                $this->_404();
            }
        }elseif(preg_match("~^/images/(fixed)/([\d]+-[\d]+)/([\d]+)/(.*)~",$_SERVER['REQUEST_URI'] ,$m)){
            $this->type          = $m[1];
            $this->key           = $m[2];
            $this->name          = urldecode(substr($m[4], 0, strripos($m[4], '.')));
            $this->currentFolder = $m[1].'/'.$m[2].'/'.$m[3].'/';
            $this->file          = urldecode($Core->imagesStorage.$m[3].'/'.$m[4]);

            if(!$this->allowedSizes){
                $rato = explode('-', $m[2]);
                if(count($rato == 2)){
                    $this->width  = $rato[0];
                    $this->height = $rato[1];
                }
            }

            if(!is_file($this->file)){
                //unexisting file
                $this->_404();
            }
        }elseif(preg_match("~^/images/(org)/([\d]+)/(.*)~",$_SERVER['REQUEST_URI'] ,$m)){
            $this->type          = 'org';
            $this->key           = 'org';
            $this->name          = urldecode(substr($m[3], 0, strripos($m[3], '.')));
            $this->currentFolder = $m[1].'/'.$m[2].'/';
            $this->file          = urldecode($Core->imagesStorage.$m[2].'/'.$m[3]);

            if(!is_file($this->file)){
                //unexisting file
                $this->_404();
            }
        }else{
            //unexisting file
            $this->_404();
        }

        if($this->allowedSizes && !isset($this->allowedSizes[$this->type])){
            throw new Exception($Core->language->error_unallowed_action);
        }elseif($this->allowedSizes && !isset($this->allowedSizes[$this->type][$this->key])){
            throw new Exception($Core->language->unallowed_image_size);
        }elseif($this->allowedSizes){
            $this->width  = $this->allowedSizes[$this->type][$this->key]['width'];
            $this->height = $this->allowedSizes[$this->type][$this->key]['height'];
        }
    }

    public function processOriginal(){
        global $Core;
        $exists = $this->exists($this->orgMd5);

        if(!$this->allowDuplicate && $exists){
            throw new Exception($Core->language->error_this_image_already_exists);
        }

        if(!$exists){
            if(isset($_REQUEST['watermark'])){
                $this->hasWatermark = 1;
            }else{
                $this->hasWatermark = 0;
            }
            $this->name = $Core->globalfunctions->getHref($this->name, 'images', 'name');

            //keep original witout watermark
            $this->insert($Core->imagesStorage.$this->folderNumber.'/', $this->name, $this->orgMd5, 1, $this->hasWatermark);
        }else{
            $this->name = substr($exists, strripos($exists, '/')+1);
            $this->name = substr($this->name, 0, strripos($this->name, '.'));
        }

        //upload only org image in non-web folder and return file path
        if($this->onlyUpload){
            $this->response[] = $Core->imagesStorage.$this->folderNumber.'/'.$this->name.'.'.$this->format;
        }
    }

    public function processResized(){
        global $Core;

        //upload only org image in non-web folder and return file path
        if($this->onlyUpload){
            return;
        }

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
        if(is_file($Core->imagesDir.$this->currentFolder.$this->name.'.'.$this->format)){
            if(!$this->upload){
                $this->show($file);
            }
            $this->response[] = urldecode($Core->imagesWebDir.$this->currentFolder.$this->name.'.'.$this->format);
            return;
        }

        $this->insert($Core->imagesDir.$this->currentFolder, $this->name, md5($file), NULL, $this->hasWatermark);

        if(!$this->upload){
            $this->show($file);
        }
        $this->response[] = urldecode($Core->imagesWebDir.$this->currentFolder.$this->name.'.'.$this->format);
        return;
    }

    public function insert($folder, $name, $md5, $isOrg = NULL, $watermark = 0){
        global $Core;
        if(!is_dir($folder)){
            mkdir($folder, 0755, true);
        }

        $destination = $folder.$name.'.'.$this->format;
        $this->imagick->writeImage($destination);

        $name = $Core->db->escape($name);
        if(!$isOrg){
            $destination = str_ireplace($Core->imagesDir, '', $Core->imagesWebDir.$destination);
        }
        $destination = $Core->db->escape($destination);

        $Core->db->query("INSERT INTO `{$Core->dbName}`.`images`(`name`, `hash`, `src`, `org`, `watermark`) VALUES('{$name}', '{$md5}', '{$destination}', '{$isOrg}', '{$watermark}')");
    }

    public function addWatermark(){
        global $Core;

        //check if watermark file exists
        if(!is_file($this->watermark)){
            throw new Exception($Core->language->error_invalid_watermark_file);
        }
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

    public function setImagick($file){
        $this->imagick = new Imagick($file);
    }

    public function convert(){
        if(strtolower($this->imagick->getImageFormat()) != $this->format){
            $this->imagick->setImageFormat($this->format);
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