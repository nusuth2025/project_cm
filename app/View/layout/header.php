<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>contentMonitor</title>
    <link rel="icon" type="image/svg+xml" href="/img/light-bulb-idea-svgrepo-com-green.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { DEFAULT: '#16a34a', light: '#dcfce7', dark: '#15803d' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">

<nav class="bg-white border-b border-gray-200 shadow-sm">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-6">
        <a href="/" class="flex items-center gap-2 font-bold text-green-700 text-lg shrink-0">
            <img src="/img/light-bulb-idea-svgrepo-com-green.svg" class="h-6 w-6" alt="">
            contentMonitor
        </a>
        <div class="flex items-center gap-5 text-sm">
            <a href="/"     class="text-gray-600 hover:text-gray-900 transition-colors">Home</a>
            <a href="/list" class="text-gray-600 hover:text-gray-900 transition-colors">Meine Monitore</a>
            <a href="/add"  class="text-gray-600 hover:text-gray-900 transition-colors">Hinzufügen</a>
        </div>
        <div class="ml-auto flex items-center gap-3 text-sm">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="text-gray-500"><?= htmlspecialchars((string)($_SESSION['username'] ?? '')) ?></span>
                <form method="post" action="/logout" class="inline">
                    <button type="submit"
                            class="text-red-600 hover:text-red-800 transition-colors font-medium">
                        Abmelden
                    </button>
                </form>
            <?php else: ?>
                <a href="/login"
                   class="bg-green-600 text-white px-3 py-1.5 rounded hover:bg-green-700 transition-colors font-medium">
                    Anmelden
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="max-w-6xl mx-auto px-4 py-8">
