<?php
session_start();
header('Content-Type: application/json');

// Database connection
$host = 'sql204.infinityfree.com';
$db = 'if0_39196567_COS30043_Project';
$user = 'if0_39196567';
$pass = 'Gl9kSBoo5L';
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
	echo json_encode(['error' => 'Database connection failed']);
	exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$user_id = $_SESSION['user_id'];

// Parse request 
$input = json_decode(file_get_contents("php://input"), true);
$action = $_GET['action'] ?? ($input['action'] ?? '');

// Log for debugging
error_log("Action: " . $action);
error_log("Input: " . file_get_contents("php://input"));

// ============ USER PROFILE OPERATIONS ============

if ($action === 'update_user') {
	$user = $input['user'] ?? [];
	$name = trim($user['name'] ?? '');
	$email = trim($user['email'] ?? '');
	$password = $user['password'] ?? ''; // Keep as string, can be empty
	$password_confirm = $user['password_confirm'] ?? ''; // New field
	
	error_log("User update data: " . json_encode($user));
	
	if (!$name || !$email) {
		echo json_encode(['error' => 'Name and email are required', 'fields' => ['name' => $name, 'email' => $email]]);
		exit;
	}
	
	// Check if email is valid
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		echo json_encode(['error' => 'Invalid email format']);
		exit;
	}
	
	// Check if email is already taken by another user
	$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
	$stmt->bind_param("si", $email, $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	
	if ($result->num_rows > 0) {
		echo json_encode(['error' => 'Email is already taken by another user']);
		exit;
	}
	
	// If password is provided, validate it and its confirmation
	if (!empty($password)) {
		if ($password !== $password_confirm) {
			echo json_encode(['error' => 'Password and confirmation do not match']);
			exit;
		}

		// Validate password strength
		if (strlen($password) < 8) {
			echo json_encode(['error' => 'Password must be at least 8 characters']);
			exit;
		}
		
		if (!preg_match('/[A-Z]/', $password)) {
			echo json_encode(['error' => 'Password must contain at least one uppercase letter']);
			exit;
		}
		
		if (!preg_match('/[0-9]/', $password)) {
			echo json_encode(['error' => 'Password must contain at least one number']);
			exit;
		}
		
		if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
			echo json_encode(['error' => 'Password must contain at least one special character']);
			exit;
		}
		
		$hashed = password_hash($password, PASSWORD_DEFAULT);
		$stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
		$stmt->bind_param("sssi", $name, $email, $hashed, $user_id);
	} else {
		// Update without changing password
		$stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
		$stmt->bind_param("ssi", $name, $email, $user_id);
	}
	
	if ($stmt->execute()) {
		// Update session data
		$_SESSION['user_name'] = $name;
		$_SESSION['user_email'] = $email;
		
		echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
	} else {
		echo json_encode(['error' => 'Failed to update profile: ' . $conn->error]);
	}
	exit;
}

// ============ ADDRESS OPERATIONS ============

// Get all addresses for the current user
if ($action === 'get_addresses') {
	$stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY created_at DESC");
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	
	$addresses = [];
	while ($row = $result->fetch_assoc()) {
		$addresses[] = $row;
	}
	
	echo json_encode(['success' => true, 'addresses' => $addresses]);
	exit;
}

// Create a new address
if ($action === 'create_address') {
	$address = $input['address'] ?? [];
	
	$name = trim($address['name'] ?? '');
	$phone = trim($address['phone'] ?? '');
	$unit_number = trim($address['unit_number'] ?? '');
	$street = trim($address['street'] ?? '');
	$city = trim($address['city'] ?? '');
	$state = trim($address['state'] ?? '');
	$postcode = trim($address['postcode'] ?? '');
	$country = trim($address['country'] ?? 'Malaysia');
	
	// Validate required fields
	if (!$name || !$phone || !$street || !$city || !$state || !$postcode || !$country) {
		echo json_encode(['error' => 'All required fields must be filled (name, phone, street, city, state, postcode, country)']);
		exit;
	}
	
	// Validate phone number - allow international formats with country codes
	if (!preg_match('/^[\+]?[\d\s\-\(\)]+$/', $phone) || strlen(preg_replace('/[\s\-\(\)\+]/', '', $phone)) < 7) {
		echo json_encode(['error' => 'Please enter a valid phone number']);
		exit;
	}
	
	// Validate postcode based on country
	if ($country === 'Malaysia') {
		if (!preg_match('/^\d{5}$/', $postcode)) {
			echo json_encode(['error' => 'Malaysian postcode must be 5 digits']);
			exit;
		}
	} else {
		// Generic postcode validation for other countries
		if (!preg_match('/^[A-Za-z0-9\s\-]{3,10}$/', $postcode)) {
			echo json_encode(['error' => 'Please enter a valid postcode']);
			exit;
		}
	}
	
	// Insert new address
	$stmt = $conn->prepare("INSERT INTO addresses (user_id, name, phone, unit_number, street, city, state, postcode, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
	$stmt->bind_param("issssssss", $user_id, $name, $phone, $unit_number, $street, $city, $state, $postcode, $country);
	
	if ($stmt->execute()) {
		echo json_encode(['success' => true, 'message' => 'Address created successfully', 'address_id' => $conn->insert_id]);
	} else {
		echo json_encode(['error' => 'Failed to create address: ' . $conn->error]);
	}
	exit;
}

// Update an existing address
if ($action === 'update_address') {
	$address = $input['address'] ?? [];
	
	$address_id = intval($address['id'] ?? 0);
	$name = trim($address['name'] ?? '');
	$phone = trim($address['phone'] ?? '');
	$unit_number = trim($address['unit_number'] ?? '');
	$street = trim($address['street'] ?? '');
	$city = trim($address['city'] ?? '');
	$state = trim($address['state'] ?? '');
	$postcode = trim($address['postcode'] ?? '');
	$country = trim($address['country'] ?? 'Malaysia');
	
	if (!$address_id) {
		echo json_encode(['error' => 'Address ID is required']);
		exit;
	}
	
	// Validate required fields
	if (!$name || !$phone || !$street || !$city || !$state || !$postcode || !$country) {
		echo json_encode(['error' => 'All required fields must be filled (name, phone, street, city, state, postcode, country)']);
		exit;
	}
	
	// Validate phone number - allow international formats with country codes
	if (!preg_match('/^[\+]?[\d\s\-\(\)]+$/', $phone) || strlen(preg_replace('/[\s\-\(\)\+]/', '', $phone)) < 7) {
		echo json_encode(['error' => 'Please enter a valid phone number']);
		exit;
	}
	
	// Validate postcode based on country
	if ($country === 'Malaysia') {
		if (!preg_match('/^\d{5}$/', $postcode)) {
			echo json_encode(['error' => 'Malaysian postcode must be 5 digits']);
			exit;
		}
	} else {
		// Generic postcode validation for other countries
		if (!preg_match('/^[A-Za-z0-9\s\-]{3,10}$/', $postcode)) {
			echo json_encode(['error' => 'Please enter a valid postcode']);
			exit;
		}
	}
	
	// Check if address belongs to current user
	$stmt = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
	$stmt->bind_param("ii", $address_id, $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	
	if ($result->num_rows === 0) {
		echo json_encode(['error' => 'Address not found or unauthorized']);
		exit;
	}
	
	// Update address
	$stmt = $conn->prepare("UPDATE addresses SET name = ?, phone = ?, unit_number = ?, street = ?, city = ?, state = ?, postcode = ?, country = ? WHERE id = ? AND user_id = ?");
	$stmt->bind_param("ssssssssii", $name, $phone, $unit_number, $street, $city, $state, $postcode, $country, $address_id, $user_id);
	
	if ($stmt->execute()) {
		echo json_encode(['success' => true, 'message' => 'Address updated successfully']);
	} else {
		echo json_encode(['error' => 'Failed to update address: ' . $conn->error]);
	}
	exit;
}

// Delete an address
if ($action === 'delete_address') {
	$address_id = intval($input['address_id'] ?? 0);
	
	if (!$address_id) {
		echo json_encode(['error' => 'Address ID is required']);
		exit;
	}
	
	// Check if address belongs to current user
	$stmt = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
	$stmt->bind_param("ii", $address_id, $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	
	if ($result->num_rows === 0) {
		echo json_encode(['error' => 'Address not found or unauthorized']);
		exit;
	}
	
	// Delete address
	$stmt = $conn->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
	$stmt->bind_param("ii", $address_id, $user_id);
	
	if ($stmt->execute()) {
		echo json_encode(['success' => true, 'message' => 'Address deleted successfully']);
	} else {
		echo json_encode(['error' => 'Failed to delete address: ' . $conn->error]);
	}
	exit;
}

// If no valid action is provided
http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
?>
