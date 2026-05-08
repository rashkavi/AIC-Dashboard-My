<div class="col-12 p-0 m-0 g-0 mb-4">
    <div class="row g-4 d-flex">
        <?php
        for ($i = 0; $i < 8; $i++) {
        ?>
            <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                <div class="px-5 py-5 w-100 skeleton">
                </div>
            </div>
        <?php
        }
        ?>
    </div>
</div>

<?php
for ($i = 0; $i < 3; $i++) {
?>
    <div class="col-12 px-4 py-3 component-theme mb-10 skeleton-container">
        <div class="row p-0 m-0 g-0 align-content-center">

            <!-- IMAGE SKELETON -->
            <div class="col-12 col-md-2 col-lg-1 d-flex align-items-center p-0 m-0 g-0">
                <div class="skeleton skeleton-img w-100"></div>
            </div>

            <!-- TEXT SKELETON -->
            <div class="col-12 col-md-7 col-lg-8 px-0 px-md-3 mt-3 mt-md-0 g-0 d-flex flex-column justify-content-center">
                <div class="skeleton skeleton-title mb-2"></div>
                <div class="skeleton skeleton-text"></div>
            </div>

            <!-- BUTTONS SKELETON -->
            <div class="col-12 col-md-3 p-0 mt-3 m-md-0 m-lg-0">
                <div class="row gx-3 h-100 align-content-center justify-content-end">
                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                        <div class="skeleton skeleton-btn w-100"></div>
                    </div>
                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                        <div class="skeleton skeleton-btn w-100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
}
?>