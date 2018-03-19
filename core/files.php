<?php
class Files extends Base{
    private $filePath   = false;
    public  $tableName  = 'files';

    public function __construct(){
        global $Core;

        $this->filePath = $Core->filesDir;
    }

    public function getByHash($hash){
        global $Core;

        $hash = $Core->db->escape($hash);
        if(empty($hash)){
            throw new exception ('Invalid hash!');
        }

        $Core->db->query("SELECT * FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `hash`='$hash'",$Core->cacheTime,'fetch_assoc',$result);
        if(empty($result)){
            throw new exception('File not found!');
        }
        return $result;
    }

    public function getBySrc($src){
        global $Core;

        $src = $Core->db->escape($src);

        if(!is_string($src) || empty($src)){
            throw new exception ('Invalid file source!');
        }

        $src   = explode('/', $src);
        $count = count($src);

        if($count < 2){
            throw new exception('Invalid file source!');
        }

        $hashedName = $src[$count-1];
        $dir        = $src[$count-2];

        $Core->db->query("SELECT * FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `dir` = '$dir' AND `hashed_name`='$hashedName'",$Core->cacheTime,'fetch_assoc',$result);
        if(empty($result)){
            throw new exception('File not found!');
        }
        return $result;
    }

    private function getFileHash($file){
        return sha1($file['name'].'_'.$file['size'].'_'.time());
    }

    private function getHashedName($file){
        return sha1($file['name'].'the_file_name'.time().uniqid());
    }

    private function insertFile($currentDirId,$file){
        global $Core;
        if(!is_dir($this->filePath.$currentDirId)){
            mkdir($this->filePath.$currentDirId,0755);
        }

        $fi = new FilesystemIterator($this->filePath.$currentDirId, FilesystemIterator::SKIP_DOTS);
        $fCount = iterator_count($fi);
        if($fCount < $Core->folderLimit){
            move_uploaded_file($file['tmp_name'],$this->filePath.$currentDirId.'/'.$file['hashed_name']);
            return $currentDirId;
        }
        return $this->insertFile($currentDirId + 1,$file);
    }

    public function addFiles($files, $insertInDb = true){
        global $Core;

        if(is_array($files['tmp_name'])){
            $files = $Core->globalfunctions->reArrangeRequestFiles($files);
        }else{
            $files = array($files);
        }

        foreach($files as $file){
            if(empty($file)){
                throw new exception('Please provide a valid file!');
            }elseif($file['error']){
                throw new exception($this->codeToMessage($file['error']));
            }

            $file['hash'] = $this->getFileHash($file);
            $file['hashed_name'] = $this->getHashedName($file);

            $currentDirId = $this->insertFile(1,$file);

            if(is_file($this->filePath.$currentDirId.'/'.$file['hashed_name'])){
                $inputArr = array(
                    'name' => $file['name'],
                    'hashed_name' => $file['hashed_name'],
                    'type' => $file['type'],
                    'dir'  => $currentDirId,
                    'hash' => $file['hash'],
                    'size' => $file['size']
                );

                if($insertInDb){
                    $this->add($inputArr);
                    $return[] = $Core->siteDomain.$Core->filesWebDir.$file['hash'];
                }else{
                    $return[] = $inputArr;
                }
            }
            else{
                throw new exception('Error occured! Please try again and if the problem persits contact support!');
            }
        }

        return $return;
    }

    public function insertFileIntoDb($inputArr){
        global $Core;
        if(!is_array($inputArr)){
            throw new exception('Please provide a valid array!');
        }

        $this->add($inputArr);

        return $Core->siteDomain.$Core->filesWebDir.$inputArr['hash'];
    }

    private function codeToMessage($code){
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by extension";
                break;
            default:
                $message = "Unknown upload error";
                break;
        }
        return $message;
    }

    public function typeMap($type,$name){
        if($type == 'application/octet-stream'){
            $name = strrev($name);
            $name = substr($name,0,strpos($name,'.'));
            $name = strrev($name);
            if($name == 'zip' || $name == 'rar'){
                return $name;
            }
        }
        if(stristr($type,'image')){
            return 'pic';
        }
        if($type == 'application/pdf'){
            return 'pdf';
        }
        if($type == 'application/vnd.ms-excel'){
            return 'excel';
        }
        if($type == 'application/vnd.openxmlformats-officedocument.pres'){
            return 'ppt';
        }
        if(stristr($type,'application') || stristr($type,'text')){
            return 'doc';
        }
        if(stristr($type,'video')){
            return 'mov';
        }
        if(stristr($type,'audio') || stristr($type,'music')){
            return 'music';
        }
        return 'unknown';
    }

    public function download($fileTo){
        global $Core;

        if(is_numeric($fileTo) && $file = $this->getById($fileTo)){
            $fileTo = $Core->filesDir.$file['dir'].'/'.$file['hashed_name'];
        }else{
            $file = $this->getBySrc($fileTo);
        }

        if(is_file($fileTo)){
            header("Content-Type: ".mime_content_type($fileTo));
            header("Content-Length:".filesize($fileTo));
            header('Content-Disposition: attachment;  filename="'.$file['name'].'"');
            readfile($fileTo);
            die;
        }else{
            throw new exception('File not found!');
        }
    }

    public function deleteFile($fileTo){
        global $Core;

        if(is_numeric($fileTo) && $file = $this->getById($fileTo)){
            $fileTo = $Core->filesDir.$file['dir'].'/'.$file['hashed_name'];
        }else{
            $file = $this->getBySrc($fileTo);
        }

        if(!is_file($fileTo)){
            throw new exception('File not found!');
        }

        unlink($fileTo);
        $this->delete($file['id']);
        return true;
    }
}
?>