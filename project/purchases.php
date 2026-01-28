<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Purchase History - CozyLoops</title>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	
	<!-- Bootstrap-->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	
	<!-- Font Awesome & Fonts -->
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;700&display=swap" rel="stylesheet">
	
	<!-- Custom Styles -->
	<link href="style/style.css" rel="stylesheet">
</head>
<body>
	<?php include 'commons/header.php'; ?>

	<div class="container py-4" id="purchaseApp">
		<h2 class="mb-4 text-center">🛍️ Purchase History</h2>

		<!-- Search and Filter -->
		<div class="filter-section mb-4">
			<div class="row">
				<div class="col-md-6">
					<div class="mb-3">
						<label for="searchOrder" class="form-label"><i class="fas fa-search me-2"></i>Search Orders</label>
						<div class="position-relative">
							<input 
								type="text" 
								class="form-control" 
								id="searchOrder"
								v-model="searchQuery"
								placeholder="Search by order number or item name..."
							>
							<!-- Clear button -->
							<button 
								v-show="searchQuery.trim() !== ''"
								@click="searchQuery = ''"
								class="btn position-absolute"
								style="right: 8px; top: 50%; transform: translateY(-50%); border: none; background: transparent; color: #6c757d; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s ease;"
								onmouseover="this.style.backgroundColor='#f8f9fa'; this.style.color='#495057';"
								onmouseout="this.style.backgroundColor='transparent'; this.style.color='#6c757d';"
								type="button"
								title="Clear search"
							>
								<i class="fas fa-times" style="font-size: 14px;"></i>
							</button>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="mb-3">
						<label for="statusFilter" class="form-label"><i class="fas fa-filter me-2"></i>Filter by Status</label>
						<select class="form-select" id="statusFilter" v-model="statusFilter">
							<option value="">All Orders</option>
							<option value="pending">Pending</option>
							<option value="cancelled">Cancelled</option>
						</select>
					</div>
				</div>
				<div class="col-md-3">
					<div class="mb-3">
						<label for="sortBy" class="form-label"><i class="fas fa-sort me-2"></i>Sort by</label>
						<select class="form-select" id="sortBy" v-model="sortBy">
							<option value="newest">Newest First</option>
							<option value="oldest">Oldest First</option>
							<option value="amount_high">Highest Amount</option>
							<option value="amount_low">Lowest Amount</option>
						</select>
					</div>
				</div>
			</div>
		</div>

		<!-- No purchases message -->
		<div v-if="filteredPurchases.length === 0 && !loading" class="text-center mt-5">
			<p class="lead">No purchases found... 🌸</p>
			<p>Your purchase history will appear here once you make some orders!</p>
		</div>

		<!-- Loading state -->
		<div v-if="loading" class="text-center mt-5">
			<div class="spinner-border text-primary" role="status">
				<span class="visually-hidden">Loading...</span>
			</div>
		</div>

		<!-- Purchase Items -->
		<div v-for="purchase in filteredPurchases" :key="purchase.id" class="purchase-container">
			<div class="purchase-header">
				<div class="row align-items-center">
					<div class="col-md-6">
						<h5 class="mb-1">Order #{{ purchase.order_number }}</h5>
						<small class="text-muted">{{ formatDate(purchase.order_date) }}</small>
					</div>
					<div class="col-md-3">
						<span :class="'status-badge status-' + purchase.order_status">
							{{ purchase.order_status }}
						</span>
					</div>
					<div class="col-md-3 text-end">
						<div class="btn-group btn-group-sm">
							<button 
								class="btn btn-outline-cozy"
								@click="toggleEdit(purchase.id)"
								v-if="purchase.order_status === 'pending'"
							>
								<i class="fas fa-edit"></i> Edit
							</button>
							<button 
								class="btn btn-danger-soft"
								@click="cancelOrder(purchase.id)"
								v-if="purchase.order_status === 'pending' || purchase.order_status === 'processing'"
							>
								<i class="fas fa-times"></i> Cancel
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Edit Form -->
			<div v-if="editingOrder === purchase.id" class="edit-form">
				<h6>Edit Order Items</h6>
				<div v-for="item in purchase.items" :key="item.id" class="row align-items-center mb-2">
					<div class="col-md-6">
						<strong>{{ item.name }}</strong>
					</div>
					<div class="col-md-2">
						<input 
							type="number" 
							class="form-control quantity-input" 
							v-model.number="item.newQuantity"
							min="1"
						>
					</div>
					<div class="col-md-2">
						${{ (item.price * item.newQuantity).toFixed(2) }}
					</div>
					<div class="col-md-2">
						<button 
							class="btn btn-danger-soft btn-sm"
							@click="removeItemFromOrder(purchase.id, item.id)"
						>
							<i class="fas fa-trash"></i>
						</button>
					</div>
				</div>
				<div class="mt-3">
					<button class="btn profile-btn delete-btn me-2" @click="saveOrderChanges(purchase.id)">
						<i class="fas fa-save icon-spacing"></i>Save Changes
					</button>
					<button class="btn profile-btn cancel-btn" @click="cancelEdit()">
						Cancel
					</button>
				</div>
			</div>

			<!-- Order Items Display -->
			<div v-else>
				<div v-if="purchase.items && purchase.items.length === 0" class="text-center text-muted py-4">
					<p>This order currently has no items.</p>
				</div>
				<div v-for="item in purchase.items" :key="item.id" class="purchase-item">
					<div class="row align-items-center">
						<div class="col-2">
							<img :src="item.image" class="img-fluid" :alt="item.name" style="max-height: 60px;">
						</div>
						<div class="col-6">
							<h6 class="mb-1">{{ item.name }}</h6>
							<small class="text-muted">Quantity: {{ item.quantity }}</small>
						</div>
						<div class="col-2">
							<span class="fw-bold">${{ item.price.toFixed(2) }}</span>
						</div>
						<div class="col-2 text-end">
							<span class="fw-bold">${{ (item.price * item.quantity).toFixed(2) }}</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Order Summary -->
			<div class="row mt-3 pt-3" style="border-top: 1px solid #e9ecef;">
				<div class="col-md-8"></div>
				<div class="col-md-4">
					<div class="d-flex justify-content-between mb-1">
						<span>Subtotal:</span>
						<span>${{ (purchase.total_amount - purchase.shipping_cost).toFixed(2) }}</span>
					</div>
					<div class="d-flex justify-content-between mb-1">
						<span>Shipping:</span>
						<span v-if="purchase.shipping_cost === 0" class="text-success">FREE</span>
						<span v-else>${{ purchase.shipping_cost.toFixed(2) }}</span>
					</div>
					<div class="d-flex justify-content-between fw-bold">
						<span>Total:</span>
						<span>${{ purchase.total_amount.toFixed(2) }}</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Confirmation Dialog -->
		<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content custom-card">
					<div class="modal-header">
						<h5 class="modal-title section-title" id="confirmationModalLabel">{{ confirmTitle }}</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" @click="closeConfirm"></button>
					</div>
					<div class="modal-body text-muted">
						{{ confirmMessage }}
					</div>
					<div class="modal-footer">
						<button type="button" class="btn profile-btn cancel-btn" data-bs-dismiss="modal" @click="closeConfirm">Cancel</button>
						<button type="button" class="btn profile-btn delete-btn" @click="executeConfirmAction">Confirm</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php include 'commons/footer.php'; ?>

	<!-- Load Bootstrap JS first -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

	<!-- Load Vue and custom cart script -->
	<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
	<script src="purchases.js"></script>

</body>
</html>