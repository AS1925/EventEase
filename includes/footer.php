</div>
<?php
if (!isset($base_path)) {
    $base_path = (
        strpos($_SERVER['PHP_SELF'], '/admin/') !== false
        || strpos($_SERVER['PHP_SELF'], '/user/') !== false
        || strpos($_SERVER['PHP_SELF'], '/organizer/') !== false
        || strpos($_SERVER['PHP_SELF'], '/vendor/') !== false
    ) ? '../' : '';
}
?>
<footer class="site-footer">
    <div class="container py-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <h5 class="fw-bold mb-2">EventEase</h5>
                <p class="mb-0 text-white-50">Professional event planning and service booking made simple.</p>
            </div>
            <div class="col-lg-6 text-lg-end">
                <p class="mb-0 text-white-50">© 2026 EventEase. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>
<button class="scroll-to-top" id="scrollTopBtn" aria-label="Scroll to top"><i class="fa-solid fa-arrow-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo isset($base_path) ? $base_path : ''; ?>assets/js/script.js"></script>
