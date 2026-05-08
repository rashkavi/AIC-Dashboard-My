<div id="table-skeleton" class="p-4 component-theme">

    <div class="d-flex mb-3 gap-3 align-items-center">
        <div class="skeleton skeleton-btn w-25"></div>
        <div class="skeleton skeleton-btn w-75"></div>
        <div class="skeleton skeleton-btn w-25"></div>
    </div>

    <div class="d-flex mb-3 gap-3 align-items-center justify-content-end">
        <div class="col-6 col-lg-2 skeleton skeleton-btn"></div>
    </div>

    <?php
    for ($u = 0; $u < 10; $u++) {
    ?>
        <div class="skeleton-row d-flex align-items-center p-4 bl-solid">
            <div class="skeleton" style="width:18px; height:18px; border-radius:4px;"></div>

            <div class="skeleton ms-3 hwr-40"></div>
            <div class="skeleton ms-3" style="width:160px; height:14px;"></div>
            <div class="skeleton ms-5" style="width:130px; height:14px;"></div>
            <div class="skeleton ms-5" style="width:200px; height:14px;"></div>
            <div class="skeleton ms-5" style="width:120px; height:14px;"></div>
            <div class="skeleton ms-auto d-none d-md-block" style="width:90px; height:28px;"></div>
        </div>
    <?php
    }
    ?>




</div>