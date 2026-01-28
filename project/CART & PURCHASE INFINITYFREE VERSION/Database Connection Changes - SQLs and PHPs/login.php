<?php
// Prevent any output before JSON response
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

session_start(); // Start a session to store user information

// Handle POST requests for login
if (
	$_SERVER['REQUEST_METHOD'] === 'POST' &&
	isset($_SERVER['CONTENT_TYPE']) &&
	strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0
) {
	// Clear any previous output
	ob_clean();
	header("Content-Type: application/json");

	try {
		// Parse JSON input
		$input = json_decode(file_get_contents('php://input'), true);
		if (!$input || !isset($input['email'], $input['password'])) {
			http_response_code(400);
			echo json_encode(['message' => 'Invalid input']);
			exit;
		}

		$email = trim($input['email']);
		$password = $input['password'];

		// Database connection using prepared statements
		$conn = mysqli_connect('sql204.infinityfree.com', 'if0_39196567', 'Gl9kSBoo5L', 'if0_39196567_COS30043_Project');
		if (!$conn) {
			http_response_code(500);
			echo json_encode(['message' => 'Database connection failed: ' . mysqli_connect_error()]);
			exit;
		}

		mysqli_set_charset($conn, 'utf8');

		// Use prepared statement to prevent SQL injection
		$query = "SELECT * FROM users WHERE email = ?";
		$stmt = mysqli_prepare($conn, $query);

		if (!$stmt) {
			http_response_code(500);
			echo json_encode(['message' => 'Database query failed']);
			mysqli_close($conn);
			exit;
		}

		mysqli_stmt_bind_param($stmt, "s", $email);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		if ($user = mysqli_fetch_assoc($result)) {
			if (password_verify($password, $user['password'])) {
				// Store user data in session
				$_SESSION['user_id'] = $user['id'];
				$_SESSION['user_name'] = $user['name'];
				$_SESSION['user_email'] = $user['email'];
				$_SESSION['logged_in'] = true;

				echo json_encode([
					'message' => 'Login successful',
					'user' => [
						'id' => $user['id'],
						'name' => $user['name'],
						'email' => $user['email']
					],
					'redirectUrl' => 'index.php'
				]);
			} else {
				http_response_code(401);
				echo json_encode(['message' => 'Incorrect password']);
			}
		} else {
			http_response_code(404);
			echo json_encode(['message' => 'User not found']);
		}

		mysqli_stmt_close($stmt);
		mysqli_close($conn);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode(['message' => 'Server error: ' . $e->getMessage()]);
	}

	exit;
}

// Check if user is already logged in via session
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
	header('Location: index.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>Login - CozyLoops</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<!-- Vuetify CSS and Bootstrap-->
	<link href="https://cdn.jsdelivr.net/npm/vuetify@3.5.0/dist/vuetify.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

	<!-- Font Awesome & Fonts -->
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;700&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/@mdi/font@latest/css/materialdesignicons.min.css" rel="stylesheet">

	<!-- Vue & Vuetify JS -->
	<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/vuetify@3.5.0/dist/vuetify.min.js"></script>

	<!-- Custom Styles & Script -->
	<link rel="stylesheet" href="style/style.css">
</head>
<body>
	<div id="app"></div>
	
	<!-- Load Bootstrap JS first -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	
	<!-- Load your profile Vue app last -->
	<script src="login.js" defer></script>
</body>
</html>
