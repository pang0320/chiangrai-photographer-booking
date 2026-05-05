<?php $flashItems = flashes(); ?>
<?php
$footerRequestUri = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $footerRequestUri = $_SERVER['REQUEST_URI'];
}
$footerPath = parse_url($footerRequestUri, PHP_URL_PATH);
if (!$footerPath) {
    $footerPath = '/';
}
$footerIsWorkspacePage = preg_match('#^/(admin|customer|photographer)/#', $footerPath) === 1;
$footerIsContactPage = $footerPath === '/contact.php';
?>
</main>
<?php if ($footerIsWorkspacePage): ?>
    </div>
<?php endif; ?>
<div id="page-loader" class="page-loader hidden">
    <div class="rounded-[2rem] bg-white p-6 text-center shadow-2xl">
        <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-neutral-200 border-t-red-600"></div>
        <p class="mt-3 text-sm font-black text-neutral-700">กำลังโหลด...</p>
    </div>
</div>
<?php
$footerCategories = db_fetch_all('SELECT id, name, slug FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name LIMIT 6');
$footerDistricts = db_fetch_all('SELECT district_name FROM districts WHERE is_active = 1 ORDER BY district_name LIMIT 8');
?>
<footer class="mt-16 border-t border-neutral-200 bg-neutral-950 text-white">
    <div class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
            <div>
                <div class="flex items-center gap-3">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-red-600 text-white shadow-lg shadow-red-900/30">
                        <i class="fa-solid fa-camera-retro"></i>
                    </div>
                    <div>
                        <div class="text-xl font-black text-white">Chiang Rai<span class="text-red-500">Photo</span></div>
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-white/38">ตลาดช่างภาพ</p>
                    </div>
                </div>
                <p class="mt-5 max-w-md text-sm leading-7 text-white/62"><?= h(PAYMENT_DISCLAIMER) ?></p>
                <div class="mt-6 flex gap-3">
                    <a href="#" class="grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white hover:bg-red-600"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white hover:bg-red-600"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white hover:bg-red-600"><i class="fa-brands fa-line"></i></a>
                </div>
            </div>

            <div>
                <div class="font-black text-white">เมนูหลัก</div>
                <div class="mt-4 grid gap-2 text-sm text-white/62">
                    <a href="/index.php" class="hover:text-red-400"><i class="fa-solid fa-home mr-2"></i>หน้าแรก</a>
                    <a href="/photographers.php" class="hover:text-red-400"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาช่างภาพ</a>
                    <a href="/about.php" class="hover:text-red-400"><i class="fa-solid fa-circle-info mr-2"></i>เกี่ยวกับเรา</a>
                    <a href="/blog.php" class="hover:text-red-400"><i class="fa-solid fa-newspaper mr-2"></i>บทความ</a>
                    <a href="/faq.php" class="hover:text-red-400"><i class="fa-solid fa-circle-question mr-2"></i>คำถามที่พบบ่อย</a>
                    <a href="/contact.php" class="hover:text-red-400"><i class="fa-solid fa-envelope mr-2"></i>ติดต่อเรา</a>
                    <a href="/register.php?role=photographer" class="hover:text-red-400"><i class="fa-solid fa-user-plus mr-2"></i>สมัครเป็นช่างภาพ</a>
                    <a href="/login.php" class="hover:text-red-400"><i class="fa-solid fa-right-to-bracket mr-2"></i>เข้าสู่ระบบ</a>
                </div>
            </div>

            <div>
                <div class="font-black text-white">หมวดหมู่ยอดนิยม</div>
                <div class="mt-4 grid gap-2 text-sm text-white/62">
                    <?php foreach ($footerCategories as $category): ?>
                        <a href="/photographers.php?category_id=<?= (int)$category['id'] ?>" class="hover:text-red-400"><i class="fa-solid fa-layer-group mr-2"></i><?= h($category['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <div class="font-black text-white">อำเภอยอดนิยม</div>
                <div class="mt-4 flex flex-wrap gap-2 text-sm text-white/62">
                    <?php foreach ($footerDistricts as $district): ?>
                        <span class="rounded-full bg-white/8 px-3 py-1.5"><i class="fa-solid fa-location-dot mr-1 text-red-400"></i><?= h($district['district_name']) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if (!$footerIsContactPage): ?>
                    <div class="mt-5 text-sm leading-7 text-white/62">
                        <p><i class="fa-solid fa-code mr-2 text-red-400"></i>Creepygame / Game</p>
                        <p><i class="fa-solid fa-phone mr-2 text-red-400"></i>099-4344335</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="mt-10 flex flex-wrap justify-between gap-4 border-t border-white/10 pt-6 text-xs font-bold text-white/42">
            <span>© <?= current_be_year() ?> <?= h(setting('site_name', APP_NAME)) ?>. ระบบค้นหา จอง และติดต่อช่างภาพโดยตรง</span>
            <span class="flex flex-wrap gap-4">
                <a href="/terms.php" class="hover:text-red-400"><i class="fa-solid fa-file-contract mr-1"></i>เงื่อนไขการใช้งาน</a>
                <a href="/privacy.php" class="hover:text-red-400"><i class="fa-solid fa-shield-halved mr-1"></i>นโยบายความเป็นส่วนตัว</a>
                <a href="/sitemap.php" class="hover:text-red-400"><i class="fa-solid fa-sitemap mr-1"></i>แผนผังเว็บไซต์</a>
            </span>
        </div>
        <?php if (!$footerIsContactPage): ?>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white/5 px-4 py-3 text-xs font-bold text-white/50">
                <span><i class="fa-solid fa-code mr-2 text-red-400"></i>Developer: Creepygame / Game</span>
                <span><i class="fa-solid fa-camera-retro mr-1 text-red-400"></i>Photographer from Chiang Rai, Thailand</span>
            </div>
        <?php endif; ?>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/assets/js/app.js"></script>
<?php foreach ($flashItems as $item): ?>
<script>
Swal.fire({icon: '<?= h($item['type']) ?>', title: '<?= h($item['message']) ?>', timer: 2200, showConfirmButton: false});
</script>
<?php endforeach; ?>
</body>
</html>
