<?php require BASE_PATH . '/app/View/layout/header.php'; ?>

<div class="max-w-md mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Anmelden</h1>

    <?php if ($error !== null): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-5 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/login"
          class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 space-y-5">

        <div>
            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                Benutzername
            </label>
            <input type="text" name="username" id="username" required autocomplete="username"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                Passwort
            </label>
            <input type="password" name="password" id="password" required autocomplete="current-password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
        </div>

        <button type="submit"
                class="w-full bg-green-600 text-white py-2 rounded-lg font-medium
                       hover:bg-green-700 transition-colors text-sm">
            Anmelden
        </button>
    </form>
</div>

<?php require BASE_PATH . '/app/View/layout/footer.php'; ?>
