<div class="admin-container">
    <div class="login-box">
        <h2>🔐 Admin</h2>
        <?php if (!empty($loginError)): ?>
            <div class="error-msg"><?php echo e($loginError); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Mot de passe" required autofocus>
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken ?? ''); ?>">
            <button type="submit">Connexion</button>
        </form>
        <p class="login-return"><a href="index.php">← Retour</a></p>
        <p class="login-return"><a href="confidentialite.php">Politique de confidentialité</a></p>
    </div>
</div>
