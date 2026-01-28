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
		'message' => 'Please log in to access your orders'
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
	if ($method === 'GET') {
		// Get all purchases for the current user with their items
		$purchasesQuery = "
			SELECT p.*, 
				   COUNT(pi.id) as item_count,
				   SUM(pi.quantity) as total_items
			FROM purchases p 
			LEFT JOIN purchase_items pi ON p.id = pi.purchase_id 
			WHERE p.user_id = ?
			GROUP BY p.id 
			ORDER BY p.order_date DESC
		";
		
		$purchasesStmt = mysqli_prepare($conn, $purchasesQuery);
		if (!$purchasesStmt) {
			throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
		}
		
		mysqli_stmt_bind_param($purchasesStmt, "i", $user_id);
		mysqli_stmt_execute($purchasesStmt);
		$purchasesResult = mysqli_stmt_get_result($purchasesStmt);
		
		if (!$purchasesResult) {
			throw new Exception('Failed to fetch purchases: ' . mysqli_error($conn));
		}
		
		$purchases = [];
		while ($row = mysqli_fetch_assoc($purchasesResult)) {
			// Get items for this purchase
			$itemsQuery = "SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY id ASC";
			$itemsStmt = mysqli_prepare($conn, $itemsQuery);
			mysqli_stmt_bind_param($itemsStmt, "i", $row['id']);
			mysqli_stmt_execute($itemsStmt);
			$itemsResult = mysqli_stmt_get_result($itemsStmt);
			
			$items = [];
			while ($itemRow = mysqli_fetch_assoc($itemsResult)) {
				$items[] = [
					'id' => (int)$itemRow['id'],
					'product_id' => (int)$itemRow['product_id'],
					'name' => $itemRow['name'],
					'price' => (float)$itemRow['price'],
					'quantity' => (int)$itemRow['quantity'],
					'image' => $itemRow['image']
				];
			}
			mysqli_stmt_close($itemsStmt);
			
			$purchases[] = [
				'id' => (int)$row['id'],
				'order_number' => $row['order_number'],
				'total_amount' => (float)$row['total_amount'],
				'shipping_cost' => (float)$row['shipping_cost'],
				'order_status' => $row['order_status'],
				'order_date' => $row['order_date'],
				'updated_at' => $row['updated_at'],
				'item_count' => (int)$row['item_count'],
				'total_items' => (int)$row['total_items'],
				'items' => $items
			];
		}
		
		mysqli_stmt_close($purchasesStmt);
		echo json_encode($purchases);

	} elseif ($method === 'POST') {
		// Create new purchase from selected cart items
		$input = json_decode(file_get_contents('php://input'), true);
		
		// Start transaction
		mysqli_begin_transaction($conn);
		
		try {
			// Generate order number
			$orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
			
			// Check if order number exists and regenerate if needed
			$checkStmt = mysqli_prepare($conn, "SELECT id FROM purchases WHERE order_number = ?");
			mysqli_stmt_bind_param($checkStmt, "s", $orderNumber);
			mysqli_stmt_execute($checkStmt);
			$checkResult = mysqli_stmt_get_result($checkStmt);
			
			while (mysqli_num_rows($checkResult) > 0) {
				$orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
				mysqli_stmt_bind_param($checkStmt, "s", $orderNumber);
				mysqli_stmt_execute($checkStmt);
				$checkResult = mysqli_stmt_get_result($checkStmt);
			}
			mysqli_stmt_close($checkStmt);
			
			// Handle different request formats
			if (isset($input['action']) && $input['action'] === 'create_from_selected_cart') {
				// New format: selected items passed directly
				if (!isset($input['selected_items']) || empty($input['selected_items'])) {
					throw new Exception('No items selected for checkout');
				}
				
				$selectedItems = $input['selected_items'];
				$subtotal = isset($input['subtotal']) ? (float)$input['subtotal'] : 0;
				$shippingCost = isset($input['shipping']) ? (float)$input['shipping'] : 0;
				$totalAmount = isset($input['total']) ? (float)$input['total'] : ($subtotal + $shippingCost);
				
				// Validate the calculations
				$calculatedSubtotal = 0;
				foreach ($selectedItems as $item) {
					$calculatedSubtotal += (float)$item['price'] * (int)$item['quantity'];
				}
				
				// Allow small floating point differences
				if (abs($calculatedSubtotal - $subtotal) > 0.01) {
					throw new Exception('Subtotal mismatch detected');
				}
				
				$calculatedShipping = $calculatedSubtotal >= 50.00 ? 0.00 : 5.00;
				if (abs($calculatedShipping - $shippingCost) > 0.01) {
					throw new Exception('Shipping cost mismatch detected');
				}
				
			} else {
				// Legacy format: get all cart items for user
				$cartStmt = mysqli_prepare($conn, "SELECT * FROM cart WHERE user_id = ?");
				if (!$cartStmt) {
					throw new Exception('Failed to prepare cart statement: ' . mysqli_error($conn));
				}
				
				mysqli_stmt_bind_param($cartStmt, "i", $user_id);
				mysqli_stmt_execute($cartStmt);
				$cartResult = mysqli_stmt_get_result($cartStmt);
				
				$cartItems = [];
				while ($row = mysqli_fetch_assoc($cartResult)) {
					$cartItems[] = $row;
				}
				mysqli_stmt_close($cartStmt);
				
				if (empty($cartItems)) {
					throw new Exception('Cart is empty');
				}
				
				// Group cart items by product_id and calculate totals
				$groupedItems = [];
				$subtotal = 0;
				
				foreach ($cartItems as $item) {
					$productId = $item['product_id'];
					if (!isset($groupedItems[$productId])) {
						$groupedItems[$productId] = [
							'product_id' => $productId,
							'name' => $item['name'],
							'price' => (float)$item['price'],
							'image' => $item['image'],
							'quantity' => 0
						];
					}
					$groupedItems[$productId]['quantity']++;
					$subtotal += (float)$item['price'];
				}
				
				$selectedItems = array_values($groupedItems);
				$shippingCost = $subtotal >= 50.00 ? 0.00 : 5.00;
				$totalAmount = $subtotal + $shippingCost;
			}
			
			// Insert purchase record with user_id
			$purchaseStmt = mysqli_prepare($conn, "
				INSERT INTO purchases (user_id, order_number, total_amount, shipping_cost, order_status) 
				VALUES (?, ?, ?, ?, 'pending')
			");
			mysqli_stmt_bind_param($purchaseStmt, "isdd", $user_id, $orderNumber, $totalAmount, $shippingCost);
			
			if (!mysqli_stmt_execute($purchaseStmt)) {
				throw new Exception('Failed to create purchase: ' . mysqli_stmt_error($purchaseStmt));
			}
			
			$purchaseId = mysqli_insert_id($conn);
			mysqli_stmt_close($purchaseStmt);
			
			// Insert purchase items
			$itemStmt = mysqli_prepare($conn, "
				INSERT INTO purchase_items (purchase_id, product_id, name, price, quantity, image) 
				VALUES (?, ?, ?, ?, ?, ?)
			");
			
			foreach ($selectedItems as $item) {
				mysqli_stmt_bind_param($itemStmt, "iisdis", 
					$purchaseId, 
					$item['product_id'], 
					$item['name'], 
					$item['price'], 
					$item['quantity'], 
					$item['image']
				);
				
				if (!mysqli_stmt_execute($itemStmt)) {
					throw new Exception('Failed to add purchase item: ' . mysqli_stmt_error($itemStmt));
				}
			}
			mysqli_stmt_close($itemStmt);
			
			// Clear cart for current user only if using legacy format
			if (!isset($input['action']) || $input['action'] !== 'create_from_selected_cart') {
				$clearCartStmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
				mysqli_stmt_bind_param($clearCartStmt, "i", $user_id);
				if (!mysqli_stmt_execute($clearCartStmt)) {
					throw new Exception('Failed to clear cart: ' . mysqli_stmt_error($clearCartStmt));
				}
				mysqli_stmt_close($clearCartStmt);
			}
			
			// Commit transaction
			mysqli_commit($conn);
			
			echo json_encode([
				'success' => true,
				'message' => 'Purchase created successfully',
				'order_number' => $orderNumber,
				'purchase_id' => $purchaseId,
				'total_amount' => $totalAmount
			]);
			
		} catch (Exception $e) {
			mysqli_rollback($conn);
			throw $e;
		}

	} elseif ($method === 'PATCH') {
		// Update purchase or purchase items (only for current user)
		$input = json_decode(file_get_contents('php://input'), true);
		
		if (!isset($input['action'])) {
			http_response_code(400);
			echo json_encode(['error' => 'Missing action parameter']);
			exit;
		}
		
		$action = $input['action'];
		
		if ($action === 'update_items') {
			// Update quantities of items in a purchase
			if (!isset($input['purchase_id'], $input['items'])) {
				http_response_code(400);
				echo json_encode(['error' => 'Missing purchase_id or items']);
				exit;
			}
			
			// Check if purchase belongs to current user and can be edited
			$statusStmt = mysqli_prepare($conn, "SELECT order_status FROM purchases WHERE id = ? AND user_id = ?");
			mysqli_stmt_bind_param($statusStmt, "ii", $input['purchase_id'], $user_id);
			mysqli_stmt_execute($statusStmt);
			$statusResult = mysqli_stmt_get_result($statusStmt);
			$statusRow = mysqli_fetch_assoc($statusResult);
			mysqli_stmt_close($statusStmt);
			
			if (!$statusRow) {
				http_response_code(404);
				echo json_encode(['error' => 'Order not found or access denied']);
				exit;
			}
			
			if ($statusRow['order_status'] !== 'pending') {
				http_response_code(400);
				echo json_encode(['error' => 'Order cannot be edited']);
				exit;
			}
			
			mysqli_begin_transaction($conn);
			
			try {
				$updateStmt = mysqli_prepare($conn, "UPDATE purchase_items SET quantity = ? WHERE id = ?");
				
				foreach ($input['items'] as $item) {
					if ($item['quantity'] > 0) {
						mysqli_stmt_bind_param($updateStmt, "ii", $item['quantity'], $item['id']);
						if (!mysqli_stmt_execute($updateStmt)) {
							throw new Exception('Failed to update item quantity: ' . mysqli_stmt_error($updateStmt));
						}
					}
				}
				mysqli_stmt_close($updateStmt);
				
				// Recalculate total amount
				$totalQuery = "
					SELECT SUM(price * quantity) as subtotal 
					FROM purchase_items 
					WHERE purchase_id = ?
				";
				$totalStmt = mysqli_prepare($conn, $totalQuery);
				mysqli_stmt_bind_param($totalStmt, "i", $input['purchase_id']);
				mysqli_stmt_execute($totalStmt);
				$totalResult = mysqli_stmt_get_result($totalStmt);
				$totalRow = mysqli_fetch_assoc($totalResult);
				mysqli_stmt_close($totalStmt);
				
				$subtotal = (float)$totalRow['subtotal'];
				$shippingCost = $subtotal >= 50.00 ? 0.00 : 5.00;
				$newTotalAmount = $subtotal + $shippingCost;
				
				// Update purchase total (verify user ownership again)
				$updatePurchaseStmt = mysqli_prepare($conn, "
					UPDATE purchases 
					SET total_amount = ?, shipping_cost = ?, updated_at = CURRENT_TIMESTAMP 
					WHERE id = ? AND user_id = ?
				");
				mysqli_stmt_bind_param($updatePurchaseStmt, "ddii", $newTotalAmount, $shippingCost, $input['purchase_id'], $user_id);
				
				if (!mysqli_stmt_execute($updatePurchaseStmt)) {
					throw new Exception('Failed to update purchase total: ' . mysqli_stmt_error($updatePurchaseStmt));
				}
				mysqli_stmt_close($updatePurchaseStmt);
				
				mysqli_commit($conn);
				
				echo json_encode([
					'success' => true,
					'message' => 'Order updated successfully',
					'new_total' => $newTotalAmount
				]);
				
			} catch (Exception $e) {
				mysqli_rollback($conn);
				throw $e;
			}
			
		} elseif ($action === 'cancel_order') {
			// Cancel an order (only user's own orders)
			if (!isset($input['purchase_id'])) {
				http_response_code(400);
				echo json_encode(['error' => 'Missing purchase_id']);
				exit;
			}
			
			// Check if order belongs to user and can be cancelled
			$statusStmt = mysqli_prepare($conn, "SELECT order_status FROM purchases WHERE id = ? AND user_id = ?");
			mysqli_stmt_bind_param($statusStmt, "ii", $input['purchase_id'], $user_id);
			mysqli_stmt_execute($statusStmt);
			$statusResult = mysqli_stmt_get_result($statusStmt);
			$statusRow = mysqli_fetch_assoc($statusResult);
			mysqli_stmt_close($statusStmt);
			
			if (!$statusRow) {
				http_response_code(404);
				echo json_encode(['error' => 'Order not found or access denied']);
				exit;
			}
			
			if (!in_array($statusRow['order_status'], ['pending', 'processing'])) {
				http_response_code(400);
				echo json_encode(['error' => 'Order cannot be cancelled']);
				exit;
			}
			
			$cancelStmt = mysqli_prepare($conn, "
				UPDATE purchases 
				SET order_status = 'cancelled', updated_at = CURRENT_TIMESTAMP 
				WHERE id = ? AND user_id = ?
			");
			mysqli_stmt_bind_param($cancelStmt, "ii", $input['purchase_id'], $user_id);
			
			if (mysqli_stmt_execute($cancelStmt)) {
				echo json_encode([
					'success' => true,
					'message' => 'Order cancelled successfully'
				]);
			} else {
				throw new Exception('Failed to cancel order: ' . mysqli_stmt_error($cancelStmt));
			}
			mysqli_stmt_close($cancelStmt);
			
		} elseif ($action === 'update_status') {
			// Update order status (admin function - requires additional admin check)
			// For now, we'll restrict this to prevent users from changing their own order status
			http_response_code(403);
			echo json_encode(['error' => 'Insufficient permissions']);
			
		} else {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid action']);
		}

	} elseif ($method === 'DELETE') {
		// Delete purchase items or entire purchase (only user's own)
		$input = json_decode(file_get_contents('php://input'), true);
		
		if (!isset($input['action'])) {
			http_response_code(400);
			echo json_encode(['error' => 'Missing action parameter']);
			exit;
		}
		
		$action = $input['action'];
		
		if ($action === 'remove_item') {
			// Remove specific item from purchase
			if (!isset($input['item_id'])) {
				http_response_code(400);
				echo json_encode(['error' => 'Missing item_id']);
				exit;
			}
			
			// Get purchase_id and check if order belongs to user and can be edited
			$itemQuery = "
				SELECT pi.purchase_id, p.order_status 
				FROM purchase_items pi 
				JOIN purchases p ON pi.purchase_id = p.id 
				WHERE pi.id = ? AND p.user_id = ?
			";
			$itemStmt = mysqli_prepare($conn, $itemQuery);
			mysqli_stmt_bind_param($itemStmt, "ii", $input['item_id'], $user_id);
			mysqli_stmt_execute($itemStmt);
			$itemResult = mysqli_stmt_get_result($itemStmt);
			$itemRow = mysqli_fetch_assoc($itemResult);
			mysqli_stmt_close($itemStmt);
			
			if (!$itemRow) {
				http_response_code(404);
				echo json_encode(['error' => 'Item not found or access denied']);
				exit;
			}
			
			if ($itemRow['order_status'] !== 'pending') {
				http_response_code(400);
				echo json_encode(['error' => 'Order cannot be edited']);
				exit;
			}
			
			mysqli_begin_transaction($conn);
			
			try {
				// Delete the item
				$deleteStmt = mysqli_prepare($conn, "DELETE FROM purchase_items WHERE id = ?");
				mysqli_stmt_bind_param($deleteStmt, "i", $input['item_id']);
				
				if (!mysqli_stmt_execute($deleteStmt)) {
					throw new Exception('Failed to delete item: ' . mysqli_stmt_error($deleteStmt));
				}
				mysqli_stmt_close($deleteStmt);
				
				// Check if any items remain
				$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as item_count FROM purchase_items WHERE purchase_id = ?");
				mysqli_stmt_bind_param($countStmt, "i", $itemRow['purchase_id']);
				mysqli_stmt_execute($countStmt);
				$countResult = mysqli_stmt_get_result($countStmt);
				$countRow = mysqli_fetch_assoc($countResult);
				mysqli_stmt_close($countStmt);
				
				if ($countRow['item_count'] == 0) {
					// No items left, cancel the order
					$cancelStmt = mysqli_prepare($conn, "
						UPDATE purchases 
						SET order_status = 'cancelled', updated_at = CURRENT_TIMESTAMP 
						WHERE id = ? AND user_id = ?
					");
					mysqli_stmt_bind_param($cancelStmt, "ii", $itemRow['purchase_id'], $user_id);
					
					if (!mysqli_stmt_execute($cancelStmt)) {
						throw new Exception('Failed to cancel empty order: ' . mysqli_stmt_error($cancelStmt));
					}
					mysqli_stmt_close($cancelStmt);
				} else {
					// Recalculate total amount
					$totalQuery = "
						SELECT SUM(price * quantity) as subtotal 
						FROM purchase_items 
						WHERE purchase_id = ?
					";
					$totalStmt = mysqli_prepare($conn, $totalQuery);
					mysqli_stmt_bind_param($totalStmt, "i", $itemRow['purchase_id']);
					mysqli_stmt_execute($totalStmt);
					$totalResult = mysqli_stmt_get_result($totalStmt);
					$totalRow = mysqli_fetch_assoc($totalResult);
					mysqli_stmt_close($totalStmt);
					
					$subtotal = (float)$totalRow['subtotal'];
					$shippingCost = $subtotal >= 50.00 ? 0.00 : 5.00;
					$newTotalAmount = $subtotal + $shippingCost;
					
					// Update purchase total (verify user ownership)
					$updatePurchaseStmt = mysqli_prepare($conn, "
						UPDATE purchases 
						SET total_amount = ?, shipping_cost = ?, updated_at = CURRENT_TIMESTAMP 
						WHERE id = ? AND user_id = ?
					");
					mysqli_stmt_bind_param($updatePurchaseStmt, "ddii", $newTotalAmount, $shippingCost, $itemRow['purchase_id'], $user_id);
					
					if (!mysqli_stmt_execute($updatePurchaseStmt)) {
						throw new Exception('Failed to update purchase total: ' . mysqli_stmt_error($updatePurchaseStmt));
					}
					mysqli_stmt_close($updatePurchaseStmt);
				}
				
				mysqli_commit($conn);
				
				echo json_encode([
					'success' => true,
					'message' => 'Item removed successfully'
				]);
				
			} catch (Exception $e) {
				mysqli_rollback($conn);
				throw $e;
			}
			
		} elseif ($action === 'delete_purchase') {
			// Delete entire purchase (only user's own cancelled orders)
			if (!isset($input['purchase_id'])) {
				http_response_code(400);
				echo json_encode(['error' => 'Missing purchase_id']);
				exit;
			}
			
			// Check if purchase belongs to user and can be deleted
			$statusStmt = mysqli_prepare($conn, "SELECT order_status FROM purchases WHERE id = ? AND user_id = ?");
			mysqli_stmt_bind_param($statusStmt, "ii", $input['purchase_id'], $user_id);
			mysqli_stmt_execute($statusStmt);
			$statusResult = mysqli_stmt_get_result($statusStmt);
			$statusRow = mysqli_fetch_assoc($statusResult);
			mysqli_stmt_close($statusStmt);
			
			if (!$statusRow) {
				http_response_code(404);
				echo json_encode(['error' => 'Purchase not found or access denied']);
				exit;
			}
			
			if ($statusRow['order_status'] !== 'cancelled') {
				http_response_code(400);
				echo json_encode(['error' => 'Only cancelled orders can be deleted']);
				exit;
			}
			
			// Delete purchase (items will be deleted automatically due to foreign key constraint)
			$deleteStmt = mysqli_prepare($conn, "DELETE FROM purchases WHERE id = ? AND user_id = ?");
			mysqli_stmt_bind_param($deleteStmt, "ii", $input['purchase_id'], $user_id);
			
			if (mysqli_stmt_execute($deleteStmt)) {
				echo json_encode([
					'success' => true,
					'message' => 'Purchase deleted successfully'
				]);
			} else {
				throw new Exception('Failed to delete purchase: ' . mysqli_stmt_error($deleteStmt));
			}
			mysqli_stmt_close($deleteStmt);
			
		} else {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid action']);
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