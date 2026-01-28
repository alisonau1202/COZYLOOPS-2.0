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
		// Handle different POST actions: create purchase, remove item, cancel order, update items
		
		if (!isset($input['action'])) {
			http_response_code(400);
			echo json_encode(['error' => 'Missing action parameter for POST request']);
			exit;
		}

		$action = $input['action'];

		if ($action === 'create_from_cart') {
			// Create new purchase from selected cart items for current user
			mysqli_begin_transaction($conn);
			
			try {
				// Validate selected_items
				if (!isset($input['selected_items']) || !is_array($input['selected_items']) || empty($input['selected_items'])) {
					throw new Exception('No selected items provided for checkout.');
				}
				
				$selectedItems = $input['selected_items'];

				// Generate order number
				$orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 3, '0', STR_PAD_LEFT);
				
				// Check if order number exists and regenerate if needed
				$checkStmt = mysqli_prepare($conn, "SELECT id FROM purchases WHERE order_number = ?");
				mysqli_stmt_bind_param($checkStmt, "s", $orderNumber);
				mysqli_stmt_execute($checkStmt);
				$checkResult = mysqli_stmt_get_result($checkStmt);
				
				while (mysqli_num_rows($checkResult) > 0) {
					$orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 3, '0', STR_PAD_LEFT);
					mysqli_stmt_bind_param($checkStmt, "s", $orderNumber);
					mysqli_stmt_execute($checkStmt);
					$checkResult = mysqli_stmt_get_result($checkStmt);
				}
				mysqli_stmt_close($checkStmt);
				
				// Calculate subtotal and shipping based on selected items
				$subtotal = 0;
				foreach ($selectedItems as $item) {
					$subtotal += (float)$item['price'] * (int)$item['quantity'];
				}
				
				$shippingCost = $subtotal >= 50.00 ? 0.00 : 5.00;
				$totalAmount = $subtotal + $shippingCost;
				
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
						$item['id'], // Use item.id (which is product_id)
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
				
				// Remove only the selected items from the cart
				foreach ($selectedItems as $item) {
					$productId = $item['id']; // This is the product_id from the grouped item
					$quantityToRemove = $item['quantity'];

					// Fetch specific cart entry IDs to delete
					$selectCartItemsStmt = mysqli_prepare($conn, "SELECT id FROM cart WHERE user_id = ? AND product_id = ? LIMIT ?");
					if (!$selectCartItemsStmt) {
						throw new Exception('Failed to prepare select cart items statement: ' . mysqli_error($conn));
					}
					mysqli_stmt_bind_param($selectCartItemsStmt, "iii", $user_id, $productId, $quantityToRemove);
					mysqli_stmt_execute($selectCartItemsStmt);
					$cartItemIdsResult = mysqli_stmt_get_result($selectCartItemsStmt);
					
					$idsToDelete = [];
					while ($row = mysqli_fetch_assoc($cartItemIdsResult)) {
						$idsToDelete[] = $row['id'];
					}
					mysqli_stmt_close($selectCartItemsStmt);

					if (!empty($idsToDelete)) {
						$idList = implode(',', $idsToDelete);
						// Prepare and execute statement to delete specific cart items by ID
						$deleteSpecificCartItemsStmt = mysqli_prepare($conn, "DELETE FROM cart WHERE id IN ($idList)");
						if (!$deleteSpecificCartItemsStmt) {
							throw new Exception('Failed to prepare delete specific cart items statement: ' . mysqli_error($conn));
						}
						if (!mysqli_stmt_execute($deleteSpecificCartItemsStmt)) {
							throw new Exception('Failed to clear specific cart items: ' . mysqli_stmt_error($deleteSpecificCartItemsStmt));
						}
						mysqli_stmt_close($deleteSpecificCartItemsStmt);
					}
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
		} 
		// NEW CASE: Remove specific item from order (moved from DELETE method)
		elseif ($action === 'remove_item_from_order') {
			if (!isset($input['item_id']) || !isset($input['purchase_id'])) {
				http_response_code(400);
				echo json_encode(['error' => 'Missing item_id or purchase_id']);
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
				echo json_encode(['error' => 'Order cannot be edited (status: ' . $itemRow['order_status'] . ')']);
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
						throw new Exception('Failed to update purchase total after item removal: ' . mysqli_stmt_error($updatePurchaseStmt));
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
		}
		// NEW CASE: Cancel an order (moved from PATCH method)
		elseif ($action === 'cancel_purchase') {
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
				echo json_encode(['error' => 'Order cannot be cancelled. Current status: ' . $statusRow['order_status']]);
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
			
		} 
		// NEW CASE: Update items in order (moved from PATCH method)
		elseif ($action === 'update_items_in_order') {
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
					// Ensure quantity is not negative
					$quantity = max(0, (int)$item['quantity']); 
					if ($quantity > 0) {
						mysqli_stmt_bind_param($updateStmt, "ii", $quantity, $item['id']);
						if (!mysqli_stmt_execute($updateStmt)) {
							throw new Exception('Failed to update item quantity: ' . mysqli_stmt_error($updateStmt));
						}
					} else {
						// If quantity is 0, delete the item (optional, or handle separately if you allow 0 quantity)
						$deleteItemStmt = mysqli_prepare($conn, "DELETE FROM purchase_items WHERE id = ?");
						mysqli_stmt_bind_param($deleteItemStmt, "i", $item['id']);
						if (!mysqli_stmt_execute($deleteItemStmt)) {
							throw new Exception('Failed to delete item with zero quantity: ' . mysqli_stmt_error($deleteItemStmt));
						}
						mysqli_stmt_close($deleteItemStmt);
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
				
				$subtotal = (float)($totalRow['subtotal'] ?? 0.00); // Handle case where all items are removed (subtotal is null)
				$shippingCost = $subtotal >= 50.00 ? 0.00 : 5.00;
				$newTotalAmount = $subtotal + $shippingCost;

				// Check if any items remain after update/delete, if not, cancel the order
				$countItemsQuery = "SELECT COUNT(*) FROM purchase_items WHERE purchase_id = ?";
				$countItemsStmt = mysqli_prepare($conn, $countItemsQuery);
				mysqli_stmt_bind_param($countItemsStmt, "i", $input['purchase_id']);
				mysqli_stmt_execute($countItemsStmt);
				$countItemsResult = mysqli_stmt_get_result($countItemsStmt);
				$itemsExist = mysqli_fetch_row($countItemsResult)[0] > 0;
				mysqli_stmt_close($countItemsStmt);

				if (!$itemsExist) {
					// If no items are left, cancel the order
					$cancelOrderIfEmptyStmt = mysqli_prepare($conn, "UPDATE purchases SET order_status = 'cancelled', total_amount = ?, shipping_cost = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
					mysqli_stmt_bind_param($cancelOrderIfEmptyStmt, "ddii", $newTotalAmount, $shippingCost, $input['purchase_id'], $user_id);
					if (!mysqli_stmt_execute($cancelOrderIfEmptyStmt)) {
						throw new Exception('Failed to cancel order after all items removed: ' . mysqli_stmt_error($cancelOrderIfEmptyStmt));
					}
					mysqli_stmt_close($cancelOrderIfEmptyStmt);
					echo json_encode([
						'success' => true,
						'message' => 'All items removed, order cancelled successfully',
						'new_total' => $newTotalAmount,
						'order_status' => 'cancelled'
					]);
					mysqli_commit($conn);
					exit; // Exit after handling this case
				}
				
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
					'new_total' => $newTotalAmount,
					'order_status' => 'pending' // Assuming it remains pending if items exist
				]);
				
			} catch (Exception $e) {
				mysqli_rollback($conn);
				throw $e;
			}
		}
		else {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid action for POST request']);
		}

	} elseif ($method === 'PATCH') {
		// All PATCH actions are now deprecated in favor of POST with 'action' parameter.
		// The original 'update_items' is now 'update_items_in_order' in POST.
		// The original 'cancel_order' is now 'cancel_purchase' in POST.
		$input = json_decode(file_get_contents('php://input'), true); // Re-decode for safety
		if (isset($input['action'])) {
			http_response_code(405);
			echo json_encode(['error' => "PATCH method for action '{$input['action']}' is no longer supported. Please use POST instead."]);
		} else {
			http_response_code(405);
			echo json_encode(['error' => 'PATCH method is not supported for this endpoint. Please use POST with an action parameter.']);
		}
		
	} elseif ($method === 'DELETE') {
		// All DELETE actions are now deprecated in favor of POST with 'action' parameter.
		// The original 'remove_item' is now 'remove_item_from_order' in POST.
		// The original 'delete_purchase' is now handled elsewhere if needed (e.g., in POST).
		
		http_response_code(405); // Method Not Allowed for specific DELETE operations now.
		echo json_encode(['error' => 'DELETE method is not supported for specific purchase operations. Please use POST with an action parameter.']);


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
