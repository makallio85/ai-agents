<?php
/**
 * @var \App\View\AppView $this
 */
$this->layout = 'auth';
$this->assign('title', 'Sign in');
?>
<h5 class="card-title fw-bold mb-1">Welcome back</h5>
<p class="text-muted small mb-4">Sign in to your account to continue</p>

<div id="login-app"></div>

<?php
$this->append('script', $this->Html->script('vue/api'));
$this->append('script', $this->Html->script('vue/pages/Login/index'));
?>
