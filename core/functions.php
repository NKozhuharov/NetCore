<?php
class Functions{
    //sends email; requires PHPMailer library to work!
    public function sendEmail($from = false, $fromName = false, $subject = false, $addAddress = false, $body = false, $isHTML = false, $attachment = false, $isAdnmin = false){
        require_once('/var/www/classes/PHPMailer-master/PHPMailerAutoload.php');
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

    public function drawPagination($resultsCount, $url, $page, $firstLast = false){
        global $Core;
        if($resultsCount <= $Core->itemsPerPage){
            return false;
        }

        if($page <= 0){
            $page = 1;
        }

        $pagesCount = ceil($resultsCount / $Core->itemsPerPage);

        if($Core->numberOfPagesInPagination > $pagesCount){
            $numberOfPagesInPagination = $pagesCount;
        }else{
            $numberOfPagesInPagination = $Core->numberOfPagesInPagination;
        }

        $current = $page;

        echo '<div class="pagination">';
        if($page>1){
            if($firstLast){
                echo '<a title="'.$Core->language->first_page.'" href="'.$url.'1"><i class="fa fa-angle-double-left"></i></a>';
            }
            echo '<a href="'.$url.($current-1).'" class="prev" title="'.$Core->language->prev_page.'"><i class="fa fa-angle-left"></i></a>';
        }

        if($Core->numberOfPagesInPagination%2 == 0)
            $OddOrEven = 0;
        else
            $OddOrEven = 1;

        $more = 0;

        for($s = $page - ceil($numberOfPagesInPagination/2)+$OddOrEven; $s < $page; $s++){
            if($s>0 && $page+ceil($numberOfPagesInPagination/2)+$OddOrEven < $pagesCount+1+$OddOrEven){
                echo '<a ';
                if($page == $s){
                    echo ' class="pageSelected" ';
                }
                echo 'title="'.$Core->language->page.' '.$s.'" href="'.$url.$s.'">'.$s.'</a>';
                $more++;
            }
        }

        if($page+ceil($numberOfPagesInPagination/2) >= $pagesCount+1){
            $page = $pagesCount-$numberOfPagesInPagination+1;

            for($s = $page; $s<$numberOfPagesInPagination+$page; $s++){
                echo '<a ';
                if($current==$s){
                    echo ' class="pageSelected" ';
                }
                echo 'title="'.$Core->language->page.' '.$s.'" href="'.$url.$s.'">'.$s.'</a>';
            }
        }else{
            for($s = $page; $s < $page + $numberOfPagesInPagination - $more; $s++){
                if($s<=$pagesCount){
                    echo '<a ';
                    if($page == $s){
                        echo ' class="pageSelected" ';
                    }
                    echo 'title="'.$Core->language->page.' '.$s.'" href="'.$url.$s.'">'.$s.'</a>';
                }
            }
        }

        if($current<$pagesCount){
            echo '<a href="'.$url.($current+1).'" class="next" title="'.$Core->language->next_page.'"><i class="fa fa-angle-right"></i></a>';
            if($firstLast){
                echo '<a title="'.$Core->language->last_page.'" href="'.$url.$pagesCount.'"><i class="fa fa-angle-double-right"></i></a>';
            }
        }
        echo '</div>';
    }

    //use %s fore page
    function drawPaginationNew($resultsCount, $url, $currentPage = false, $firstLast = false, array $html){
        global $Core;
        if($resultsCount <= $Core->itemsPerPage){
            return false;
        }
        
        if(!$currentPage){
            $currentPage = $Core->currentPage;
        }

        if($currentPage <= 0){
            $currentPage = 1;
        }

        $pagesCount = ceil($resultsCount / $Core->itemsPerPage);

        if($Core->numberOfPagesInPagination > $pagesCount){
            $numberOfPagesInPagination = $pagesCount;
        }else{
            $numberOfPagesInPagination = $Core->numberOfPagesInPagination;
        }

        $current = $currentPage;
        
        if(!isset($html['current_page_class'])){
            $html['current_page_class'] = 'pageSelected';
        }
        
        //TO DO FIX HTML ARRAY DEFAULTS
        /*
        $pageStyle = array(
            'ul_class' => 'pagination',
            'current_page_class' => 'active',
            'default' => array(
                'class' => 'asd',
                'html' => '%s'
            ),
            'next' => array(
                'class' => 'next',
                'html' => '<i class="fa fa-chevron-right"></i>'
            ),
            'last' => array(
                'class' => 'next',
                'html' => '<i class="fa fa-chevron-circle-right"></i>'
            ),
            'prev' => array(
                'class' => 'prev',
                'html' => '<i class="fa fa-chevron-left"></i>'
            ),
            'first' => array(
                'class' => 'prev',
                'html' => '<i class="fa fa-chevron-circle-left"></i>'
            ),
        );
        */
        
        if(isset($html['ul_class'])){
            echo '<ul class="'.$html['ul_class'].'">';
        }
        else{
            echo '<ul>';
        }
        
        if($currentPage>1){
            if($firstLast){
                printf('<li class="'.$html['first']['class'].'"><a title="Page 1" href="'.$url.'1">'.$html['first']['html'].'</a></li>', $pagesCount);
            }
            printf('<li class="'.($html['prev']['class'] ? $html['prev']['class'] : '').'"><a href="'.$url.($current-1).'" title="Page '.($current-1).'">'.$html['prev']['html'].'</a></li>', $current-1);
        }

        if($Core->numberOfPagesInPagination%2 == 0)
            $OddOrEven = 0;
        else
            $OddOrEven = 1;

        $more = 0;

        for($s = $currentPage - ceil($numberOfPagesInPagination/2)+$OddOrEven; $s < $currentPage; $s++){
            if($s>0 && $currentPage+ceil($numberOfPagesInPagination/2)+$OddOrEven < $pagesCount+1+$OddOrEven){
                if($s<=$pagesCount){
                    printf(
                        '<li class="'.$html['default']['class'].($currentPage == $s ? ' '.$html['current_page_class'] : '').'">
                        <a title="Page '.$s.'" href="'.$url.$s.'">'.$html['default']['html'].'</a>
                        </li>'
                    , $s);
                }
                $more++;
            }
        }

        if($currentPage+ceil($numberOfPagesInPagination/2) >= $pagesCount+1){
            $currentPage = $pagesCount-$numberOfPagesInPagination+1;

            for($s = $currentPage; $s<$numberOfPagesInPagination+$currentPage; $s++){
                if($s<=$pagesCount){
                    printf(
                        '<li class="'.$html['default']['class'].($current == $s ? ' '.$html['current_page_class'] : '').'">
                        <a title="Page '.$s.'" href="'.$url.$s.'">'.$html['default']['html'].'</a>
                        </li>'
                    , $s);
                }
            }
        }else{
            for($s = $currentPage; $s < $currentPage + $numberOfPagesInPagination - $more; $s++){
                if($s<=$pagesCount){
                    printf(
                        '<li class="'.$html['default']['class'].($currentPage == $s ? ' '.$html['current_page_class'] : '').'">
                        <a title="Page '.$s.'" href="'.$url.$s.'">'.$html['default']['html'].'</a>
                        </li>'
                    , $s);
                }
            }
        }

        if($current<$pagesCount){
            printf('<li class="'.($html['next']['class'] ? $html['next']['class'] : '').'"><a href="'.$url.($current+1).'" title="Page '.($current+1).'">'.$html['next']['html'].'</a></li>', $current+1);
            if($firstLast){
                printf('<li class="'.$html['last']['class'].'"><a title="Page '.$pagesCount.'" href="'.$url.$pagesCount.'">'.$html['last']['html'].'</a></li>', $pagesCount);
            }
        }
        echo '</ul>';
    }

    public function getBetween($content, $start, $end){
        if(!strpos($content,$start))
            return '';
        $content=substr($content,strpos($content,$start)+strlen($start));
        $content=substr($content,0,strpos($content,$end));
        return $content;
    }

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
            if (is_array($fields[$key])){
                $this->stripAllFields($fields[$key], $start, $donot);
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

    //formats bytes into the powered values; $precision is used to set the number of decimal numbers
    public static function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    //put this in the header
    public function insertMeta(){
        global $Core;
        echo '<title>'.$Core->meta['title'].'</title>';
        echo '<meta charset="UTF-8"/>';
        if(!empty($Core->meta['description']))
            echo '<meta name="description" content="'.$Core->meta['description'].'"/>';
        if(!empty($Core->meta['keywords']))
            echo '<meta name="keywords" content="'.$Core->meta['keywords'].'"/>';

        if(!empty($Core->meta['og_title']) || !empty($Core->meta['title'])){
            echo '<meta property="og:title" content="'.(!empty($Core->meta['og_title']) ? $Core->meta['og_title'] : $Core->meta['title']).'" />';
        }
        if(!empty($Core->meta['og_description']) || !empty($Core->meta['description'])){
            echo '<meta property="og:description" content="'.(!empty($Core->meta['og_description']) ? $Core->meta['og_description'] : $Core->meta['description']).'" />';
        }

        echo '<meta property="og:type" content="'.$Core->meta['og_type'].'"/>';

        echo '<meta property="og:url" content="'.(!empty($Core->meta['og_url']) ? $Core->meta['og_url'] : $Core->siteProtocol.'://'.$Core->siteDomain.$_SERVER['REQUEST_URI']).'"/>';

        if(!empty($Core->meta['og_image'])){
            echo '<meta property="og:image" content="'.$Core->meta['og_image'].'"/>';
            @list($width, $height) = getimagesize($Core->meta['og_image']);
            if(isset($width) && !empty($width)){
                echo '<meta property="og:image:width" content="'.$width.'"/>';
            }
            if(isset($height) && !empty($height)){
                echo '<meta property="og:image:height" content="'.$height.'"/>';
            }
        }
    }
}
?>