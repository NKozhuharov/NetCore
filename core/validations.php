<?php
class Validations{
    public static function validateEmail($email){
        global $Core;
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            throw new exception($Core->language->error_email_is_not_valid);
        }
        return true;
    }

    public static function validatePhones($phones){
        global $Core;
        if(!is_array($phones)){
            $phones = array($phones);
        }
        foreach($phones as $p){
            if(empty($p)){
                throw new exception($Core->language->error_phone_field_cannot_be_empty);
            }
            if(!empty($p) && (!preg_match("{(\+|)[0-9 \-]+}",$p) || strlen($p)<3)){
                throw new exception($Core->language->error_phone_field_invalid);   
            }
        }
        return true;
    }
}
?>