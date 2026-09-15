<?php
$pageTitle = $pageTitle ?? 'Masuk - Inventaris Toko Bangunan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#0a2647] text-[#1a2332] font-sans antialiased flex items-center justify-center p-6">
  <div class="w-full max-w-md"><?= $content ?></div>
</body>
</html>