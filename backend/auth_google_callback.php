<?php
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_google.php';

$settings = synk_auth_settings();
$portal = (string)($_SESSION['google_oauth_portal'] ?? '');
$isStudentPortal = $portal === 'student'
    || isset($_SESSION['student_google_oauth_state'])
    || isset($_SESSION['student_google_oauth_nonce']);
$redirectTarget = $isStudentPortal ? '../student/login.php' : '../index.php';
$stateSessionKey = $isStudentPortal ? 'student_google_oauth_state' : 'google_oauth_state';
$nonceSessionKey = $isStudentPortal ? 'student_google_oauth_nonce' : 'google_oauth_nonce';

if (!synk_google_login_enabled($settings) || !synk_google_auth_ready($settings)) {
    synk_auth_status_redirect('google_unavailable', $redirectTarget);
}

$incomingState = trim((string)($_GET['state'] ?? ''));
$storedState = (string)($_SESSION[$stateSessionKey] ?? '');
$storedNonce = (string)($_SESSION[$nonceSessionKey] ?? '');
unset(
    $_SESSION['google_oauth_state'],
    $_SESSION['google_oauth_nonce'],
    $_SESSION['student_google_oauth_state'],
    $_SESSION['student_google_oauth_nonce'],
    $_SESSION['google_oauth_portal']
);

if ($incomingState === '' || $storedState === '' || !hash_equals($storedState, $incomingState)) {
    synk_auth_status_redirect('google_state_invalid', $redirectTarget);
}

if (!empty($_GET['error'])) {
    synk_auth_status_redirect('google_cancelled', $redirectTarget);
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    synk_auth_status_redirect('google_code_missing', $redirectTarget);
}

$tokenResponse = synk_google_exchange_code($settings, $code);
if (!$tokenResponse['ok']) {
    synk_auth_status_redirect('google_token_failed', $redirectTarget);
}

$tokenJson = $tokenResponse['json'];
$accessToken = trim((string)($tokenJson['access_token'] ?? ''));
if ($accessToken === '') {
    synk_auth_status_redirect('google_token_missing', $redirectTarget);
}

$idToken = trim((string)($tokenJson['id_token'] ?? ''));
if ($idToken === '') {
    synk_auth_status_redirect('google_id_token_missing', $redirectTarget);
}

$idTokenClaims = synk_google_decode_id_token($idToken);
$idTokenStatus = synk_google_validate_id_token_claims($settings, $idToken, $idTokenClaims, $storedNonce);
if ($idTokenStatus !== null) {
    synk_auth_status_redirect($idTokenStatus, $redirectTarget);
}

$userInfoResponse = synk_google_fetch_userinfo($accessToken);
if (!$userInfoResponse['ok']) {
    synk_auth_status_redirect('google_profile_failed', $redirectTarget);
}

$userInfo = $userInfoResponse['json'];
$googleSub = trim((string)($userInfo['sub'] ?? ''));
$email = synk_normalize_email((string)($userInfo['email'] ?? ''));
$displayName = trim((string)($userInfo['name'] ?? ''));
$pictureUrl = trim((string)($userInfo['picture'] ?? ''));
$emailVerified = !empty($userInfo['email_verified']);
$idTokenEmail = synk_normalize_email((string)($idTokenClaims['email'] ?? ''));
$idTokenSub = trim((string)($idTokenClaims['sub'] ?? ''));

if ($googleSub === '' || $email === '' || !$emailVerified) {
    synk_auth_status_redirect('google_profile_invalid', $redirectTarget);
}

if (($idTokenEmail !== '' && !hash_equals($idTokenEmail, $email))
    || ($idTokenSub !== '' && !hash_equals($idTokenSub, $googleSub))) {
    synk_auth_status_redirect('google_email_mismatch', $redirectTarget);
}

if (!synk_is_allowed_email_domain($email, (string)$settings['allowed_domain'])) {
    synk_auth_status_redirect('email_domain_denied', $redirectTarget);
}

if ($isStudentPortal) {
    synk_complete_student_login($email, $displayName, $googleSub);

    if ($pictureUrl !== '') {
        $_SESSION['user_avatar_url'] = $pictureUrl;
    }

    header('Location: ../student/');
    exit;
}

$user = synk_find_useraccount_by_email($conn, $email);
if (!$user) {
    synk_auth_status_redirect('account_not_allowed', $redirectTarget);
}

if (($user['status'] ?? '') !== 'active') {
    synk_auth_status_redirect('account_inactive', $redirectTarget);
}

$role = (string)($user['role'] ?? '');
if (!in_array($role, synk_supported_module_roles(), true)) {
    synk_auth_status_redirect('role_not_supported', $redirectTarget);
}

if ($role === 'scheduler' && !synk_scheduler_account_has_access($conn, $user)) {
    synk_auth_status_redirect('account_incomplete', $redirectTarget);
}

$existingGoogleSub = trim((string)($user['google_sub'] ?? ''));
if ($existingGoogleSub !== '' && !hash_equals($existingGoogleSub, $googleSub)) {
    synk_auth_status_redirect('google_identity_mismatch', $redirectTarget);
}

synk_record_google_login($conn, (int)$user['user_id'], $googleSub, $emailVerified, $displayName);
synk_complete_user_login($user, $conn);

if ($pictureUrl !== '') {
    $_SESSION['user_avatar_url'] = $pictureUrl;
}

$redirectPath = synk_role_redirect_path($role);
if ($redirectPath === null) {
    synk_auth_status_redirect('role_not_supported', $redirectTarget);
}

header('Location: ../' . $redirectPath);
exit;
