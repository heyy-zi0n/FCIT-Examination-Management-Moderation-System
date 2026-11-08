<?php
/**
 * Shared Footer Layout
 */
$footerOffsetClass = !empty($hasAppSidebar) ? 'md:pl-64' : '';
?>
<footer class="bg-white border-t border-slate-200 mt-auto <?= $footerOffsetClass ?> no-print">
    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-600 gap-2 text-center sm:text-left">
        <div>
            &copy; <?= date('Y') ?> Faculty of Computing and Information Technology (FCIT), Lagos State University. All Rights Reserved.
        </div>
        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
            <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                <span class="w-2 h-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                <span>Workflow Governance Active</span>
            </span>
            <span class="hidden sm:inline text-slate-300" aria-hidden="true">|</span>
            <span>Moderation &bull; Approval &bull; Audit &bull; Controlled Printing</span>
        </div>
    </div>
</footer>

<script src="<?= url('assets/js/app.js') ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
