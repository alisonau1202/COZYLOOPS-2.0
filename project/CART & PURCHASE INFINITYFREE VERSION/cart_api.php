<?php
// Start session to access user data
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set response type
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(200);
	exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode([
		'error' => 'Unauthorized',
		'message' => 'Please log in to manage your cart'
	]);
	exit;
}

$user_id = $_SESSION['user_id'];

// Connect to MySQL
$conn = mysqli_connect('sql204.infinityfree.com', 'if0_39196567', 'Gl9kSBoo5L', 'if0_39196567_COS30043_Project');
if (!$conn) {
	http_response_code(500);
	echo json_encode([
		'error' => 'Failed to connect to database',
		'details' => mysqli_connect_error()
	]);
	exit;
}

// Set charset
mysqli_set_charset($conn, 'utf8');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true); // Get input for POST, PATCH, DELETE

try {
	if ($method === 'GET') {
		// Get all cart items for the current user
		$stmt = mysqli_prepare($conn, "SELECT * FROM cart WHERE user_id = ? ORDER BY id ASC");
		if (!$stmt) {
			throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
		}
		
		mysqli_stmt_bind_param($stmt, "i", $user_id);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		
		if (!$result) {
			throw new Exception('Failed to fetch cart items: ' . mysqli_error($conn));
		}
		
		$items = [];
		while ($row = mysqli_fetch_assoc($result)) {
			$items[] = [
				'id' => $row['id'], // This is the unique cart item ID
				'product_id' => $row['product_id'], // This is the ID of the product
				'name' => $row['name'],
				'price' => floatval($row['price']),
				'image' => $row['image']
			];
		}
		
		mysqli_stmt_close($stmt);
		echo json_encode($items);

	} elseif ($method === 'POST') {
		// Handle different POST actions: add item, clear cart, remove all of a specific item, add one item, remove one item
		
		// Determine the action. If 'action' is not set, assume it's 'add_item' for backward compatibility.
		$action = $input['action'] ?? 'add_item'; 

		// Case 1: Add item to cart (initial add from product page or explicit 'add_item' action)
		if ($action === 'add_item' && isset($input['id'], $input['name'], $input['price'], $input['image'])) {
			// Validate input
			if (!is_numeric($input['price']) || $input['price'] < 0) {
				http_response_code(400);
				echo json_encode(['error' => 'Invalid price']);
				exit;
			}

			$stmt = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, name, price, image) VALUES (?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
			}
			
			// Note: $input['id'] here is the product_id
			mysqli_stmt_bind_param($stmt, "iisds", $user_id, $input['id'], $input['name'], $input['price'], $input['image']);
			
			if (mysqli_stmt_execute($stmt)) {
				echo json_encode([
					'success' => true,
					'message' => 'Item added to cart',
					'item_id' => mysqli_insert_id($conn)
				]);
			} else {
				throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($conn));
			}
			
			mysqli_stmt_close($stmt);
		} 
		// Case 2: Clear entire cart
		elseif ($action === 'clear_cart') {
			$stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
			if (!$stmt) {
				throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
			}
			
			mysqli_stmt_bind_param($stmt, "i", $user_id);
			
			if (mysqli_stmt_execute($stmt)) {
				echo json_encode([
					'success' => true,
					'message' => 'Cart cleared successfully'
				]);
			} else {
				// Log the detailed error for debugging
				error_log("Failed to clear cart: " . mysqli_stmt_error($stmt));
				throw new Exception('Failed to clear cart: ' . mysqli_stmt_error($stmt));
			}
			
			mysqli_stmt_close($stmt);
		}
		// Case 3: Remove all instances of a specific product
		elseif ($action === 'remove_all_items' && isset($input['id'])) {
			$productId = intval($input['id']); // This ID is the product_id
			
			if (!$productId) {
				http_response_code(400);
				echo json_encode(['error' => 'Product ID is required for remove_all_items action']);
				exit;
			}

			$stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ? AND product_id = ?");
			if (!$stmt) {
				throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
			}
			
			mysqli_stmt_bind_param($stmt, "ii", $user_id, $productId);
			
			if (mysqli_stmt_execute($stmt)) {
				$affected_rows = mysqli_stmt_affected_rows($stmt);
				echo json_encode([
					'success' => true,
					'message' => 'All instances of item removed successfully',
					'removed_count' => $affected_rows
				]);
			} else {
				throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($stmt));
			}
			
			mysqli_stmt_close($stmt);
		}
		// Case 4: Add one instance of a product
		elseif ($action === 'add_one_item' && isset($input['id'], $input['name'], $input['price'], $input['image'])) {
			$productId = $input['id'];
			// Validate input
			if (!is_numeric($input['price']) || $input['price'] < 0) {
				http_response_code(400);
				echo json_encode(['error' => 'Invalid price']);
				exit;
			}

			$stmt = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, name, price, image) VALUES (?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
			}
			
			mysqli_stmt_bind_param($stmt, "iisds", $user_id, $productId, $input['name'], $input['price'], $input['image']);
			
			if (mysqli_stmt_execute($stmt)) {
				echo json_encode([
					'success' => true,
					'message' => 'One item added',
					'item_id' => mysqli_insert_id($conn)
				]);
			} else {
				throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($conn));
			}
			
			mysqli_stmt_close($stmt);
		}
		// Case 5: Remove one instance of a product
		elseif ($action === 'remove_one_item' && isset($input['id'])) {
			$productId = $input['id'];
			
			$stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ? AND product_id = ? LIMIT 1");
			if (!$stmt) {
				throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
			}
			
			mysqli_stmt_bind_param($stmt, "ii", $user_id, $productId);
			
			if (mysqli_stmt_execute($stmt)) {
				$affected_rows = mysqli_stmt_affected_rows($stmt);
				echo json_encode([
					'success' => true,
					'message' => 'One item removed',
					'removed' => $affected_rows > 0
				]);
			} else {
				throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($conn));
			}
			
			mysqli_stmt_close($stmt);
		}
		else {
			http_response_code(400);
			echo json_encode(['error' => 'Missing required fields or invalid action for POST request']);
		}

	} elseif ($method === 'DELETE') {
		// All specific DELETE actions (clear_all, remove_all_items)
		// have been moved to the POST method with an 'action' parameter.
		// Therefore, any direct DELETE request at this point is unexpected.
		http_response_code(405); // Method Not Allowed for specific DELETE operations now.
		echo json_encode(['error' => 'DELETE method is not supported for specific cart operations. Please use POST with an action parameter.']);

	} elseif ($method === 'PATCH') {
		// All PATCH actions are now deprecated in favor of POST with 'action' parameter.
		// The original 'remove_one' is now 'remove_one_item' in POST.
		// The original 'add_one' is now 'add_one_item' in POST.
		$input = json_decode(file_get_contents('php://input'), true); // Re-decode for safety
		if (isset($input['action'])) {
			http_response_code(405);
			echo json_encode(['error' => "PATCH method for action '{$input['action']}' is no longer supported. Please use POST instead."]);
		} else {
			http_response_code(405);
			echo json_encode(['error' => 'PATCH method is not supported for this endpoint. Please use POST with an action parameter.']);
		}

	} else {
		http_response_code(405);
		echo json_encode(['error' => 'Method not allowed']);
	}

} catch (Exception $e) {
	http_response_code(500);
	echo json_encode([
		'error' => 'Server error occurred',
		'message' => $e->getMessage()
	]);
} finally {
	// Close database connection
	if (isset($conn)) {
		mysqli_close($conn);
	}
}
?>
