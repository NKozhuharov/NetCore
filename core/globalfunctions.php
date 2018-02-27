<?php
class GlobalFunctions{
    public function catchMessage($message, $success = false){
        if(stristr($message, '<script')){
            echo $message;
            return;
        }
        ?>
        <script id="modal-message-wrap" type="text/javascript">
            modal.<?=$success ? 'success' : 'error'?>({message: '<?=$message?>'});
            $('#modal-message-wrap').remove()
        </script>
        <?php return;
        // depricated
        /*
            ?>
            <div class="modal-message-wrap">
                <div id="modal-message" class="modal modal-message">
                    <?php if($success){; ?>
                        <div class="modal-content">
                            <i class="zmdi zmdi-check"></i>
                            <h2>Success</h2>
                            <p><?php echo $message; ?></p>
                            <a href="#!" class="modal-action blue modal-close waves-effect waves-green btn-flat">Close</a>
                        </div>
                    <?php }else{; ?>
                        <div class="modal-content">
                            <i class="zmdi zmdi-close"></i>
                            <h2>Error</h2>
                            <p><?php echo $message; ?></p>
                            <a href="#!" class="modal-action blue modal-close waves-effect waves-green btn-flat">Close</a>
                        </div>
                    <?php }; ?>
                </div>
                <script>$('#modal-message').modal({complete: function(){$(".modal-message-wrap").remove();}}).modal('open');</script>
            </div>
            <?php
        */
    }

    function curl($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $r = curl_exec($ch);
        $er = curl_error($ch);
        curl_close($ch);
        if($er){
            throw new Error($er);
        }

        return $r;
    }

    public function getUrl($string){
        $string = trim(preg_replace('~\P{Xan}++~u', ' ', $string));
        $string = preg_replace("~\s+~", '-', strtolower($string));
        $string = substr($string, 0, 200);
        return $string;
    }

    public function getHref($title, $table, $field, $id = false){
        global $Core;
        $url = $this->getUrl($title);
        $url = substr($url, 0, 200);
        $and = '';
        if($id && is_numeric($id)){
            $and = " `id` != '$id' AND ";
        }

        $count = 0;
        while($Core->db->result("SELECT `id` FROM `{$Core->dbName}`.`$table` WHERE $and `$field` = '$url'")){
            $count++;
            $postFix = substr($url, strripos($url, '-'));
            if($count > 1){
                $postFix = str_replace('-'.($count-1),'-'.$count, $postFix);
                $url = substr($url, 0, strripos($url, '-')).$postFix;
            }else{
                $url .= '-'.$count;
            }
        }
        return $url;
    }

    //create swiper
    public function swiper(array $imges, $pagination = true, $navigation = true){
        ob_start();
        ?>
        <div class="swiper-container">
            <div class="swiper-wrapper">
            <?php
            if(!empty($imges)){
                foreach($imges as $img){
                    ?>
                    <div class="swiper-slide">
                        <img src="<?php echo $img?>" class="swiper-lazy">
                        <div class="swiper-lazy-preloader swiper-lazy-preloader-white"></div>
                    </div>
                    <?php
                }
            }
            ?>
            </div>
            <?php if($pagination){ ?>
                <div class="swiper-pagination"></div>
            <?php } ?>

            <?php if($navigation){ ?>
                <!-- Add Arrows -->
                <div class="swiper-button-next swiper-button-white"></div>
                <div class="swiper-button-prev swiper-button-white"></div>
            <?php } ?>

            <!--Close button-->
            <i class="fa fa-window-close-o swiper-full-close"></i>
        </div>
        <?php
        $sw = ob_get_contents();
        ob_end_clean();

        return $sw;
    }

    //search array key for given value
    public function arraySearch($array, $key, $value){
        $results = array();

        if(is_array($array)){
            if(isset($array[$key]) && $array[$key] == $value){
                $results[] = $array;
            }

            foreach($array as $subarray){
                $results = array_merge($results, $this->arraySearch($subarray, $key, $value));
            }
        }

        return $results;
    }

    //sends email; requires PHPMailer library to work!
    public function sendEmail($from = false, $fromName = false, $subject = false, $addAddress = false, $body = false, $isHTML = false, $attachment = false, $isAdnmin = false){
        global $Core;
        require_once('/var/www/classes/core/PHPMailer-master/PHPMailerAutoload.php');
        $mail = new PHPMailer;
        $mail->isSMTP();

        if(isset($Core->mailConfig) && $Core->mailConfig){
            $mail->SMTPAuth   = true;
            $mail->SMTPSecure = $Core->mailConfig['SMTPSecure'];
            $mail->Username   = $Core->mailConfig['Username'];
            $mail->Password   = $Core->mailConfig['Password'];
            $mail->Host       = $Core->mailConfig['Host'];
            $mail->Port       = $Core->mailConfig['Port'];;
        }else{
            throw new Exception('Please set core $mailConfig variable');
        }

        if($isHTML){
            $mail->isHTML(true);
        }
        if($body){
            $mail->Body = $body;
        }

        if(is_array($attachment)){
            foreach($attachment as $att){
                $mail->AddAttachment($att);
            }
        }else{
            $mail->AddAttachment($attachment);
        }

        if(is_array($addAddress)){
            foreach($addAddress as $adr){
                $mail->addAddress($adr);
            }
        }else{
            $mail->addAddress($addAddress);
        }

        $mail->CharSet = "UTF-8";
        $mail->Subject = $subject;
        $mail->From = $from;
        $mail->FromName = $fromName;

        if(!$mail->send()){
            if($isAdnmin){
                $msg = $mail->ErrorInfo;
            }else{
                $msg = $Core->language->generalEmailError;
            }
            throw new Error($msg);
        }
    }

    //pagination function
    public function drawPagination($resultsCount, $url, $currentPage = false, $firstLast = false, $html = array(), $firstPage = false){
        global $Core;

        if($resultsCount <= $Core->itemsPerPage){
            return false;
        }

        if(!$currentPage){
            $currentPage = $Core->rewrite->currentPage;
        }elseif($currentPage <= 0){
            $Core->doOrDie();
        }

        $pagesCount = ceil($resultsCount / $Core->itemsPerPage);

        if($Core->numberOfPagesInPagination > $pagesCount){
            $numberOfPagesInPagination = $pagesCount;
        }else{
            $numberOfPagesInPagination = $Core->numberOfPagesInPagination;
        }

        $current = $currentPage;

        echo '<ul'.(isset($html['ul_class']) ? ' class="'.$html['ul_class'].'"' : '').'>';
        if($currentPage>1){
            if($firstLast){
                echo('<li'.(isset($html['first'], $html['first']['class']) ? ' class="'.$html['first']['class'].'"' : '').'><a title="Page 1" href="'.(!$firstPage && $url != '/' && substr($url, -1, 1) == '/' ? substr($url, 0, -1) : $url).($firstPage ? '1' : '').'">'.(isset($html['first'], $html['first']['html']) ? $html['first']['html'] : $pagesCount).'</a></li>');
            }
            echo('<li'.(isset($html['prev'], $html['prev']['class']) ? ' class="'.$html['prev']['class'].'"' : '').'><a href="'.($current-1 == 1 && !$firstPage && $url != '/' && substr($url, -1, 1) == '/' ? substr($url, 0, -1) : $url).($current-1 == 1 ? ($firstPage ? $current-1 : '') : $current-1).'" title="Page '.($current-1).'">'.(isset($html['prev'], $html['prev']['html']) ? $html['prev']['html'] : $current-1).'</a></li>');
        }

        if($Core->numberOfPagesInPagination%2 == 0)
            $OddOrEven = 0;
        else
            $OddOrEven = 1;

        $more = 0;

        for($s = $currentPage - ceil($numberOfPagesInPagination/2)+$OddOrEven; $s < $currentPage; $s++){
            if($s>0 && $currentPage+ceil($numberOfPagesInPagination/2)+$OddOrEven < $pagesCount+1+$OddOrEven){
                if($s<=$pagesCount){
                    echo (
                        '<li'.((isset($html['default'], $html['default']['class']) || $currentPage == $s) ? ' class="'.(isset($html['default'], $html['default']['class']) ? $html['default']['class'].' ' : '').($currentPage == $s ? (isset($html['current_page_class']) ? ' '.$html['current_page_class'] : '') : '').'"' : '').'>
                            <a title="Page '.$s.'" href="'.($s == 1 && !$firstPage && $url != '/' && substr($url, -1, 1) == '/' ? substr($url, 0, -1) : $url).($s == 1 ? ($firstPage ? $s : '') : $s).'">'.(isset($html['default'], $html['default']['html']) ? $html['default']['html'].$s : $s).'</a>
                        </li>');
                }
                $more++;
            }
        }

        if($currentPage+ceil($numberOfPagesInPagination/2) >= $pagesCount+1){
            $currentPage = $pagesCount-$numberOfPagesInPagination+1;

            for($s = $currentPage; $s<$numberOfPagesInPagination+$currentPage; $s++){
                if($s<=$pagesCount){
                    echo(
                        '<li'.((isset($html['default'], $html['default']['class']) || $current == $s) ? ' class="'.(isset($html['default'], $html['default']['class']) ? $html['default']['class'].' ' : '').($current == $s ? (isset($html['current_page_class']) ? ' '.$html['current_page_class'] : '') : '').'"' : '').'>
                        <a title="Page '.$s.'" href="'.($s == 1 && !$firstPage && $url != '/' && substr($url, -1, 1) == '/' ? substr($url, 0, -1) : $url).($s == 1 ? ($firstPage ? $s : '') : $s).'">'.(isset($html['default'], $html['default']['html']) ? $html['default']['html'].$s : $s).'</a>
                        </li>');
                }
            }
        }else{
            for($s = $currentPage; $s < $currentPage + $numberOfPagesInPagination - $more; $s++){
                if($s<=$pagesCount){
                    echo(
                        '<li'.((isset($html['default'], $html['default']['class']) || $currentPage == $s) ? ' class="'.(isset($html['default'], $html['default']['class']) ? $html['default']['class'].' ' : '').($currentPage == $s ? (isset($html['current_page_class']) ? ' '.$html['current_page_class'] : '') : '').'"' : '').'>
                        <a title="Page '.$s.'" href="'.($s == 1 && !$firstPage && $url != '/' && substr($url, -1, 1) == '/' ? substr($url, 0, -1) : $url).($s == 1 ? ($firstPage ? $s : '') : $s).'">'.(isset($html['default'], $html['default']['html']) ? $html['default']['html'].$s : $s).'</a>
                        </li>');
                }
            }
        }

        if($current<$pagesCount){
            echo('<li'.(isset($html['next'], $html['next']['class']) ? ' class="'.$html['next']['class'].'"' : '').'><a href="'.$url.($current+1).'" title="Page '.($current+1).'">'.(isset($html['next'], $html['next']['html']) ? $html['next']['html']: $current+1).'</a></li>');
            if($firstLast){
                echo('<li'.(isset($html['last'], $html['last']['class']) ? ' class="'.$html['first']['class'].'"' : '').'><a title="Page '.$pagesCount.'" href="'.$url.$pagesCount.'">'.(isset($html['prev'], $html['last']['html']) ? $html['last']['html'] : $pagesCount).'</a></li>');
            }
        }
        echo '</ul>';
    }

    //returns the content between 2 points of a string
    public function getBetween($content, $start, $end){
        if(!strpos($content,$start))
            return '';
        $content=substr($content,strpos($content,$start)+strlen($start));
        $content=substr($content,0,strpos($content,$end));
        return $content;
    }

    //returns the content between 2 points of a string
    public function getBetweenAll($content, $start, $end,$return=array()){
        while(stristr($content,$start)){
            $startpos=strpos($content,$start)+strlen($start);
            $a=$content=substr($content,$startpos);
            $endpos=strpos($content,$end);
            $b[]=substr($content,0,$endpos);
            $content=substr($content,$endpos);
        }
        if(isset($b))
            return $b;
    }

    //strips the HTML tags all fields in an array
    public function stripAllFields(&$fields, $donot = false){
        foreach ($fields as $key => $value) {
            if($donot){
                if(is_array($donot) && in_array($key, $donot)){
                    continue;
                }elseif(is_string($donot) && $key == $donot){
                    continue;
                }
            }
            if(is_array($fields[$key])){
                $this->stripAllFields($fields[$key], $donot);
            }else{
                $fields[$key] = strip_tags($value);
            }
        }
    }

    //formats the given timestamp into ready for insert into mysql db date for field date/datetime; addHours parameter should be true for datetime fields
    public function formatMysqlTime($time,$addHours = false){
        $time = intval($time);
        if(empty($time)){
            return false;
        }

        if($addHours){
            return date('Y-m-d H:i:s',$time);
        }
        return date('Y-m-d',$time);
    }

    //formats date from mysql date/datetime field into timestamp
    public function mysqlTimeToTimestamp($time){
        if(empty($time)){
            return false;
        }

        if(stristr($time,':')){
            $time = str_replace(array(':',' '),'-',$time);
            $time = explode('-',$time);
            return mktime($time[3],$time[4],$time[5],$time[1],$time[2],$time[0]);
        }
        $time = explode('-',$time);
        return mktime(0,0,0,$time[1],$time[2],$time[0]);
    }

    //formats seconds int seconds, minutes, hours, days and months; remove comment from $s and $mo to calculate seconds and months
    function formatSecondsToTime($time) {
        $time = intval($time);
        if(empty($time)){
            return false;
        }

        //$s = $time%60;
        $m = floor(($time%3600)/60);
        $h = floor(($time%86400)/3600);
        $d = floor(($time%2592000)/86400);
        //$mo = floor($time/2592000);

        $r = '';
        if($d > 0){
            $r .= $d.'d ';
        }
        if($h < 10){
            $h = "0$h";
        }
        if($m < 10){
            $m = "0$m";
        }
        $r .= "$h:$m";
        return $r;
    }

    //formats bytes into the powered values; $precision is used to set the number of decimal numbers
    public function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function formatNumberToReadable($number){
        if($number < 1000){
            return $number;
        }
        if($number < 1000000){
            return number_format(($number / 1000),0).'K';
        }
        if($number < 1000000000){
            return number_format(($number / 1000000),0).'M';
        }
        return number_format(($number / 1000000000),0).'B';
    }

    //put this in the header
    public function insertMeta(){
        global $Core;
        echo '<title>'.$Core->siteName.($Core->meta['title'] ? htmlspecialchars($Core->meta['title'], ENT_QUOTES, 'UTF-8') : '').'</title>';
        echo '<meta charset="UTF-8"/>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1"/>';
        echo '<meta name="keywords" content="'.(htmlspecialchars(($Core->siteName.', '.$Core->siteDomain.$Core->meta['keywords']), ENT_QUOTES, 'UTF-8')).'"/>';

        if($Core->meta['description']){
            echo '<meta name="description" content="'.htmlspecialchars($Core->meta['description'], ENT_QUOTES, 'UTF-8').'"/>';
        }

        echo '<meta property="og:type" content="'.($Core->meta['og_type'] ? $Core->meta['og_type'] : 'website').'"/>';
        echo '<meta property="og:url" content="'.(htmlspecialchars(($Core->meta['og_url'] ? $Core->meta['og_url'] : $Core->siteProtocol.'://'.$Core->siteDomain.$_SERVER['REQUEST_URI']), ENT_QUOTES, 'UTF-8')).'" />';

        if($Core->meta['og_title'] || $Core->meta['title']){
            echo '<meta property="og:title" content="'.$Core->siteName.(htmlspecialchars(($Core->meta['og_title'] ? $Core->meta['og_title'] : $Core->meta['title']), ENT_QUOTES, 'UTF-8')).'" />';
        }
        if($Core->meta['og_description'] || $Core->meta['description']){
            echo '<meta property="og:description" content="'.(htmlspecialchars(($Core->meta['og_description'] ? $Core->meta['og_description'] : $Core->meta['description']), ENT_QUOTES, 'UTF-8')).'" />';
        }

        if(!empty($Core->meta['og_image'])){
            echo '<meta property="og:image" content="'.(htmlspecialchars($Core->meta['og_image'], ENT_QUOTES, 'UTF-8')).'"/>';
            @list($width, $height) = getimagesize($Core->meta['og_image']);
            if(isset($width) && !empty($width)){
                echo '<meta property="og:image:width" content="'.$width.'"/>';
            }
            if(isset($height) && !empty($height)){
                echo '<meta property="og:image:height" content="'.$height.'"/>';
            }
        }
    }

    public function getFolder($dir, $onlyCurrent = false){
        global $Core;

        $mainFoldersCount = count(glob($dir.'*', GLOB_ONLYDIR));
        if($mainFoldersCount == 0){
            $current = 1;
            $folder  = $dir.$current.'/';
        }elseif(count(glob($dir.$mainFoldersCount.'/*')) >= $Core->folderLimit){
            $current = $mainFoldersCount+1;
            $folder  = $dir.$current.'/';
        }else{
            $current = $mainFoldersCount;
            $folder  = $dir.$current.'/';
        }

        if($onlyCurrent){
            return $current;
        }
        return $folder;
    }

    //validate an URL ($url) against a test string ($stringToCheck)
    public function validateSpecificLink($url, $pattern) {
        global $Core;
        $this->validateBasicUrl($url);
        if (!preg_match("{".$pattern."}",$url)) {
            throw new Error("{$url} ".$Core->language->is_not_valid);
        }
        return true;
    }

    //a basic check, if an URL is valid
    public function validateBasicUrl($url) {
        global $Core;
        if (!filter_var($url, FILTER_VALIDATE_URL))
            throw new Error("{$url} ".$Core->language->is_not_a_valid_link);

        if (!substr($url, 0, 7) == "http://" || !substr($url, 0, 8) == "https://") {
            throw new Error("{$url} ".$Core->language->is_not_a_valid_link);
        }

        return true;
    }

    public function checkIfProcessIsRunning($processName){
        global $Core;
        if(empty($processName)){
            throw new Error($Core->language->error_provide_a_process_name);
        }

        exec("ps ax | grep '$processName'",$res);

        return count($res) > 2 ? true : false;
    }

    public function getProcessInstancesCount($processName){
        global $Core;
        if(empty($processName)){
            throw new Error($Core->language->error_provide_a_process_name);
        }

        exec("ps ax | grep '$processName'",$res);

        return count($res) - 2;
    }
}
?>