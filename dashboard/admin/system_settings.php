<?php
/**
 * System Settings, Storage Health & Cryptography Diagnostics (Administrator)
 */

require_once __DIR__ . '/../../bootstrap.php';
$user = requireRole(ROLE_ADMIN);

$pdo = getDb();

// Diagnostic checks
$storageWriteable = is_writable(STORAGE_PATH);
$rawStorageWriteable = is_writable(EXAM_STORAGE_PATH);
$encStorageWriteable = is_writable(EXAM_STORAGE_PATH);
$prevStorageWriteable = is_writable(PREVIEW_STORAGE_PATH);

$libreOfficeAvailable = file_exists(LIBREOFFICE_PATH);
$mysqlVersion = (string)$pdo->query("SELECT VERSION()")->fetchColumn();
$phpVersion = PHP_VERSION;
$opensslAvailable = extension_loaded('openssl');
$config = require __DIR__ . '/../../config/app.php';
$masterKey = $config['encryption_key'] ?? '';
$keyConfigured = !empty($masterKey) && strlen($masterKey) >= 32;

// Accurate storage usage calculation separated by file type
$rawStorageSize = 0;
$encStorageSize = 0;
if (is_dir(EXAM_STORAGE_PATH)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(EXAM_STORAGE_PATH, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $ext = strtolower($file->getExtension());
            if ($ext === 'enc') {
                $encStorageSize += $file->getSize();
            } else {
                $rawStorageSize += $file->getSize();
            }
        }
    }
}

$prevStorageSize = 0;
if (is_dir(PREVIEW_STORAGE_PATH)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PREVIEW_STORAGE_PATH, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $prevStorageSize += $file->getSize();
        }
    }
}

$pageTitle = 'System Health & Security Diagnostics — LASU FCIT CMS';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="flex-1 flex">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <main id="main-content" class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">System Diagnostics &amp; Security Parameters</h1>
            <p class="text-sm text-slate-600 mt-0.5">Live operational status of cryptographic engines, storage isolation, and environment dependencies</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8 mb-8">
            <!-- Security & Cryptography Engine -->
            <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Cryptographic Security Engine</span>
                </h2>

                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div>
                            <div class="font-bold text-slate-900">OpenSSL Extension &amp; AES-256-GCM</div>
                            <div class="text-xs text-slate-500">Authenticated encryption cipher support</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold border shrink-0 <?= $opensslAvailable ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200' ?>">
                            <?= $opensslAvailable ? 'Operational' : 'Missing' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div>
                            <div class="font-bold text-slate-900">Master Cryptographic Key</div>
                            <div class="text-xs text-slate-500">AES-256 key entropy verification</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold border shrink-0 <?= $keyConfigured ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200' ?>">
                            <?= $keyConfigured ? 'Valid (256-bit)' : 'Misconfigured' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div class="min-w-0">
                            <div class="font-bold text-slate-900">LibreOffice Headless Converter</div>
                            <div class="text-xs text-slate-500 font-mono truncate"><?= htmlspecialchars(LIBREOFFICE_PATH, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold border shrink-0 <?= $libreOfficeAvailable ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200' ?>">
                            <?= $libreOfficeAvailable ? 'Configured' : 'Path Not Found' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div>
                            <div class="font-bold text-slate-900">Enforced Application Timezone</div>
                            <div class="text-xs text-slate-500">Controlled printing window authority</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold font-mono bg-cyan-50 text-cyan-900 border border-cyan-200 shrink-0">
                            <?= htmlspecialchars(APP_TIMEZONE, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>
            </section>

            <!-- Isolated Storage Directories -->
            <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-lasu-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    <span>Storage Isolation &amp; Directory Permissions</span>
                </h2>

                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div>
                            <div class="font-bold text-slate-900">Raw Working Storage (DOCX / PDF)</div>
                            <div class="text-xs text-slate-500 tabular-nums">Total stored: <?= formatBytes($rawStorageSize) ?></div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold border shrink-0 <?= $rawStorageWriteable ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200' ?>">
                            <?= $rawStorageWriteable ? 'Writable & Protected' : 'Permission Error' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div>
                            <div class="font-bold text-slate-900">Blind Lockdown Storage (AES-256)</div>
                            <div class="text-xs text-slate-500 tabular-nums">Total stored: <?= formatBytes($encStorageSize) ?></div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold border shrink-0 <?= $encStorageWriteable ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200' ?>">
                            <?= $encStorageWriteable ? 'Writable & Isolated' : 'Permission Error' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div>
                            <div class="font-bold text-slate-900">Rendered PDF Preview Cache</div>
                            <div class="text-xs text-slate-500 tabular-nums">Total cached: <?= formatBytes($prevStorageSize) ?></div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold border shrink-0 <?= $prevStorageWriteable ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200' ?>">
                            <?= $prevStorageWriteable ? 'Writable & Active' : 'Permission Error' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-4 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div>
                            <div class="font-bold text-slate-900">Direct Web Access Blocker (.htaccess)</div>
                            <div class="text-xs text-slate-500">Denies direct HTTP file requests</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 shrink-0">
                            Enforced (Fail-Closed)
                        </span>
                    </div>
                </div>
            </section>
        </div>

        <!-- Runtime Platform Environment -->
        <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-4">
                Platform Runtime Environment
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div class="p-3.5 bg-slate-50 border border-slate-200/70 rounded-xl">
                    <span class="text-slate-500 block text-xs uppercase font-semibold mb-0.5">PHP Engine</span>
                    <span class="font-bold font-mono text-slate-900 tabular-nums"><?= htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="p-3.5 bg-slate-50 border border-slate-200/70 rounded-xl">
                    <span class="text-slate-500 block text-xs uppercase font-semibold mb-0.5">Database Engine</span>
                    <span class="font-bold font-mono text-slate-900 tabular-nums"><?= htmlspecialchars($mysqlVersion, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="p-3.5 bg-slate-50 border border-slate-200/70 rounded-xl">
                    <span class="text-slate-500 block text-xs uppercase font-semibold mb-0.5">Server OS</span>
                    <span class="font-bold text-slate-900"><?= htmlspecialchars(PHP_OS_FAMILY . ' (' . php_uname('s') . ')', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="p-3.5 bg-slate-50 border border-slate-200/70 rounded-xl">
                    <span class="text-slate-500 block text-xs uppercase font-semibold mb-0.5">App Version</span>
                    <span class="font-bold font-mono text-lasu-navy tabular-nums"><?= htmlspecialchars(APP_VERSION, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </section>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
