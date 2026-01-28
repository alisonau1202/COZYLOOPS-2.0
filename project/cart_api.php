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
$conn = mysqli_connect('localhost', 'root', '', 'project');
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

try {
	if ($method === 'POST') {
		// Add item to cart
		$input = json_decode(file_get_contents('php://input'), true);
		
		if (!isset($input['id'], $input['name'], $input['price'], $input['image'])) {
			http_response_code(400);
			echo json_encode(['error' => 'Missing required fields: id, name, price, image']);
			exit;
		}

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
		
		mysqli_stmt_bind_param($stmt, "iisds", $user_id, $input['id'], $input['name'], $input['price'], $input['image']);
		
		if (mysqli_stmt_execute($stmt)) {
			echo json_encode([
				'success' => true,
				'message' => 'Item added to cart',
				'item_id' => mysqli_insert_id($conn)
			]);
		} else {
			throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($stmt));
		}
		
		mysqli_stmt_close($stmt);

	} elseif ($method === 'GET') {
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
				'id' => $row['id'],
				'product_id' => $row['product_id'],
				'name' => $row['name'],
				'price' => floatval($row['price']),
				'image' => $row['image']
			];
		}
		
		mysqli_stmt_close($stmt);
		echo json_encode($items);

	} elseif ($method === 'DELETE') {
		// Remove items from cart
		$input = json_decode(file_get_contents('php://input'), true);
		
		if (isset($input['clear_all']) && $input['clear_all'] === true) {
			// Clear entire cart for current user
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
				throw new Exception('Failed to clear cart: ' . mysqli_stmt_error($stmt));
			}
			
			mysqli_stmt_close($stmt);
			
		} elseif (isset($input['id'])) {
			// Remove all instances of a specific product for current user
			$stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ? AND product_id = ?");
			if (!$stmt) {
				throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
			}
			
			mysqli_stmt_bind_param($stmt, "ii", $user_id, $input['id']);
			
			if (mysqli_stmt_execute($stmt)) {
				$affected_rows = mysqli_stmt_affected_rows($stmt);
				echo json_encode([
					'success' => true,
					'message' => 'Items removed from cart',
					'removed_count' => $affected_rows
				]);
			} else {
				throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($stmt));
			}
			
			mysqli_stmt_close($stmt);
		} else {
			http_response_code(400);
			echo json_encode(['error' => 'Missing product ID or clear_all flag']);
		}

	} elseif ($method === 'PATCH') {
		// Update cart items
		$input = json_decode(file_get_contents('php://input'), true);
		
		if (!isset($input['id'], $input['action'])) {
			http_response_code(400);
			echo json_encode(['error' => 'Missing required fields: id, action']);
			exit;
		}

		$productId = $input['id'];
		$action = $input['action'];

		if ($action === 'remove_one') {
			// Remove one instance of the product for current user
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
				throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($stmt));
			}
			
			mysqli_stmt_close($stmt);

		} elseif ($action === 'add_one') {
			// Add one instance of the product for current user
			if (!isset($input['name'], $input['price'], $input['image'])) {
				http_response_code(400);
				echo json_encode(['error' => 'Missing item details for add_one action']);
				exit;
			}

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
				throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($stmt));
			}
			
			mysqli_stmt_close($stmt);

		} else {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid action. Use "remove_one" or "add_one"']);
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