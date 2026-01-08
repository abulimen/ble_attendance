<?php
/**
 * Displays error and success messages.
 * Assumes $errors (array) and $success_message (string) variables might be set in the parent scope.
 */
?>

<?php if (!empty($errors) && is_array($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Error(s):</strong>
        <ul>
            <?php foreach ($errors as $error_msg): ?>
                <li><?= escape_html($error_msg) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= escape_html($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
