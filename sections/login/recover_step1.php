<?php

$validator = new Gazelle\Util\Validator;
$validator->setField('email', true, 'email', 'You entered an invalid email address.');

$error = false;
$sent  = false;
if (isset($_REQUEST['expired'])) {
    $error = 'The link you followed has expired.';
} elseif (!empty($_REQUEST['email'])) {
    $error = $validator->validate($_REQUEST) ? false : $validator->errorMessage();
    if (!$error) {
        $user = (new Gazelle\Manager\User)->findByEmail(trim($_REQUEST['email']));
        if ($user) {
            $user->resetPassword();
            $user->logoutEverywhere();
            $sent = true;
        }
        $error = "Email sent with further instructions.";
    }
}

echo $Twig->render('login/reset-password.twig', [
    'error' => $error,
    'js'    => $validator->generateJS('recoverform'),
    'sent'  => $sent,
]);
