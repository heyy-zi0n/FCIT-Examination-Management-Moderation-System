<?php
/**
 * Shared Header Layout
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/alerts.php';

$pageTitle = $pageTitle ?? APP_NAME;
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Design and Implementation of a Secure Examination Question Lifecycle Management System with Moderation, Approval, Audit and Controlled Printing for LASU FCIT">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="<?= url('assets/img/lasu-logo.png') ?>" type="image/png">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN with configuration -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        lasu: {
                            navy: '#002147',
                            'navy-light': '#0d3b66',
                            gold: '#D4AF37',
                            'gold-hover': '#b89628',
                            emerald: '#10B981'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="<?= url('assets/css/custom.css') ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/custom.css') ?>">
    <?php if (isset($isLandingPage) && $isLandingPage): ?>
    <link rel="stylesheet" href="<?= url('assets/css/landing.css') ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/landing.css') ?>">
    <?php endif; ?>
</head>
<body class="<?= !empty($isLandingPage) ? 'min-h-full' : 'h-full' ?> font-sans antialiased text-slate-800 dark:text-slate-100 flex flex-col">
