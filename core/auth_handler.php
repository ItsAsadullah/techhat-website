<?php
require_once 'auth.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $response['message'] = 'Invalid CSRF token';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'] ?? '';

// Login Action
if ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $response['message'] = 'Email and password are required';
        echo json_encode($response);
        exit;
    }
    
    if (login($email, $password)) {
        $response['success'] = true;
        $response['message'] = 'Login successful';
        
        // Check if user came from checkout
        if (isset($_SESSION['checkout_redirect']) && $_SESSION['checkout_redirect']) {
            unset($_SESSION['checkout_redirect']);
            $response['redirect'] = 'checkout.php';
        } elseif (is_admin()) {
            $response['redirect'] = 'admin/index.php';
        } else {
            // Stay on current page (sent from frontend)
            $response['redirect'] = $_POST['current_page'] ?? 'index.php';
        }
    } else {
        $response['message'] = 'Invalid email or password';
    }
}

// Register Action
elseif ($action === 'register') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        $response['message'] = 'All fields are required';
        echo json_encode($response);
        exit;
    }
    
    if ($password !== $confirm_password) {
        $response['message'] = 'Passwords do not match';
        echo json_encode($response);
        exit;
    }
    
    $result = register($name, $email, $password, $phone);
    
    if ($result === true) {
        // Auto-login after registration
        login($email, $password);
        
        $response['success'] = true;
        $response['message'] = 'Registration successful!';
        
        // Check if user came from checkout
        if (isset($_SESSION['checkout_redirect']) && $_SESSION['checkout_redirect']) {
            unset($_SESSION['checkout_redirect']);
            $response['redirect'] = 'checkout.php';
        } else {
            // Stay on current page (sent from frontend)
            $response['redirect'] = $_POST['current_page'] ?? 'index.php';
        }
    } else {
        $response['message'] = $result;
    }
}

echo json_encode($response);
