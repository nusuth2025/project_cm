<?php require BASE_PATH . '/app/View/layout/header.php'; ?>

<div class="mb-6">
    <a href="javascript:history.back()"
       class="text-sm text-gray-500 hover:text-gray-700 mb-2 inline-block transition-colors">
        zurück →
    </a>
    <h1 class="text-2xl font-bold text-gray-900">Konto-Einstellungen</h1>
</div>

<div class="max-w-lg space-y-6">

    <!-- E-Mail ändern -->
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <h2 class="font-semibold text-gray-800 mb-4">E-Mail-Adresse ändern</h2>
        <div class="text-sm text-gray-500 mb-4">
            Aktuelle Adresse: <span class="font-medium text-gray-700"><?= htmlspecialchars($user['email']) ?></span>
        </div>

        <?php if ($emailSuccess !== null): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm mb-4">
                <?= htmlspecialchars($emailSuccess) ?>
            </div>
        <?php endif; ?>
        <?php if ($emailError !== null): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm mb-4">
                <?= htmlspecialchars($emailError) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/settings" class="space-y-4">
            <div>
                <label for="new_email" class="block text-sm font-medium text-gray-700 mb-1">Neue E-Mail-Adresse</label>
                <input type="email" name="new_email" id="new_email" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
            <div>
                <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1">
                    Passwort zur Bestätigung
                </label>
                <input type="password" name="password_confirm" id="password_confirm" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
            <button type="submit" name="change_email" value="1"
                    class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium
                           hover:bg-green-700 transition-colors">
                E-Mail aktualisieren
            </button>
        </form>
    </div>

    <!-- Passwort ändern -->
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <h2 class="font-semibold text-gray-800 mb-4">Passwort ändern</h2>

        <?php if ($passwordSuccess !== null): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm mb-4">
                <?= htmlspecialchars($passwordSuccess) ?>
            </div>
        <?php endif; ?>
        <?php if ($passwordError !== null): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm mb-4">
                <?= htmlspecialchars($passwordError) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/settings" class="space-y-4">
            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">
                    Aktuelles Passwort
                </label>
                <input type="password" name="current_password" id="current_password" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                    Neues Passwort <span class="text-gray-400">(min. 8 Zeichen)</span>
                </label>
                <input type="password" name="new_password" id="new_password" required minlength="8"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                    Neues Passwort bestätigen
                </label>
                <input type="password" name="confirm_password" id="confirm_password" required minlength="8"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>
            <button type="submit" name="change_password" value="1"
                    class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium
                           hover:bg-green-700 transition-colors">
                Passwort ändern
            </button>
        </form>
    </div>

</div>

<?php require BASE_PATH . '/app/View/layout/footer.php'; ?>
