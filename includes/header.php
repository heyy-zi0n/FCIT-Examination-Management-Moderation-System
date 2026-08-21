<?php
/**
 * Shared Header Layout
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/alerts.php';

$pageTitle = $pageTitle ?? 'LASU FCIT Secure Examination Question Management System';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="LASU FCIT Secure Examination Question Management, Dual Approval and Controlled Printing System">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="/FOCITECMS/assets/img/lasu-logo.png" type="image/png">
    
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
    
    <link rel="stylesheet" href="/FOCITECMS/assets/css/custom.css">
</head>
<body class="h-full font-sans antialiased text-slate-800 dark:text-slate-100 flex flex-col">
