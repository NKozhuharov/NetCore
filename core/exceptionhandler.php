<?php
class ExceptionHandler{
    public function Success($e){
        global $Core;

        header('HTTP/1.1 200 OK', true, 200);
        if($Core->ajax){
            echo $this->catchMessage($e->getMessage(), true);
        }else{
            print_r($e->getMessage());
        }
    }

    public function Error($e){
        global $Core;

        header('HTTP/1.1 400 Bad Request', true, 400);
        if($Core->ajax){
            echo $this->catchMessage($e->getMessage());
        }else{
            print_r($e->getMessage());
        }
    }

    public function Exception($e){
        global $Core;

        header('HTTP/1.1 500 Internal Server Error', true, 500);
        if($Core->ajax){
            echo $this->catchMessage($e->getMessage());
        }else{
            print_r($e->getMessage());
        }
    }

    public function catchMessage($message, $isSuccess = false){
        echo '<script>alert("'.$message.'")</script>';
    }
}
?>