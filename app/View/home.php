<?php require BASE_PATH . '/app/View/layout/header.php'; ?>

<div class="max-w-2xl">
    <h1 class="text-3xl font-bold text-gray-900 mb-4">Willkommen beim contentMonitor</h1>
    <p class="text-gray-600 mb-8 text-lg leading-relaxed">
        Überwachen Sie Textabschnitte auf Webseiten und werden Sie automatisch
        benachrichtigt, wenn sich Inhalte verändern.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="/add"
           class="block bg-green-600 text-white rounded-lg p-6 hover:bg-green-700 transition-colors">
            <div class="text-2xl mb-2">＋</div>
            <div class="font-semibold text-lg">Monitor hinzufügen</div>
            <div class="text-green-100 text-sm mt-1">URL eingeben, Textauswahl treffen, speichern</div>
        </a>
        <a href="/list"
           class="block bg-green-100 border border-green-400 rounded-lg p-6 hover:bg-green-200 hover:border-green-500 transition-all">
            <div class="text-2xl mb-2">📋</div>
            <div class="font-semibold text-lg text-green-900">Meine Monitore</div>
            <div class="text-green-800 text-sm mt-1">Alle überwachten Seiten auf einen Blick</div>
        </a>
    </div>
</div>

<?php require BASE_PATH . '/app/View/layout/footer.php'; ?>
