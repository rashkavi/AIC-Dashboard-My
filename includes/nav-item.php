<?php

class nav_item
{

    public static function loadItem($text, $icon, $link, $langText, $condition)
    {
        if ($condition) {
?>
            <a class="nav-link p-0 g-0 text-decoration-none mb-1" aria-current="page" href="<?php echo $link ?>">
                <div class="col-12 align-content-center justify-content-start menuItem px-2 pt-1 pb-1 m-0">
                    <div class="d-flex flex-row align-items-center justify-content-start m-1">
                        <iconify-icon icon="<?php echo $icon; ?>" width="20" height="20" class="pe-2 m-0 nav-icon"></iconify-icon>
                        <span class="text-16 fw-medium m-0 p-0 g-0 lh-lg nav-text" data-i18n="<?= $langText ?>"><?php echo $text ?></span>
                    </div>
                </div>
            </a>
        <?php
        } else {
        ?>
            <a class="nav-link p-0 g-0 mb-1" aria-current="page" href="<?php echo $link ?>">
                <div class="col-12 align-content-center justify-content-start px-2 pt-1 pb-1 m-0 nav-left-b">
                    <div class="d-flex flex-row align-items-center justify-content-start m-1">
                        <iconify-icon icon="<?php echo $icon; ?>" width="20" height="20" class="pe-2 m-0 dark-theme"></iconify-icon>
                        <span class="text-16 dark-theme fw-medium m-0 p-0 g-0 lh-lg" data-i18n="<?= $langText ?>"><?php echo $text ?></span>
                    </div>
                </div>
            </a>
<?php
        }
    }
}

?>