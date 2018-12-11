<?php
    require_once "Mail.php";
    require_once("Mail/mime.php");
    class PearMailer{
        public $from         = 'Admin Streamservant <support@streamservant.com>';
        public $contactEmail = 'Admin Streamservant <support@streamservant.com>';
        #public $contactEmail = 'rarebutcommon@gmail.com';

        public $smtp         = [
             'host' => 'email-smtp.eu-west-1.amazonaws.com'
            ,'auth' => true
            /*
                WARNING:
                    Amazon IAM Does Not Provide the SMTP password directly but rather a private key which you can use to generate the password
                    See: https://docs.aws.amazon.com/ses/latest/DeveloperGuide/smtp-credentials.html

            */
            ,'username' => 'AKIAIMTUEVHFGBZTPK3Q'
            ,'password' => 'AkrNBjLJ80kw7URSbiKM3zYVKER1aQVcTh/4DYG1AuQy'
        ];

        public function __construct(){

        }
        public function send($input,$headers=false){
            $default=array(
                 'from' => $this->from
            );
            $required=array('from','to','subject','html','text');
            $final=array_replace($default,$input);

            foreach($required as $k => $v){
                if(!isset($final[$v]) || empty($final[$v])){
                    throw new Exception("PearMailer line ".__LINE__.": Missing Input[$v]");
                }
            }

            if(isset($headers['From']) || isset($headers['To']) || isset($headers['Subject'])){
                throw new Exception("\$header[from|to|subject] are taken from the \$input please unset them from headers due to it being a confusion possibility.");
            }

            $headers['From']    = $final['from'];
            $headers['To']      = $final['to'];
            $headers['Subject'] = $final['subject'];

            $mime = new Mail_mime(array('eol' => PHP_EOL));
            $mime->setTXTBody($final['text']);
            $mime->setHTMLBody($final['html']);

            $body = $mime->get();
            $hdrs = $mime->headers($headers);

            $smtp = Mail::factory('smtp',$this->smtp);
            $mail=$smtp->send($final['to'], $hdrs, $body);

            if(PEAR::isError($mail)){
                #echo("<p>".$mail->getMessage()."</p>");
                throw new Exception($mail->getMessage());
            }
            return true;
        }
    }
?>
