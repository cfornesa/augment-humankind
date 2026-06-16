<?php
declare(strict_types=1);

$commentUrl = $commentUrl ?? '/api/posts/' . (int) $postId . '/comments';
$signinRedirect = $signinRedirect ?? ($_SERVER['REQUEST_URI'] ?? '/blog/posts/' . (int) $postId);
require dirname(__DIR__) . '/partials/comment-form.php';
?>
