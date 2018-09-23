<?php
class Menu{
    public $pathName = false;

    public function getFullPathName(array $pages, $delimiter = ' / '){
        global $Core;

        $fullPageName = false;

        if($pages && $pages = $this->buildTree($pages)){
            $currentPage = $Core->globalFunctions->arraySearch($pages, 'url', $Core->rewrite->URL);

            if(isset($currentPage[0], $currentPage[0]['parentsIds'])){
                $parents = $currentPage[0]['parentsIds'];

                foreach($parents as $p){
                    $fullPageName .= $Core->globalFunctions->arraySearch($pages, 'id', $p)[0]['name'].$delimiter;
                }
            }

            if(isset($currentPage[0], $currentPage[0]['name'])){
                $fullPageName .= $currentPage[0]['name'];
            }
            return $fullPageName;
        }
        return false;
    }

    public function findParents($tree, $id, $parents = array()){
        global $Core;

        $parent = $Core->globalFunctions->arraySearch($tree, 'id', $id);

        if(isset($parent[0]['parent_id']) && $parent[0]['parent_id']){
            $parents[] =  $parent[0]['parent_id'];
            return $this->findParents($tree, $parent[0]['parent_id'], $parents);
        }
        $parents = array_reverse($parents);
        return $parents;
    }

    public function findChildren($tree, $id, $children = array()){
        global $Core;

        $child = $Core->globalFunctions->arraySearch($tree, 'id', $id);

        if(isset($child[0]['children']) && $child[0]['children']){
            foreach($child[0]['children'] as $c){
                $children[] = $c['id'];
                $children = $this->findChildren($tree, $c['id'], $children);
            }
        }
        return $children;
    }

    public function buildTree(array $elements, $parentId = 0, $level = 0){
        $branch = array();
        $level++;

        foreach ($elements as $k => $element) {
            if ($element['parent_id'] == $parentId){
                $children = $this->buildTree($elements, $element['id'], $level);
                if($children){
                    $element['children'] = $children;
                }else{
                    $element['children'] = array();
                }

                $element['level']       = $level;
                $element['parentsIds']  = $this->findParents($elements, $element['id']);
                $element['childrenIds'] = $this->findChildren($element, $element['id']);
                $branch[]               = $element;
            }
        }
        return $branch;
    }

    public function formTree($row, $isResp = true){
        global $Core;

        $parents = $Core->globalFunctions->arraySearch($row, 'url', $Core->rewrite->URL);
        if(isset($parents[0], $parents[0]['parentsIds'])){
            $parents = $parents[0]['parentsIds'];
        }

        foreach($row as $t){
            if($t['children']){
            ?>
                <li id="<?php echo $t['id']; ?>" class="hasMenu<?php echo ($t['url'] == $Core->rewrite->URL ? ' is-current' : '' ).($isResp ? ' respPad' : '').(in_array($t['id'], $parents) ? ' active' : ''); ?>" title="<?php echo $t['name']; ?>">
                    <?php if($t['url']){ ?>
                        <a href="<?php echo $t['url']; ?>"><?php echo $t['icon']; ?><?php echo $t['name']; ?></a>
                    <?php }else{ ?>
                        <div class="menu-name"><?php echo $t['icon']; ?><?php echo $t['name']; ?></div>
                    <?php } ?>
                    <ul class="<?php echo (in_array($t['id'], $parents) ? 'opened' : '').($isResp ? ' resp' : ''); ?>">
                        <?php $this->formTree($t['children'], $isResp); ?>
                    </ul>
                </li>
            <?php }else{ ?>
                <li id="<?php echo $t['id']; ?>" class="noMenu <?php echo $t['url'] == $Core->rewrite->URL ? ' is-current' : ''; ?>" title="<?php echo $t['name']; ?>">
                    <?php if($t['url']){ ?>
                        <a href="<?php echo $t['url']; ?>"><?php echo $t['icon']; ?><?php echo $t['name']; ?></a>
                    <?php }else{ ?>
                        <div class="menu-name"><?php echo $t['icon']; ?><?php echo $t['name']; ?></div>
                    <?php } ?>
                </li>
            <?php
            }
        }
    }

    //if class is false menu will auto expand aside and if menu width is bigger than its parent, elements goes one under another, max width of element is 250px
    //else every element goes one under another, max width of element is 100%
    //width comes from the css
    public function getMenu(array $pages, $class = 'allResp'){
        global $Core;

        ob_start();
        ?>
        <div class="mega-menu <?php echo $class; ?>">
            <ul class="mega-menu-in">
                <?php
                if($pages){
                    $this->formTree($this->buildTree($pages), $class);
                }else{
                    echo 'No pages';
                }
                ?>
            </ul>
        </div>
        <?php
        $menu = ob_get_contents();
        ob_end_clean();
        return $menu;
    }
}
?>