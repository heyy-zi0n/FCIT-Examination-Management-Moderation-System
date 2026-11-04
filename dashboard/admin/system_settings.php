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

    <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
        <?= renderAlerts() ?>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">System Diagnostics & Security Parameters</h1>
            <p class="text-xs text-slate-500">Live operational status of cryptographic engines, storage isolation, and environment dependencies</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            
            <!-- Security & Cryptography Engine -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Cryptographic Security Engine</span>
                </h2>

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">OpenSSL Extension & AES-256-GCM</div>
                            <div class="text-[10px] text-slate-400">Authenticated Cipher support</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $opensslAvailable ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= $opensslAvailable ? 'Operational (Available)' : 'Missing' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">Master Cryptographic Key</div>
                            <div class="text-[10px] text-slate-400">AES-256 Key Entropy check</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $keyConfigured ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= $keyConfigured ? 'Valid (256-bit entropy)' : 'Misconfigured' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">LibreOffice Headless Converter</div>
                            <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars(LIBREOFFICE_PATH) ?></div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $libreOfficeAvailable ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                            <?= $libreOfficeAvailable ? 'Detected & Configured' : 'Path Not Found' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">Enforced Application Timezone</div>
                            <div class="text-[10px] text-slate-400">Printing window authority</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold font-mono bg-cyan-100 text-cyan-800">
                            <?= APP_TIMEZONE ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Isolated Storage Directories -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    <span>Storage Isolation & Directory Permissions</span>
                </h2>

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">Raw Working Storage (DOCX / PDF)</div>
                            <div class="text-[10px] text-slate-400">Total stored: <?= formatBytes($rawStorageSize) ?></div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $rawStorageWriteable ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= $rawStorageWriteable ? 'Writable & Protected' : 'Permission Error' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">Blind Lockdown Storage (AES-256)</div>
                            <div class="text-[10px] text-slate-400">Total stored: <?= formatBytes($encStorageSize) ?></div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $encStorageWriteable ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= $encStorageWriteable ? 'Writable & Isolated' : 'Permission Error' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">Rendered PDF Preview Cache</div>
                            <div class="text-[10px] text-slate-400">Total cached: <?= formatBytes($prevStorageSize) ?></div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= $prevStorageWriteable ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= $prevStorageWriteable ? 'Writable & Active' : 'Permission Error' ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div>
                            <div class="font-bold text-slate-800 dark:text-slate-200">Direct Web Access Blocker (.htaccess)</div>
                            <div class="text-[10px] text-slate-400">Deny all HTTP direct file requests</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                            Enforced (Fail-Closed)
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Runtime Platform Environment -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4">
                Platform Runtime Environment
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                    <span class="text-slate-400 block text-[10px] uppercase font-semibold">PHP Engine</span>
                    <span class="font-bold font-mono text-slate-800 dark:text-slate-200"><?= $phpVersion ?></span>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                    <span class="text-slate-400 block text-[10px] uppercase font-semibold">Database Engine</span>
                    <span class="font-bold font-mono text-slate-800 dark:text-slate-200"><?= htmlspecialchars($mysqlVersion) ?></span>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                    <span class="text-slate-400 block text-[10px] uppercase font-semibold">Server OS</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200"><?= PHP_OS_FAMILY ?> (<?= php_uname('s') ?>)</span>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                    <span class="text-slate-400 block text-[10px] uppercase font-semibold">App Version</span>
                    <span class="font-bold font-mono text-lasu-gold"><?= APP_VERSION ?></span>
                </div>
            </div>
        </div>

    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
