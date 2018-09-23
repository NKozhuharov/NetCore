<?php 
    class Mailer{
        public static function send($to,$subject,$html){
            error_reporting(1);
            ini_set('display_errors',1);
            
            #$text=preg_replace("~<br\s+(/|)>~",PHP_EOL,$text);
            #$text=strip_tags($text);
            //email-smtp.eu-west-1.amazonaws.com
            ini_set("SMTP", "email-smtp.eu-west-1.amazonaws.com");
            ini_set("sendmail_from", "Admin@streamservant.com");

            $message = "this is a test message";

            $headers = "From: Admin@streamservant.com";


            mail("rarebutcommon@gmail.com", "Testing", $message, $headers);
            echo "Check your email now....<BR/>";
            /*$text=str_replace(array("DOMAIN","TOKEN","USERNAME"),array(($_SERVER['HTTP_HOST']),$key,ucfirst($username)),$text);
            $html=str_replace(array("DOMAIN","TOKEN","USERNAME"),array(($_SERVER['HTTP_HOST']),$key,ucfirst($username)),$html);
            
            $headers=array();
            $headers['From']    = 'Accounts StreamServant <accounts@'.$_SERVER['HTTP_HOST'].'>';
            $headers['To']      = "$email";
            $headers['Subject'] = str_replace("DOMAIN",$_SERVER['HTTP_HOST'],$subject);
            $headers['Return-path'] = 'accounts@aircloud.to';
            
            #$headers['Content-Type'] = "text/html; charset=utf-8";
            $headers['Content-Type'] = 'multipart/alternative';
            
            $headers['Content-Transfer-Encoding'] = "8bit";
            $headers['MIME-Version'] = '1.0';
            
            #$body=str_replace(array("DOMAIN","TOKEN","USERNAME"),array(($_SERVER['HTTP_HOST']),$key,ucfirst($username)),$text);

            #$mail_object=@Mail::factory('sendmail');
            $mime = new Mail_mime(array('eol' => PHP_EOL));
            $mime->setTXTBody($text);
            $mime->setHTMLBody($html);
            
            $body = $mime->get();
            $hdrs = $mime->headers($headers);

            $mail =& Mail::factory('mail');
            $mail->send($email, $hdrs, $body);
            
            #@$mail_object->send($email, $headers, $body);
            */
        }
    }
?>