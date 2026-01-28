<?php
// Handle POST request with JSON content
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
	isset($_SERVER['CONTENT_TYPE']) &&
	strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0) {

	header("Content-Type: application/json");

	// Decode JSON input
	$input = json_decode(file_get_contents('php://input'), true);
	if (!$input || !isset($input['name'], $input['email'], $input['password'])) {
		http_response_code(400);
		echo json_encode(['message' => 'Invalid input']);
		exit;
	}

	// Extract values
	$name = $input['name'];
	$email = $input['email'];
	$password = $input['password'];

	// Connect to MySQL
	$conn = mysqli_connect('localhost', 'root', '', 'project');
	if (!$conn) {
		http_response_code(500);
		echo json_encode(['message' => 'Database connection failed']);
		exit;
	}

	// Ensure UTF-8 encoding
	mysqli_set_charset($conn, 'utf8');

	// Check if email already exists
	$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
	$stmt->bind_param("s", $email);
	$stmt->execute();
	$stmt->store_result();

	if ($stmt->num_rows > 0) {
		http_response_code(409);
		echo json_encode(['message' => 'Email already registered']);
		exit;
	}

	// Hash the password
	$passwordHash = password_hash($password, PASSWORD_DEFAULT);

	// Insert new user
	$stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
	$stmt->bind_param("sss", $name, $email, $passwordHash);

	if ($stmt->execute()) {
		http_response_code(201);
		echo json_encode(['message' => 'User registered successfully', 'redirectUrl' => 'login.php']);
	} else {
		http_response_code(500);
		echo json_encode(['message' => 'Failed to register user']);
	}

	// Clean up
	$stmt->close();
	mysqli_close($conn);
	exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<title>Register - CozyLoops</title>
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
	<script src="registration.js" defer></script>
</body>
</html>
