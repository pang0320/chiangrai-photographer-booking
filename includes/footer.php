<?php $flashItems = flashes(); ?>
<?php
$footerPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$footerIsWorkspacePage = preg_match('#^/(admin|customer|photographer)/#', $footerPath) === 1;
?>
</main>
<?php if ($footerIsWorkspacePage): ?>
    </div>
<?php endif; ?>
<footer class="mt-16 border-t border-neutral-200 bg-neutral-950 text-white">
    <div class="stock-shell grid gap-6 px-4 py-10 text-sm text-white/62 sm:px-6 md:grid-cols-3 lg:px-8">
        <div>
            <div class="text-lg font-black text-white">Chiang Rai<span class="text-red-500">Photo</span></div>
            <p class="mt-2"><?= h(PAYMENT_DISCLAIMER) ?></p>
        </div>
        <div>
            <div class="font-black text-white">เมนู</div>
            <div class="mt-2 grid gap-1">
                <a href="/photographers.php" class="hover:text-red-400">ค้นหาช่างภาพ</a>
                <a href="/register.php" class="hover:text-red-400">สมัครเป็นช่างภาพ</a>
            </div>
        </div>
        <div>
            <div class="font-black text-white">ติดต่อ</div>
            <p class="mt-2"><?= h(setting('admin_email', 'admin@example.com')) ?> · <?= h(setting('admin_phone', '')) ?></p>
        </div>
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
