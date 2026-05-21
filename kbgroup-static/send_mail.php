<?php
// Capturer toutes les erreurs PHP et les retourner en JSON
set_error_handler(function($errno, $errstr) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur PHP: $errstr"]);
    exit;
});
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Exception: " . $e->getMessage()]);
    exit;
});

header('Content-Type: application/json; charset=UTF-8');
$allowed_origins = [
    'https://localhost',
    'https://kbgroupesarl.com',
    'https://www.kbgroupesarl.com',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://kbgroupesarl.com');
}
header('Access-Control-Allow-Methods: POST');

// ── Vérification PHPMailer ───────────────────────────────────────
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PHPMailer non installé. Exécutez : composer require phpmailer/phpmailer'
    ]);
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Configuration Gmail ──────────────────────────────────────────
define('GMAIL_USER',     'Contact');
define('GMAIL_PASSWORD', 'ghjo tjmg kzkj lcym');
define('DESTINATAIRE',   'contact@kbgroupesarl.com');
define('RECAPTCHA_SECRET', '');

// ── Sécurité : POST uniquement ───────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

// ── Vérification reCAPTCHA (désactivée sur localhost) ────────────
$recaptcha_token = $_POST['recaptcha_token'] ?? '';
$is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);

if (!$is_local) {
    if (empty($recaptcha_token)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Token reCAPTCHA manquant.']);
        exit;
    }
    $verify = file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify?secret=' .
        RECAPTCHA_SECRET . '&response=' . urlencode($recaptcha_token)
    );
    $captcha_result = json_decode($verify, true);
    if (!$captcha_result['success']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Vérification anti-robot échouée. Veuillez réessayer.']);
        exit;
    }
}

// ── Nettoyage des données ────────────────────────────────────────
function clean(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

$nom     = clean($_POST['name']    ?? '');
$email   = clean($_POST['email']   ?? '');
$objet   = clean($_POST['objet']   ?? '');
$message = clean($_POST['message'] ?? '');

// ── Validation ───────────────────────────────────────────────────
if (empty($nom)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Le nom est obligatoire.']);
    exit;
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Adresse email invalide.']);
    exit;
}
if (empty($message) || strlen($message) < 10) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Le message doit contenir au moins 10 caractères.']);
    exit;
}
if (empty($objet)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => "L'objet est obligatoire."]);
    exit;
}

// ── Envoi avec PHPMailer ─────────────────────────────────────────
$mail = new PHPMailer(true);

try {
    // Serveur SMTP Gmail
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = GMAIL_USER;
    $mail->Password   = GMAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // Expéditeur et destinataire
    $mail->setFrom(GMAIL_USER, 'K&B Group - Formulaire Contact');
    $mail->addAddress(DESTINATAIRE, 'Colette Koubissack');
    $mail->addReplyTo($email, $nom);

    // Contenu de l'email
    $mail->isHTML(true);
    $mail->Subject = '[K&B Group] ' . $objet . ' — ' . $nom;
    $mail->Body    = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #1e2846; padding: 24px; border-radius: 8px 8px 0 0;'>
                <h2 style='color: #fff; margin: 0;'>Nouveau message — K&B Group</h2>
            </div>
            <div style='background: #f9fafb; padding: 24px; border: 1px solid #e5e7eb;'>
                <p><strong>Nom :</strong> {$nom}</p>
                <p><strong>Email :</strong> {$email}</p>
                <p><strong>Objet :</strong> {$objet}</p>
                <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 16px 0;'>
                <p><strong>Message :</strong></p>
                <p style='background: #fff; padding: 16px; border-radius: 6px; border: 1px solid #e5e7eb;'>" . nl2br($message) . "</p>
            </div>
            <div style='background: #e5e7eb; padding: 12px 24px; border-radius: 0 0 8px 8px; font-size: 12px; color: #6b7280;'>
                Envoyé le " . date('d/m/Y à H:i') . " via kbgroupesarl.com
            </div>
        </div>
    ";

    $mail->send();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'envoi : ' . $mail->ErrorInfo
    ]);
}