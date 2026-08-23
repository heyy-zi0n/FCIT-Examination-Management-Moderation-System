<?php
/**
 * Shared Footer Layout
 */
?>
<footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 mt-auto py-4 px-6 no-print">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-2">
        <div>
            &copy; <?= date('Y') ?> Faculty of Computing and Information Technology (FCIT), Lagos State University. All Rights Reserved.
        </div>
        <div class="flex items-center gap-4">
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>System Security Active</span>
            </span>
            <span class="text-slate-300 dark:text-slate-700">|</span>
            <span>AES-256 Blind Lockdown</span>
        </div>
    </div>
</footer>

<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
